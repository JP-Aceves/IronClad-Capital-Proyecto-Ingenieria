<?php
/**
 * Endpoint REST para el ticker en tiempo real
 * GET /wp-json/live-ticker/v1/quotes?symbols=BTC-USD,ETH-USD,SPY
 */
class LMT_REST_API {

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('live-ticker/v1', '/quotes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_quotes'],
            'permission_callback' => '__return_true',
            'args'                => [
                'symbols' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => is_string($v) && strlen($v) < 500,
                    'sanitize_callback' => fn($v) => sanitize_text_field($v),
                ],
            ],
        ]);

        register_rest_route('live-ticker/v1', '/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_config'],
            'permission_callback' => '__return_true',
        ]);

        // Metadatos visuales de los activos (iconos, nombres, colores)
        register_rest_route('live-ticker/v1', '/assets', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'get_assets'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function get_quotes(WP_REST_Request $request): WP_REST_Response {
        $raw     = $request->get_param('symbols');
        $symbols = array_filter(
            array_map('trim', explode(',', strtoupper($raw))),
            fn($s) => preg_match('/^[A-Z0-9.\-]{1,15}$/', $s)
        );

        if (empty($symbols)) {
            return new WP_REST_Response(['error' => 'No valid symbols provided'], 400);
        }

        // Limitar a 20 símbolos máximo
        $symbols = array_slice(array_values($symbols), 0, 20);
        $data    = LMT_Yahoo_Finance::fetch($symbols);

        return new WP_REST_Response([
            'success'   => true,
            'data'      => array_values($data),
            'timestamp' => time(),
        ], 200, [
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public static function get_config(WP_REST_Request $request): WP_REST_Response {
        $options = get_option('lmt_settings', []);
        return new WP_REST_Response([
            'symbols'       => $options['symbols']       ?? 'BTC-USD,ETH-USD,SPY,QQQ,SOL-USD',
            'refresh_rate'  => $options['refresh_rate']  ?? 60,
            'show_sparkline'=> $options['show_sparkline'] ?? true,
        ]);
    }

    /**
     * Devuelve el mapa símbolo → metadatos visuales del dashboard.
     * El frontend lo usa para mostrar nombres, colores e iconos correctos.
     */
    public static function get_assets(WP_REST_Request $request): WP_REST_Response {
        $dash   = get_option('lmt_dashboard', null);
        $assets = $dash['assets'] ?? [];

        // Construir mapa indexado por símbolo para fácil acceso en JS
        $map = [];
        foreach ($assets as $a) {
            $sym = strtoupper($a['symbol'] ?? '');
            if (!$sym) continue;
            $map[$sym] = [
                'label'    => $a['label']    ?? $sym,
                'ticker'   => $a['ticker']   ?? $sym,
                'color'    => $a['color']    ?? 'slate',
                'iconType' => $a['iconType'] ?? 'svg',
                'iconUrl'  => $a['iconUrl']  ?? '',
            ];
        }

        return new WP_REST_Response($map, 200, [
            'Cache-Control' => 'public, max-age=300', // 5 min cache
        ]);
    }
}
