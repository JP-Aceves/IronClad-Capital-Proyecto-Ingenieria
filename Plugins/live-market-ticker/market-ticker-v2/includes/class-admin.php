<?php
/**
 * Live Market Ticker — Admin Dashboard
 */
class LMT_Admin {

    private static function default_assets(): array {
        return [
            ['symbol'=>'BTC-USD',   'label'=>'Bitcoin',       'ticker'=>'BTC',  'type'=>'crypto', 'color'=>'orange','iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'ETH-USD',   'label'=>'Ethereum',      'ticker'=>'ETH',  'type'=>'crypto', 'color'=>'blue',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'SOL-USD',   'label'=>'Solana',        'ticker'=>'SOL',  'type'=>'crypto', 'color'=>'purple','iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'BNB-USD',   'label'=>'BNB',           'ticker'=>'BNB',  'type'=>'crypto', 'color'=>'yellow','iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'XRP-USD',   'label'=>'Ripple',        'ticker'=>'XRP',  'type'=>'crypto', 'color'=>'sky',   'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'ADA-USD',   'label'=>'Cardano',       'ticker'=>'ADA',  'type'=>'crypto', 'color'=>'teal',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'DOGE-USD',  'label'=>'Dogecoin',      'ticker'=>'DOGE', 'type'=>'crypto', 'color'=>'amber', 'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'DOT-USD',   'label'=>'Polkadot',      'ticker'=>'DOT',  'type'=>'crypto', 'color'=>'pink',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'DOT-EUR',   'label'=>'DOT/EUR',       'ticker'=>'DOT',  'type'=>'crypto', 'color'=>'pink',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'LINK-USD',  'label'=>'Chainlink',     'ticker'=>'LINK', 'type'=>'crypto', 'color'=>'blue',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'LINK',      'label'=>'Chainlink',     'ticker'=>'LINK', 'type'=>'crypto', 'color'=>'blue',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'AVAX-USD',  'label'=>'Avalanche',     'ticker'=>'AVAX', 'type'=>'crypto', 'color'=>'red',   'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'UNI-USD',   'label'=>'Uniswap',       'ticker'=>'UNI',  'type'=>'crypto', 'color'=>'pink',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'MATIC-USD', 'label'=>'Polygon',       'ticker'=>'MATIC','type'=>'crypto', 'color'=>'purple','iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'LTC-USD',   'label'=>'Litecoin',      'ticker'=>'LTC',  'type'=>'crypto', 'color'=>'slate', 'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'ATOM-USD',  'label'=>'Cosmos',        'ticker'=>'ATOM', 'type'=>'crypto', 'color'=>'indigo','iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'NEAR-USD',  'label'=>'NEAR Protocol', 'ticker'=>'NEAR', 'type'=>'crypto', 'color'=>'green', 'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'APT-USD',   'label'=>'Aptos',         'ticker'=>'APT',  'type'=>'crypto', 'color'=>'teal',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'SUI-USD',   'label'=>'Sui',           'ticker'=>'SUI',  'type'=>'crypto', 'color'=>'blue',  'iconType'=>'crypto','iconUrl'=>''],
            ['symbol'=>'SPY',  'label'=>'S&P 500 ETF',    'ticker'=>'SPY',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'QQQ',  'label'=>'Nasdaq ETF',     'ticker'=>'QQQ',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'DIA',  'label'=>'Dow Jones ETF',  'ticker'=>'DIA',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'IWM',  'label'=>'Russell 2000',   'ticker'=>'IWM',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'VTI',  'label'=>'Total Market',   'ticker'=>'VTI',  'type'=>'etf',   'color'=>'green', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'GLD',  'label'=>'Gold ETF',       'ticker'=>'GLD',  'type'=>'etf',   'color'=>'yellow','iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'SLV',  'label'=>'Silver ETF',     'ticker'=>'SLV',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'ARKK', 'label'=>'ARK Innovation', 'ticker'=>'ARKK', 'type'=>'etf',   'color'=>'indigo','iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'TLT',  'label'=>'Bonos 20Y ETF',  'ticker'=>'TLT',  'type'=>'etf',   'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'ITA',  'label'=>'iShares Italy',  'ticker'=>'ITA',  'type'=>'etf',   'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'USO',  'label'=>'Petroleo ETF',   'ticker'=>'USO',  'type'=>'etf',   'color'=>'amber', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'AAPL', 'label'=>'Apple',          'ticker'=>'AAPL', 'type'=>'stock', 'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'MSFT', 'label'=>'Microsoft',      'ticker'=>'MS',   'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'NVDA', 'label'=>'NVIDIA',         'ticker'=>'NV',   'type'=>'stock', 'color'=>'green', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'TSLA', 'label'=>'Tesla',          'ticker'=>'T',    'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'AMZN', 'label'=>'Amazon',         'ticker'=>'az',   'type'=>'stock', 'color'=>'amber', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'GOOGL','label'=>'Alphabet',       'ticker'=>'G',    'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'META', 'label'=>'Meta',           'ticker'=>'f',    'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'NFLX', 'label'=>'Netflix',        'ticker'=>'N',    'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'AMD',  'label'=>'AMD',            'ticker'=>'AMD',  'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'INTC', 'label'=>'Intel',          'ticker'=>'INT',  'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'ORCL', 'label'=>'Oracle',         'ticker'=>'OR',   'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'CRM',  'label'=>'Salesforce',     'ticker'=>'CRM',  'type'=>'stock', 'color'=>'sky',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'ADBE', 'label'=>'Adobe',          'ticker'=>'AD',   'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'PYPL', 'label'=>'PayPal',         'ticker'=>'PP',   'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'SHOP', 'label'=>'Shopify',        'ticker'=>'SH',   'type'=>'stock', 'color'=>'green', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'COIN', 'label'=>'Coinbase',       'ticker'=>'C',    'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'MSTR', 'label'=>'MicroStrategy',  'ticker'=>'MS',   'type'=>'stock', 'color'=>'orange','iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'JPM',  'label'=>'JPMorgan',       'ticker'=>'JPM',  'type'=>'stock', 'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'BAC',  'label'=>'Bank of America','ticker'=>'BAC',  'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'GS',   'label'=>'Goldman Sachs',  'ticker'=>'GS',   'type'=>'stock', 'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'V',    'label'=>'Visa',           'ticker'=>'V',    'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'MA',   'label'=>'Mastercard',     'ticker'=>'MA',   'type'=>'stock', 'color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'DIS',  'label'=>'Disney',         'ticker'=>'DIS',  'type'=>'stock', 'color'=>'blue',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'UBER', 'label'=>'Uber',           'ticker'=>'U',    'type'=>'stock', 'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'ABNB', 'label'=>'Airbnb',         'ticker'=>'AB',   'type'=>'stock', 'color'=>'pink',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'HIMS', 'label'=>'Hims & Hers',    'ticker'=>'HI',   'type'=>'stock', 'color'=>'teal',  'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'TMC',  'label'=>'TMC',            'ticker'=>'TMC',  'type'=>'stock', 'color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'EURUSD=X','label'=>'EUR/USD','ticker'=>'EUR','type'=>'forex','color'=>'sky',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'GBPUSD=X','label'=>'GBP/USD','ticker'=>'GBP','type'=>'forex','color'=>'indigo','iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'USDJPY=X','label'=>'USD/JPY','ticker'=>'JPY','type'=>'forex','color'=>'red',   'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'GC=F','label'=>'Oro (Gold)',    'ticker'=>'XAU','type'=>'future','color'=>'yellow','iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'SI=F','label'=>'Plata (Silver)','ticker'=>'XAG','type'=>'future','color'=>'slate', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'CL=F','label'=>'Crudo WTI',    'ticker'=>'WTI','type'=>'future','color'=>'amber', 'iconType'=>'svg','iconUrl'=>''],
            ['symbol'=>'BZ=F','label'=>'Crudo Brent',  'ticker'=>'BRT','type'=>'future','color'=>'amber', 'iconType'=>'svg','iconUrl'=>''],
        ];
    }

    public static function init(): void {
        add_action('admin_menu',             [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts',  [self::class, 'admin_assets']);
        add_action('wp_ajax_lmt_save_dashboard', [self::class, 'ajax_save']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'Live Market Ticker',
            '📈 Live Ticker',
            'manage_options',
            'live-market-ticker',
            [self::class, 'render_page'],
            'dashicons-chart-line',
            56
        );
    }

    public static function admin_assets(string $hook): void {
        if (strpos($hook, 'live-market-ticker') === false) return;
        wp_enqueue_style('lmt-admin',  LMT_PLUGIN_URL . 'assets/admin.css',  [], LMT_VERSION);
        wp_enqueue_script('lmt-admin', LMT_PLUGIN_URL . 'assets/admin.js', [], LMT_VERSION, true);
        $data = self::get_dashboard_data();
        wp_localize_script('lmt-admin', 'LMT_Admin_Data', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('lmt_dashboard'),
            'assets'      => $data['assets'],
            'tickerOrder' => $data['tickerOrder'],
            'settings'    => $data['settings'],
        ]);
    }

    private static function get_dashboard_data(): array {
        $saved = get_option('lmt_dashboard', null);
        if ($saved === null) {
            $old = get_option('lmt_settings', []);
            $syms = array_filter(array_map('trim', explode(',', $old['symbols'] ?? 'BTC-USD,ETH-USD,SPY,QQQ,SOL-USD')));
            return [
                'assets'      => self::default_assets(),
                'tickerOrder' => $syms ?: ['BTC-USD','ETH-USD','SOL-USD','SPY','QQQ'],
                'settings'    => [
                    'refresh_rate'   => intval($old['refresh_rate'] ?? 60),
                    'show_sparkline' => (bool)($old['show_sparkline'] ?? true),
                    'ticker_speed'   => $old['ticker_speed'] ?? 'normal',
                ],
            ];
        }
        return $saved;
    }

    public static function ajax_save(): void {
        if (!check_ajax_referer('lmt_dashboard', 'nonce', false)) { wp_send_json_error('Nonce invalido'); }
        if (!current_user_can('manage_options'))                   { wp_send_json_error('Sin permisos');   }

        $assets   = json_decode(stripslashes($_POST['assets']   ?? '[]'), true) ?: [];
        $order    = json_decode(stripslashes($_POST['order']    ?? '[]'), true) ?: [];
        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true) ?: [];

        $clean_assets = array_map(fn($a) => [
            'symbol'   => sanitize_text_field($a['symbol']   ?? ''),
            'label'    => sanitize_text_field($a['label']    ?? ''),
            'ticker'   => sanitize_text_field($a['ticker']   ?? ''),
            'type'     => sanitize_text_field($a['type']     ?? 'stock'),
            'color'    => sanitize_text_field($a['color']    ?? 'slate'),
            'iconType' => sanitize_text_field($a['iconType'] ?? 'svg'),
            'iconUrl'  => esc_url_raw($a['iconUrl']          ?? ''),
        ], $assets);

        $clean_settings = [
            'refresh_rate'   => max(30, min(3600, intval($settings['refresh_rate'] ?? 60))),
            'show_sparkline' => (bool)($settings['show_sparkline'] ?? true),
            'ticker_speed'   => in_array($settings['ticker_speed'] ?? '', ['slow','normal','fast'])
                                    ? $settings['ticker_speed'] : 'normal',
        ];

        $data = ['assets' => $clean_assets, 'tickerOrder' => array_map('sanitize_text_field', $order), 'settings' => $clean_settings];
        update_option('lmt_dashboard', $data);
        update_option('lmt_settings', [
            'symbols'        => implode(',', $data['tickerOrder']),
            'refresh_rate'   => $clean_settings['refresh_rate'],
            'show_sparkline' => $clean_settings['show_sparkline'],
            'ticker_speed'   => $clean_settings['ticker_speed'],
        ]);
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lmt_%'");
        wp_send_json_success(['saved' => true]);
    }

    public static function render_page(): void { ?>
        <div id="lmt-admin">
            <div class="lmt-admin-header">
                <h1><span>📈</span> Live Market Ticker <span class="lmt-version-badge">v<?php echo LMT_VERSION; ?></span></h1>
                <button class="lmt-btn lmt-btn-primary" id="lmt-save-btn" style="opacity:0.6">
                    <span class="dashicons dashicons-saved"></span> Guardar cambios
                </button>
            </div>
            <div id="lmt-notice-area"></div>
            <div class="lmt-tabs">
                <button class="lmt-tab active" data-tab="assets">🗂 Activos</button>
                <button class="lmt-tab" data-tab="settings">⚙️ Configuración</button>
                <button class="lmt-tab" data-tab="shortcode">🔗 Shortcode</button>
            </div>

            <!-- Panel Activos -->
            <div class="lmt-panel active" id="panel-assets">
                <div class="lmt-stats-row" id="lmt-stats-row"></div>
                <div class="lmt-card">
                    <h2 class="lmt-card-title"><span class="dashicons dashicons-sort"></span> Ticker en vivo — Orden y activos activos</h2>
                    <p style="font-size:0.82rem;color:#64748b;margin:0 0 10px">Arrastra los chips para reordenar. Haz clic en <b>×</b> para quitar un activo del ticker.</p>
                    <div class="lmt-ticker-order" id="lmt-ticker-strip"></div>
                </div>
                <div class="lmt-card">
                    <h2 class="lmt-card-title"><span class="dashicons dashicons-list-view"></span> Todos los activos</h2>
                    <div class="lmt-toolbar">
                        <div class="lmt-search-box">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" id="lmt-search" placeholder="Buscar por símbolo, nombre o ticker…">
                        </div>
                        <div class="lmt-filter-tabs">
                            <button class="lmt-filter-btn active" data-filter="all">Todos</button>
                            <button class="lmt-filter-btn" data-filter="crypto">Cripto</button>
                            <button class="lmt-filter-btn" data-filter="etf">ETFs</button>
                            <button class="lmt-filter-btn" data-filter="stock">Acciones</button>
                            <button class="lmt-filter-btn" data-filter="forex">Forex</button>
                            <button class="lmt-filter-btn" data-filter="future">Futuros</button>
                        </div>
                        <button class="lmt-btn lmt-btn-success" id="lmt-add-btn">
                            <span class="dashicons dashicons-plus-alt2"></span> Añadir activo
                        </button>
                    </div>
                    <div class="lmt-asset-grid" id="lmt-asset-grid"></div>
                    <div class="lmt-save-bar">
                        <span class="lmt-save-status" id="lmt-save-status">Sin cambios pendientes</span>
                        <button class="lmt-btn lmt-btn-primary" onclick="document.getElementById('lmt-save-btn').click()">
                            <span class="dashicons dashicons-saved"></span> Guardar cambios
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel Configuración -->
            <div class="lmt-panel" id="panel-settings">
                <div class="lmt-card">
                    <h2 class="lmt-card-title"><span class="dashicons dashicons-admin-settings"></span> Configuración general</h2>
                    <div class="lmt-setting-row">
                        <div class="lmt-setting-info"><strong>Velocidad de scroll</strong><small>Rapidez con la que se desplazan los activos</small></div>
                        <div class="lmt-setting-control">
                            <div class="lmt-speed-group">
                                <button class="lmt-speed-btn" data-speed="slow">Lento</button>
                                <button class="lmt-speed-btn active" data-speed="normal">Normal</button>
                                <button class="lmt-speed-btn" data-speed="fast">Rápido</button>
                            </div>
                        </div>
                    </div>
                    <div class="lmt-setting-row">
                        <div class="lmt-setting-info"><strong>Intervalo de actualización</strong><small>Cada cuántos segundos se consultan los precios (mínimo 30s)</small></div>
                        <div class="lmt-setting-control">
                            <input type="number" id="lmt-refresh-input" min="30" max="3600" value="60" style="width:80px;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:0.875rem">
                            <span style="font-size:0.8rem;color:#64748b;margin-left:4px">segundos</span>
                        </div>
                    </div>
                    <div class="lmt-setting-row">
                        <div class="lmt-setting-info"><strong>Mostrar gráfico sparkline</strong><small>Mini gráfico de tendencia junto a cada activo</small></div>
                        <div class="lmt-setting-control">
                            <label class="lmt-switch"><input type="checkbox" id="lmt-sparkline-toggle" checked><span class="lmt-switch-slider"></span></label>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <button class="lmt-btn lmt-btn-primary" onclick="document.getElementById('lmt-save-btn').click()">
                            <span class="dashicons dashicons-saved"></span> Guardar configuración
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel Shortcode -->
            <div class="lmt-panel" id="panel-shortcode">
                <div class="lmt-card">
                    <h2 class="lmt-card-title"><span class="dashicons dashicons-shortcode"></span> Usar el ticker en tu web</h2>
                    <p style="color:#475569;margin-bottom:16px">Copia el shortcode y pégalo en cualquier página, entrada o widget de WordPress.</p>
                    <strong style="font-size:0.85rem;color:#374151">Shortcode con tu configuración actual:</strong>
                    <div class="lmt-shortcode-box">
                        <span id="lmt-shortcode-value">[live_ticker]</span>
                        <button class="lmt-copy-btn" id="lmt-copy-shortcode">Copiar</button>
                    </div>
                    <hr style="border:none;border-top:1px solid #f1f5f9;margin:24px 0">
                    <h3 style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-bottom:10px">Parámetros disponibles</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem">
                        <thead><tr style="background:#f8fafc">
                            <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Parámetro</th>
                            <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Valores</th>
                            <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Descripción</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ([
                                ['symbols','BTC-USD,ETH-USD,SPY','Lista de símbolos separados por coma'],
                                ['refresh','30–3600 (segundos)','Intervalo de actualización'],
                                ['speed','slow / normal / fast','Velocidad de scroll'],
                                ['sparkline','true / false','Mostrar mini gráfico'],
                            ] as [$p,$v,$d]): ?>
                            <tr>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0"><code><?php echo $p; ?></code></td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0;color:#64748b"><?php echo $v; ?></td>
                                <td style="padding:8px 12px;border:1px solid #e2e8f0"><?php echo $d; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php }
}
