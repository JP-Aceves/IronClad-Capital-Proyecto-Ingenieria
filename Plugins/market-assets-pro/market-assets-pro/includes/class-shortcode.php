<?php
/**
 * Shortcode [market_assets_list] v8 — Español, nuevos períodos
 */
class MAP_Shortcode {

    private static bool $scripts_printed = false;

    public static function init(): void {
        add_shortcode('market_assets_list', [self::class, 'render']);
    }

    /**
     * Genera un token de sesión determinista para autenticar al usuario en REST.
     *
     * A diferencia de wp_create_nonce(), este token no depende de wp_nonce_tick()
     * (que cambia cada 12h y puede diferir entre el contexto de página y REST).
     * Usa wp_hash() con el user_id + session_token de WordPress + AUTH_SALT,
     * por lo que es único por usuario y sesión, y no expira hasta que el usuario
     * cierra sesión o cambia contraseña.
     */
    public static function make_session_token(int $uid): string {
        if (!$uid) return '';
        // Token determinista basado en datos que NO cambian entre contextos:
        // user_pass (hash de contraseña) + AUTH_SALT (constante de wp-config.php)
        // Esto produce siempre el mismo valor en contexto de página y en REST.
        $user = get_userdata($uid);
        if (!$user) return '';
        return wp_hash($uid . '|' . $user->user_pass . '|map_auth_v2', 'auth');
    }

    public static function render(array $atts): string {
        // Forzar que WordPress cargue la sesión del usuario actual.
        // En temas que no llaman wp_head(), la cookie puede no haberse procesado aún.
        if ( ! did_action('init') || get_current_user_id() === 0 ) {
            $cookie = '';
            foreach ($_COOKIE as $k => $v) {
                if (strpos($k, 'wordpress_logged_in_') === 0) { $cookie = $v; break; }
            }
            if ($cookie) {
                $uid = wp_validate_auth_cookie($cookie, 'logged_in');
                if ($uid) wp_set_current_user($uid);
            }
        }
        $atts = shortcode_atts([
            'show_add_btn'   => 'true',
            'items_per_page' => '10',
        ], $atts, 'market_assets_list');

        $uid     = get_current_user_id();
        $config  = json_encode([
            'api_url'      => rest_url('map/v1/'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'is_logged_in' => is_user_logged_in(),
            'user_id'      => $uid,
            // Token determinista: no usa wp_nonce_tick() así que funciona igual
            // en contexto de página y en contexto REST (donde el tick puede diferir).
            'map_nonce'    => self::make_session_token($uid),
        ]);
        $purl = MAP_PLUGIN_URL;
        $ver  = MAP_VERSION;

        $kpis = [
            ['md-vol',   'Volumen'],
            ['md-rsi',   'RSI 14'],
            ['md-ma7',   'MA 7d'],
            ['md-ma21',  'MA 21d'],
            ['md-vp',    'Volatilidad'],
            ['md-signal','Señal'],
        ];
        $swatches = [
            'blue'=>'#3b82f6','orange'=>'#f97316','purple'=>'#a855f7',
            'green'=>'#22c55e','yellow'=>'#eab308','amber'=>'#f59e0b',
            'teal'=>'#14b8a6','indigo'=>'#6366f1','slate'=>'#64748b',
            'red'=>'#ef4444','pink'=>'#ec4899',
        ];
        // Períodos: valor interno → etiqueta
        $periods = [
            '1d'  => '1D',
            '1wk' => '1S',
            '1mo' => '1M',
            '6mo' => '6M',
            '1y'  => '1A',
            '2y'  => '2A',
            '5y'  => '5A',
        ];

        ob_start(); ?>

<?php if (!self::$scripts_printed): self::$scripts_printed = true; ?>
<link rel="stylesheet" href="<?= esc_url($purl.'assets/map-assets.css?v='.$ver) ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="<?= esc_url($purl.'assets/map-assets.js?v='.$ver) ?>"></script>
<?php endif; ?>

<script>window.MAP_Config = <?= $config ?>;</script>

<div id="map-app"
     data-per-page="<?= esc_attr($atts['items_per_page']) ?>"
     data-show-add="<?= esc_attr($atts['show_add_btn']) ?>">

    <!-- TOOLBAR -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
        <div class="relative w-full lg:w-96">
            <div class="flex items-center w-full h-11 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/50 focus-within:ring-2 focus-within:ring-primary/50">
                <span class="material-symbols-outlined text-slate-400 pl-3 text-[20px] select-none">search</span>
                <input id="map-search" type="text" autocomplete="off"
                       placeholder="Buscar activo por nombre o ticker..."
                       class="w-full bg-transparent border-none text-slate-900 dark:text-white text-sm px-3 focus:outline-none focus:ring-0 placeholder:text-slate-400" />
            </div>
            <div id="map-autocomplete"
                 style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:999;
                        background:#0f172a;border:1px solid rgba(148,163,184,.2);border-radius:12px;
                        box-shadow:0 20px 40px rgba(0,0,0,.4);overflow:hidden;"></div>
        </div>
        <div class="flex items-center gap-3 w-full lg:w-auto">
            <div class="flex gap-2 overflow-x-auto flex-1 lg:flex-none">
                <button data-filter="all"       class="map-filter-btn flex h-9 shrink-0 items-center justify-center px-4 rounded-full text-sm font-medium transition-all bg-slate-100 text-slate-900 dark:bg-white dark:text-slate-900">Todos</button>
                <button data-filter="crypto"    class="map-filter-btn flex h-9 shrink-0 items-center justify-center px-4 rounded-full text-sm font-medium transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Crypto</button>
                <button data-filter="etf"       class="map-filter-btn flex h-9 shrink-0 items-center justify-center px-4 rounded-full text-sm font-medium transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">ETFs</button>
                <button data-filter="stock"     class="map-filter-btn flex h-9 shrink-0 items-center justify-center px-4 rounded-full text-sm font-medium transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Stocks</button>
                <button data-filter="watchlist" class="map-filter-btn flex h-9 shrink-0 items-center justify-center px-4 rounded-full text-sm font-medium transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Mi lista</button>
            </div>
            <?php if ($atts['show_add_btn'] === 'true'): ?>
            <button id="map-open-add" class="flex items-center justify-center gap-1.5 h-10 px-4 rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors shrink-0 text-sm font-medium">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span class="hidden sm:inline">Añadir activo</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLE -->
    <div class="w-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-[#15202b] shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[820px]">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Activo</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Precio</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Cambio 24H</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Últimos 7D</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Sentimiento</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Señal</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Acción</th>
                    </tr>
                </thead>
                <tbody id="map-tbody" class="divide-y divide-slate-200 dark:divide-slate-800"></tbody>
            </table>
        </div>
        <div class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/30">
            <p id="map-showing" class="text-xs text-slate-500 dark:text-slate-400"></p>
            <div id="map-pages" class="flex gap-1 items-center"></div>
        </div>
    </div>

    <!-- MODAL DETALLE -->
    <div id="map-detail-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);align-items:center;justify-content:center;padding:16px;">
        <div style="position:relative;background:#0f172a;border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.6);width:100%;max-width:900px;max-height:92vh;overflow-y:auto;border:1px solid rgba(148,163,184,.15);">
            <div style="padding:28px;">
                <button id="map-modal-close" style="position:absolute;top:16px;right:16px;z-index:10;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);cursor:pointer;color:#64748b;" onmouseover="this.style.color='#f1f5f9'" onmouseout="this.style.color='#64748b'">
                    <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                </button>
                <!-- Header -->
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-right:44px;">
                    <div id="md-icon" style="flex-shrink:0;"></div>
                    <div style="flex:1;min-width:0;">
                        <h2 id="md-name"   style="font-size:20px;font-weight:700;color:#f1f5f9;margin:0 0 4px;"></h2>
                        <span id="md-symbol" style="font-size:12px;font-weight:600;color:#64748b;background:rgba(148,163,184,.1);padding:2px 8px;border-radius:6px;"></span>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div id="md-price"  style="font-size:24px;font-weight:700;color:#f1f5f9;"></div>
                        <div id="md-change" style="font-size:14px;font-weight:600;margin-top:2px;"></div>
                    </div>
                </div>
                <!-- KPIs -->
                <div class="map-kpis-grid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:20px;">
                    <?php foreach ($kpis as [$id, $lbl]): ?>
                    <div style="background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.1);border-radius:12px;padding:12px;text-align:center;">
                        <p style="font-size:10px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin:0 0 4px;"><?= $lbl ?></p>
                        <p id="<?= $id ?>" style="font-size:13px;font-weight:700;color:#f1f5f9;margin:0;">—</p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Controles gráfico -->
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php foreach ($periods as $val => $lbl): ?>
                        <button class="map-period-btn" data-period="<?= $val ?>"
                                style="padding:5px 12px;font-size:12px;font-weight:500;border-radius:8px;cursor:pointer;transition:all .15s;
                                       background:<?= $val==='6mo'?'#3b82f6':'transparent' ?>;
                                       color:<?= $val==='6mo'?'#fff':'#64748b' ?>;
                                       border:1px solid <?= $val==='6mo'?'#3b82f6':'rgba(148,163,184,.2)' ?>;">
                            <?= $lbl ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:4px;">
                        <button class="map-type-btn" data-type="line"   style="padding:5px 12px;font-size:12px;font-weight:500;border-radius:8px;cursor:pointer;background:#3b82f6;color:#fff;border:1px solid #3b82f6;transition:all .15s;">Línea</button>
                        <button class="map-type-btn" data-type="candle" style="padding:5px 12px;font-size:12px;font-weight:500;border-radius:8px;cursor:pointer;background:transparent;color:#64748b;border:1px solid rgba(148,163,184,.2);transition:all .15s;">Velas</button>
                    </div>
                </div>
                <!-- Canvas -->
                <div style="position:relative;height:300px;background:rgba(148,163,184,.04);border-radius:12px;overflow:hidden;margin-bottom:24px;">
                    <canvas id="md-chart" style="position:absolute;inset:0;width:100%;height:100%;"></canvas>
                    <div id="md-chart-loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:8px;color:#64748b;font-size:13px;">
                        <span class="material-symbols-outlined map-spin" style="font-size:20px;">refresh</span>Cargando…
                    </div>
                </div>
                <!-- Predicción -->
                <div style="border-top:1px solid rgba(148,163,184,.1);padding-top:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                        <h3 style="font-size:14px;font-weight:600;color:#f1f5f9;margin:0;display:flex;align-items:center;gap:8px;">
                            <span class="material-symbols-outlined" style="font-size:20px;color:#3b82f6;">psychology</span>
                            Predicción de precio (XGBoost)
                        </h3>
                        <div style="display:flex;gap:4px;">
                            <?php foreach ([1,5,10,15] as $d): ?>
                            <button class="map-pred-btn" data-days="<?= $d ?>"
                                    style="padding:5px 12px;font-size:12px;font-weight:500;border-radius:8px;cursor:pointer;transition:all .15s;
                                           background:<?= $d===5?'#3b82f6':'transparent' ?>;
                                           color:<?= $d===5?'#fff':'#64748b' ?>;
                                           border:1px solid <?= $d===5?'#3b82f6':'rgba(148,163,184,.2)' ?>;">
                                +<?= $d ?>d
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="md-pred-content"><div style="color:#64748b;font-size:13px;padding:8px 0;">Listo…</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AÑADIR -->
    <div id="map-add-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);align-items:center;justify-content:center;padding:16px;">
        <div style="position:relative;background:#0f172a;border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.6);width:100%;max-width:440px;border:1px solid rgba(148,163,184,.15);padding:28px;">
            <button id="map-add-close" style="position:absolute;top:16px;right:16px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.2);cursor:pointer;color:#64748b;" onmouseover="this.style.color='#f1f5f9'" onmouseout="this.style.color='#64748b'">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
            <h2 style="font-size:18px;font-weight:700;color:#f1f5f9;margin:0 0 20px;">Añadir activo</h2>
            <label style="display:block;font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Buscar ticker</label>
            <div style="position:relative;margin-bottom:16px;">
                <div style="display:flex;align-items:center;height:44px;border-radius:10px;border:1px solid rgba(148,163,184,.25);background:rgba(148,163,184,.07);padding:0 12px;gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:18px;color:#64748b;">search</span>
                    <input id="map-add-search" type="text" autocomplete="off" placeholder="Bitcoin, ETH-USD, SPY…"
                           style="flex:1;background:transparent;border:none;outline:none;font-size:14px;color:#f1f5f9;"
                           onfocus="this.parentElement.style.borderColor='#3b82f6'"
                           onblur="this.parentElement.style.borderColor='rgba(148,163,184,.25)'" />
                </div>
                <div id="map-add-results" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:10;background:#0f172a;border:1px solid rgba(148,163,184,.2);border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.5);overflow:hidden;max-height:240px;overflow-y:auto;"></div>
            </div>
            <div id="map-add-selected" style="display:none;">
                <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(148,163,184,.06);border-radius:10px;padding:10px 14px;margin-bottom:16px;border:1px solid rgba(148,163,184,.12);">
                    <span id="map-sel-name" style="font-size:14px;font-weight:600;color:#f1f5f9;"></span>
                    <code  id="map-sel-sym"  style="font-size:12px;background:rgba(148,163,184,.12);padding:2px 8px;border-radius:6px;color:#94a3b8;"></code>
                </div>
                <label style="display:block;font-size:11px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Color</label>
                <div id="map-color-picker" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
                    <?php foreach ($swatches as $name => $hex): ?>
                    <button class="map-swatch" data-color="<?= $name ?>" style="width:28px;height:28px;border-radius:50%;background:<?= $hex ?>;border:3px solid transparent;cursor:pointer;transition:all .15s;" title="<?= $name ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button id="map-confirm-add" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;height:44px;border-radius:10px;background:#3b82f6;color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                    <span class="material-symbols-outlined" style="font-size:18px;">add_circle</span>Añadir a mi lista
                </button>
            </div>
        </div>
    </div>

</div><!-- #map-app -->

<style>
@keyframes map-spin-kf { to { transform:rotate(360deg); } }
.map-spin { display:inline-block; animation:map-spin-kf .8s linear infinite; }
@media(max-width:640px) { .map-kpis-grid { grid-template-columns:repeat(3,1fr) !important; } }
.map-row:hover td { background:rgba(148,163,184,.04); }
.map-view-btn:hover { background:#3b82f6 !important; color:#fff !important; border-color:#3b82f6 !important; }
</style>

        <?php
        return ob_get_clean();
    }
}
