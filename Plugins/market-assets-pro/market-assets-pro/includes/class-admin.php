<?php
/**
 * Panel de administración de Market Assets Pro
 */
class MAP_Admin {

    public static function init(): void {
        add_action('admin_menu',       [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_map_save_settings', [self::class, 'save_settings']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'Market Assets Pro',
            'Market Assets',
            'manage_options',
            'market-assets-pro',
            [self::class, 'render_page'],
            'dashicons-chart-line',
            56
        );
    }

    public static function enqueue(string $hook): void {
        if ($hook !== 'toplevel_page_market-assets-pro') return;
        wp_enqueue_style(
            'map-admin',
            MAP_PLUGIN_URL . 'assets/admin.css',
            [],
            MAP_VERSION
        );
    }

    public static function save_settings(): void {
        check_ajax_referer('map_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);

        $assets = json_decode(stripslashes($_POST['assets'] ?? '[]'), true);
        if (!is_array($assets)) $assets = [];

        // Sanitizar
        $clean = [];
        foreach ($assets as $a) {
            $sym = strtoupper(sanitize_text_field($a['symbol'] ?? ''));
            if (!$sym) continue;
            $clean[] = [
                'symbol'  => $sym,
                'label'   => sanitize_text_field($a['label']   ?? $sym),
                'color'   => sanitize_text_field($a['color']   ?? 'blue'),
                'icon_url'=> esc_url_raw($a['icon_url'] ?? ''),
            ];
        }

        update_option('map_assets', $clean);
        wp_send_json_success(['saved' => count($clean)]);
    }

    public static function render_page(): void {
        $assets  = get_option('map_assets', []);
        $nonce   = wp_create_nonce('map_admin_nonce');
        $api_url = rest_url('map/v1/');
        ?>
        <div class="wrap">
            <h1>⚡ Market Assets Pro — Configuración</h1>

            <div class="map-admin-grid">
                <!-- Panel izquierdo: gestión de activos globales -->
                <div class="map-card">
                    <h2>Activos Globales</h2>
                    <p class="description">Estos activos aparecen en la lista para todos los visitantes. Los usuarios registrados pueden añadir sus propios activos adicionales.</p>

                    <div id="map-asset-list">
                        <?php foreach ($assets as $i => $a): ?>
                        <div class="map-asset-row" data-index="<?= $i ?>">
                            <span class="map-badge map-color-<?= esc_attr($a['color']) ?>"><?= esc_html($a['symbol']) ?></span>
                            <input type="text" name="label" value="<?= esc_attr($a['label']) ?>" placeholder="Nombre" />
                            <select name="color">
                                <?php foreach (['blue','orange','purple','green','yellow','amber','teal','indigo','slate','red','pink'] as $c): ?>
                                <option value="<?= $c ?>" <?= selected($a['color'], $c, false) ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button map-remove-asset">✕</button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="map-add-row">
                        <input type="text" id="map-new-symbol"   placeholder="Ticker (BTC-USD, SPY…)" style="text-transform:uppercase" />
                        <input type="text" id="map-new-label"    placeholder="Nombre" />
                        <select id="map-new-color">
                            <?php foreach (['blue','orange','purple','green','yellow','amber','teal','indigo','slate','red','pink'] as $c): ?>
                            <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-secondary" id="map-add-asset">+ Añadir</button>
                    </div>

                    <hr />
                    <button class="button button-primary" id="map-save-btn">💾 Guardar cambios</button>
                    <span id="map-save-msg" style="margin-left:12px;color:green;display:none">✓ Guardado</span>
                </div>

                <!-- Panel derecho: instrucciones -->
                <div class="map-card">
                    <h2>Cómo usar el plugin</h2>
                    <h3>Shortcode principal</h3>
                    <code>[market_assets_list]</code>
                    <p>Muestra la tabla completa de activos con gráficos y predicciones.</p>

                    <h3>Opciones</h3>
                    <table class="wp-list-table widefat">
                        <tr><th>Parámetro</th><th>Valor</th><th>Descripción</th></tr>
                        <tr><td><code>show_search</code></td><td>true/false</td><td>Barra de búsqueda</td></tr>
                        <tr><td><code>show_add_btn</code></td><td>true/false</td><td>Botón añadir para usuarios</td></tr>
                        <tr><td><code>items_per_page</code></td><td>número</td><td>Filas por página (def: 10)</td></tr>
                        <tr><td><code>default_filter</code></td><td>all/crypto/etf/stock</td><td>Filtro inicial</td></tr>
                    </table>

                    <h3>Ejemplo completo</h3>
                    <code>[market_assets_list show_search="true" show_add_btn="true" items_per_page="10"]</code>

                    <h3>Endpoints REST disponibles</h3>
                    <ul>
                        <li><code>GET <?= esc_html($api_url) ?>assets</code></li>
                        <li><code>GET <?= esc_html($api_url) ?>quote/{symbol}</code></li>
                        <li><code>GET <?= esc_html($api_url) ?>history/{symbol}?period=1y</code></li>
                        <li><code>GET <?= esc_html($api_url) ?>predict/{symbol}?days=5</code></li>
                        <li><code>GET <?= esc_html($api_url) ?>search?q=bitcoin</code></li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
        (function($){
            const nonce   = '<?= $nonce ?>';
            const ajaxUrl = '<?= admin_url('admin-ajax.php') ?>';

            // Leer estado actual del DOM
            function collectAssets() {
                const assets = [];
                $('#map-asset-list .map-asset-row').each(function(){
                    assets.push({
                        symbol:   $(this).find('.map-badge').text().trim(),
                        label:    $(this).find('[name=label]').val(),
                        color:    $(this).find('[name=color]').val(),
                        icon_url: '',
                    });
                });
                return assets;
            }

            // Añadir activo
            $('#map-add-asset').on('click', function(){
                const sym = $('#map-new-symbol').val().trim().toUpperCase();
                const lbl = $('#map-new-label').val().trim() || sym;
                const col = $('#map-new-color').val();
                if (!sym) return alert('Introduce un ticker.');

                const idx = $('#map-asset-list .map-asset-row').length;
                const row = `<div class="map-asset-row" data-index="${idx}">
                    <span class="map-badge map-color-${col}">${sym}</span>
                    <input type="text" name="label" value="${lbl}" placeholder="Nombre" />
                    <select name="color">
                        <?php foreach (['blue','orange','purple','green','yellow','amber','teal','indigo','slate','red','pink'] as $c): ?>
                        <option value="<?= $c ?>" ${col==='<?= $c ?>'?'selected':''}><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button map-remove-asset">✕</button>
                </div>`;
                $('#map-asset-list').append(row);
                $('#map-new-symbol,#map-new-label').val('');
            });

            // Eliminar fila
            $(document).on('click', '.map-remove-asset', function(){
                $(this).closest('.map-asset-row').remove();
            });

            // Guardar
            $('#map-save-btn').on('click', function(){
                $(this).prop('disabled', true).text('Guardando…');
                $.post(ajaxUrl, {
                    action: 'map_save_settings',
                    nonce:  nonce,
                    assets: JSON.stringify(collectAssets()),
                }, function(res){
                    if (res.success) {
                        $('#map-save-msg').fadeIn().delay(3000).fadeOut();
                    } else {
                        alert('Error al guardar.');
                    }
                }).always(function(){
                    $('#map-save-btn').prop('disabled', false).text('💾 Guardar cambios');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
