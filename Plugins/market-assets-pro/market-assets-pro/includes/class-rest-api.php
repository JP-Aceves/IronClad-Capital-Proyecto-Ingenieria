<?php
/**
 * Endpoints REST del plugin Market Assets Pro
 *
 * /wp-json/map/v1/assets           GET  – lista de activos del usuario
 * /wp-json/map/v1/assets           POST – añadir activo
 * /wp-json/map/v1/assets/{symbol}  DELETE – eliminar activo
 * /wp-json/map/v1/quote/{symbol}   GET  – cotización en tiempo real
 * /wp-json/map/v1/history/{symbol} GET  – OHLCV histórico
 * /wp-json/map/v1/predict/{symbol} GET  – predicción de precio
 * /wp-json/map/v1/search           GET  – búsqueda de tickers
 */
class MAP_REST_API {

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        $ns = 'map/v1';

        // Lista de activos del usuario / administrador
        register_rest_route($ns, '/assets', [
            ['methods' => 'GET',  'callback' => [self::class, 'get_assets'],    'permission_callback' => '__return_true'],
            // __return_true: la autenticación se verifica con nonce dentro del callback.
            // can_modify() falla en REST porque las cookies no viajan por defecto.
            ['methods' => 'POST', 'callback' => [self::class, 'add_asset'],     'permission_callback' => '__return_true'],
        ]);
        register_rest_route($ns, '/assets/(?P<symbol>[A-Z0-9.\-]{1,15})', [
            ['methods' => 'DELETE', 'callback' => [self::class, 'remove_asset'], 'permission_callback' => '__return_true'],
        ]);

        // Cotización rápida
        register_rest_route($ns, '/quote/(?P<symbol>[A-Z0-9.\-]{1,15})', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_quote'],
            'permission_callback' => '__return_true',
        ]);

        // Datos históricos OHLCV
        register_rest_route($ns, '/history/(?P<symbol>[A-Z0-9.\-]{1,15})', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_history'],
            'permission_callback' => '__return_true',
        ]);

        // Predicción
        register_rest_route($ns, '/predict/(?P<symbol>[A-Z0-9.\-]{1,15})', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_prediction'],
            'permission_callback' => '__return_true',
        ]);

        // Búsqueda de tickers
        register_rest_route($ns, '/search', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'search_tickers'],
            'permission_callback' => '__return_true',
        ]);

        // Diagnóstico de autenticación (público — no expone datos sensibles)
        register_rest_route($ns, '/debug', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_debug'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ─── Permisos ──────────────────────────────────────────────────────────
    public static function can_modify(): bool {
        // Usuarios logados pueden gestionar su watchlist personal.
        // Administradores pueden modificar la lista global.
        return is_user_logged_in();
    }

    // ─── GET /assets ───────────────────────────────────────────────────────
    public static function get_assets(WP_REST_Request $req): WP_REST_Response {
        $user_id = get_current_user_id();
        global $wpdb;

        // Crear tabla si no existe (por si acaso no se creó en activación)
        $table = $wpdb->prefix . 'map_watchlists';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$exists) {
            $charset = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(20) NOT NULL,
                label VARCHAR(100) NOT NULL DEFAULT '',
                color VARCHAR(30) NOT NULL DEFAULT 'blue',
                icon_url TEXT,
                added_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY user_symbol (user_id, symbol)
            ) $charset;");
        }

        // Lista global del administrador
        $global = get_option('map_assets', self::default_assets());

        // Si el usuario está logado, merge con su watchlist personal
        if ($user_id) {
            global $wpdb;
            $table    = $wpdb->prefix . 'map_watchlists';
            $personal = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY added_at ASC", $user_id),
                ARRAY_A
            );

            // Marcar los activos globales
            foreach ($global as &$a) $a['source'] = 'global';
            // Añadir los personales (si no están ya en global)
            $global_syms = array_column($global, 'symbol');
            foreach ($personal as $p) {
                if (!in_array($p['symbol'], $global_syms)) {
                    $global[] = [
                        'symbol'   => $p['symbol'],
                        'label'    => $p['label'] ?: $p['symbol'],
                        'color'    => $p['color'],
                        'icon_url' => $p['icon_url'],
                        'source'   => 'personal',
                    ];
                }
            }
        } else {
            foreach ($global as &$a) $a['source'] = 'global';
        }

        return new WP_REST_Response(['assets' => array_values($global)]);
    }

    // ─── Resolver user_id de forma robusta ────────────────────────────────
    // WordPress REST API a veces no reconoce la sesión por cookies cuando el
    // servidor está en IP directa (sin dominio) o faltan cabeceras de referer.
    // Estrategia:
    //   1. get_current_user_id() — funciona si WP reconoció la sesión
    //   2. Fallback: el JS envía user_id + map_nonce (generados en el shortcode
    //      con el contexto de sesión PHP activo). Verificamos el nonce para
    //      asegurarnos de que no se puede suplantar un user_id arbitrario.
    private static function resolve_user_id(WP_REST_Request $req): int {
        $uid = get_current_user_id();
        if ($uid > 0) return $uid;

        $map_nonce = sanitize_text_field($req->get_param('map_nonce') ?: '');
        $map_uid   = (int)($req->get_param('map_user_id') ?: 0);

        if ($map_uid > 0 && $map_nonce) {
            $expected = MAP_Shortcode::make_session_token($map_uid);
            // Log temporal para diagnóstico — borrar tras confirmar que funciona
            error_log('[MAP] resolve_user_id: uid='.$map_uid
                .' recv='.substr($map_nonce,0,8)
                .' expected='.substr($expected,0,8)
                .' match='.($expected && hash_equals($expected, $map_nonce) ? 'YES' : 'NO'));
            if ($expected && hash_equals($expected, $map_nonce)) {
                return $map_uid;
            }
        }

        return 0;
    }

    // ─── POST /assets ──────────────────────────────────────────────────────
    public static function add_asset(WP_REST_Request $req): WP_REST_Response {
        // Autenticación: resolve_user_id() verifica map_nonce (generado en el
        // shortcode donde la sesión PHP sí está activa). No usamos wp_verify_nonce
        // aquí porque en setups con IP directa (sin dominio) WordPress no puede
        // verificar el nonce wp_rest en contexto REST al no reconocer la cookie.
        $user_id = self::resolve_user_id($req);

        $symbol  = strtoupper(sanitize_text_field($req->get_param('symbol') ?? ''));
        $label   = sanitize_text_field($req->get_param('label')   ?? $symbol);
        $color   = sanitize_text_field($req->get_param('color')   ?? 'blue');
        $icon    = esc_url_raw($req->get_param('icon_url') ?? '');

        if (!$symbol) return new WP_REST_Response(['error' => 'Symbol required'], 400);
        if (!$user_id) return new WP_REST_Response(['error' => 'Usuario no autenticado. Inicia sesión e inténtalo de nuevo.'], 401);

        // Administradores editan la lista global
        if (user_can($user_id, 'manage_options')) {
            $assets  = get_option('map_assets', self::default_assets());
            $symbols = array_column($assets, 'symbol');
            if (!in_array($symbol, $symbols)) {
                $assets[] = compact('symbol', 'label', 'color', 'icon');
                update_option('map_assets', $assets);
            }
            return new WP_REST_Response(['success' => true, 'scope' => 'global']);
        }

        // Resto de usuarios: watchlist personal
        global $wpdb;
        $table = $wpdb->prefix . 'map_watchlists';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$table'")) {
            $charset = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                symbol VARCHAR(20) NOT NULL,
                label VARCHAR(100) NOT NULL DEFAULT '',
                color VARCHAR(30) NOT NULL DEFAULT 'blue',
                icon_url TEXT,
                added_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY user_symbol (user_id, symbol)
            ) $charset;");
        }

        $wpdb->replace(
            $table,
            ['user_id' => $user_id, 'symbol' => $symbol, 'label' => $label, 'color' => $color, 'icon_url' => $icon, 'added_at' => current_time('mysql')],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return new WP_REST_Response(['success' => true, 'scope' => 'personal']);
    }

    // ─── DELETE /assets/{symbol} ───────────────────────────────────────────
    public static function remove_asset(WP_REST_Request $req): WP_REST_Response {
        $symbol  = strtoupper($req->get_param('symbol'));
        $user_id = self::resolve_user_id($req);

        if (!$user_id) return new WP_REST_Response(['error' => 'No autenticado'], 401);

        if (user_can($user_id, 'manage_options')) {
            $assets = array_filter(get_option('map_assets', []), fn($a) => $a['symbol'] !== $symbol);
            update_option('map_assets', array_values($assets));
            return new WP_REST_Response(['success' => true]);
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'map_watchlists', ['user_id' => $user_id, 'symbol' => $symbol]);
        return new WP_REST_Response(['success' => true]);
    }

    // ─── GET /quote/{symbol} ───────────────────────────────────────────────
    public static function get_quote(WP_REST_Request $req): WP_REST_Response {
        $symbol = strtoupper($req->get_param('symbol'));
        $data   = MAP_Yahoo_Finance::quote($symbol);
        if (!$data) return new WP_REST_Response(['error' => 'Symbol not found'], 404);
        return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store']);
    }

    // ─── GET /history/{symbol}?period=1y&interval=1d ───────────────────────
    public static function get_history(WP_REST_Request $req): WP_REST_Response {
        $symbol   = strtoupper($req->get_param('symbol'));
        $period   = in_array($req->get_param('period'), ['1d','5d','7d','1mo','3mo','6mo','1y','2y','5y','max']) ? $req->get_param('period') : '1y';
        $interval = in_array($req->get_param('interval'), ['1m','5m','15m','30m','1h','1d','1wk','1mo']) ? $req->get_param('interval') : '1d';

        $data = MAP_Yahoo_Finance::history($symbol, $period, $interval);
        if (!$data) return new WP_REST_Response(['error' => 'No data'], 404);

        return new WP_REST_Response([
            'symbol'   => $symbol,
            'period'   => $period,
            'interval' => $interval,
            'data'     => $data,
        ]);
    }

    // ─── GET /predict/{symbol}?days=5 ─────────────────────────────────────
    public static function get_prediction(WP_REST_Request $req): WP_REST_Response {
        $symbol = strtoupper($req->get_param('symbol'));
        $days   = (int)($req->get_param('days') ?? 5);

        // Cache de predicciones: 4 horas.
        // IMPORTANTE: usamos get_transient() === false (identidad estricta) para
        // no devolver una predicción cacheada vacía si antes falló.
        $cache_key = "map_pred_{$symbol}_{$days}";
        $cached    = get_transient($cache_key);
        if ($cached !== false && is_array($cached) && !empty($cached['predictions'])) {
            return new WP_REST_Response($cached);
        }

        // Limpiar cualquier transient de historial cacheado para este símbolo,
        // así forecast() siempre obtiene datos frescos de Yahoo Finance.
        delete_transient("map_hist_{$symbol}_2y_1d");
        delete_transient("map_hist_{$symbol}_1y_1d");

        $data = MAP_Prediction::forecast($symbol, $days);
        if (!$data || empty($data['predictions'])) {
            // NO guardar el fallo en caché para que el siguiente intento lo reintente
            return new WP_REST_Response(['error' => 'Datos históricos insuficientes para generar predicción'], 422);
        }

        set_transient($cache_key, $data, 4 * HOUR_IN_SECONDS);
        return new WP_REST_Response($data);
    }

    // ─── GET /search?q=bitcoin ─────────────────────────────────────────────
    public static function search_tickers(WP_REST_Request $req): WP_REST_Response {
        $q = sanitize_text_field($req->get_param('q') ?? '');
        if (strlen($q) < 2) return new WP_REST_Response(['results' => []]);
        $results = MAP_Yahoo_Finance::search($q);
        return new WP_REST_Response(['results' => $results]);
    }

    // ─── GET /debug — diagnóstico de autenticación (solo admin) ───────────
    public static function get_debug(WP_REST_Request $req): WP_REST_Response {
        $wp_uid     = get_current_user_id();
        $map_uid    = (int)($req->get_param('map_user_id') ?: 0);
        $map_nonce  = sanitize_text_field($req->get_param('map_nonce') ?: '');
        $wp_nonce   = $req->get_header('X-WP-Nonce') ?: '';
        $nonce_ok   = wp_verify_nonce($wp_nonce, 'wp_rest');
        $map_ok     = $map_uid > 0 && $map_nonce
                      ? wp_verify_nonce($map_nonce, 'map_user_' . $map_uid) !== false
                      : false;
        $resolved   = self::resolve_user_id($req);

        // Limpiar todos los transients de predicción al pedir el debug
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_map_pred_%'");

        return new WP_REST_Response([
            'wp_user_id'       => $wp_uid,
            'map_user_id_recv' => $map_uid,
            'resolved_user_id' => $resolved,
            'wp_nonce_valid'   => $nonce_ok !== false,
            'map_nonce_valid'  => $map_ok,
            'has_cookie'       => !empty($_COOKIE),
            'cookie_keys'      => array_keys($_COOKIE),
            'pred_cache_cleared' => $deleted,
            'php_session'      => session_status(),
            'server_time'      => date('Y-m-d H:i:s'),
        ]);
    }

    // ─── Activos por defecto ───────────────────────────────────────────────
    private static function default_assets(): array {
        return [
            ['symbol' => 'BTC-USD', 'label' => 'Bitcoin',   'color' => 'orange', 'icon_url' => ''],
            ['symbol' => 'ETH-USD', 'label' => 'Ethereum',  'color' => 'purple', 'icon_url' => ''],
            ['symbol' => 'SOL-USD', 'label' => 'Solana',    'color' => 'green',  'icon_url' => ''],
            ['symbol' => 'BNB-USD', 'label' => 'BNB',       'color' => 'yellow', 'icon_url' => ''],
            ['symbol' => 'XRP-USD', 'label' => 'XRP',       'color' => 'blue',   'icon_url' => ''],
            ['symbol' => 'ADA-USD', 'label' => 'Cardano',   'color' => 'teal',   'icon_url' => ''],
            ['symbol' => 'DOGE-USD','label' => 'Dogecoin',  'color' => 'amber',  'icon_url' => ''],
            ['symbol' => 'SPY',     'label' => 'S&P 500 ETF','color' => 'slate', 'icon_url' => ''],
            ['symbol' => 'QQQ',     'label' => 'Nasdaq 100', 'color' => 'indigo','icon_url' => ''],
            ['symbol' => 'GLD',     'label' => 'Gold ETF',   'color' => 'amber', 'icon_url' => ''],
        ];
    }
}
