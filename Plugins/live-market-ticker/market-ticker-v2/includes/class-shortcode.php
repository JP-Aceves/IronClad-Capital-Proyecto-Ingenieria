<?php
/**
 * Shortcode [live_ticker] para renderizar el ticker
 */
class LMT_Shortcode {

    public static function init(): void {
        add_shortcode('live_ticker', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style(
            'lmt-ticker',
            LMT_PLUGIN_URL . 'assets/ticker.css',
            [],
            LMT_VERSION
        );
        wp_enqueue_script(
            'lmt-ticker',
            LMT_PLUGIN_URL . 'assets/ticker.js',
            [],
            LMT_VERSION,
            true
        );
        wp_localize_script('lmt-ticker', 'LMT_Config', [
            'api_url' => rest_url('live-ticker/v1/quotes'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function render(array $atts): string {
        // Leer primero del nuevo dashboard, luego del antiguo lmt_settings
        $dash = get_option('lmt_dashboard', null);
        $opts = $dash ? $dash['settings'] : get_option('lmt_settings', []);
        $default_syms = $dash ? implode(',', $dash['tickerOrder']) : ($opts['symbols'] ?? 'BTC-USD,ETH-USD,SPY,QQQ,SOL-USD');

        $atts = shortcode_atts([
            'symbols'        => $default_syms,
            'refresh'        => $opts['refresh_rate']   ?? 60,
            'show_sparkline' => ($opts['show_sparkline'] ?? true) ? 'true' : 'false',
            'show_volume'    => ($opts['show_volume']    ?? false) ? 'true' : 'false',
            'speed'          => $opts['ticker_speed']   ?? 'normal',
        ], $atts, 'live_ticker');

        $uid = 'lmt-' . uniqid();

        ob_start(); ?>
        <section class="lmt-ticker-section" id="<?php echo esc_attr($uid); ?>"
                 data-symbols="<?php echo esc_attr($atts['symbols']); ?>"
                 data-refresh="<?php echo esc_attr($atts['refresh']); ?>"
                 data-sparkline="<?php echo esc_attr($atts['show_sparkline']); ?>"
                 data-volume="<?php echo esc_attr($atts['show_volume']); ?>"
                 data-speed="<?php echo esc_attr($atts['speed']); ?>">
            <div class="lmt-ticker-track" aria-live="polite" aria-label="Cotizaciones en tiempo real">
                <div class="lmt-ticker-inner lmt-loading">
                    <div class="lmt-skeleton"></div>
                    <div class="lmt-skeleton"></div>
                    <div class="lmt-skeleton"></div>
                    <div class="lmt-skeleton"></div>
                    <div class="lmt-skeleton"></div>
                </div>
            </div>

        </section>
        <?php
        return ob_get_clean();
    }
}
