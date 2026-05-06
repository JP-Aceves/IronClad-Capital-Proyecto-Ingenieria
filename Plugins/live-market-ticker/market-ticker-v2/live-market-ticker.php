<?php
/**
 * Plugin Name: Live Market Ticker
 * Description: Ticker en tiempo real de ETFs y criptomonedas via Yahoo Finance. Uso: [live_ticker symbols="BTC-USD,ETH-USD,SPY,QQQ,SOL-USD"]
 * Version: 2.0.0
 * Author: Tu Sitio
 */

if (!defined('ABSPATH')) exit;

define('LMT_VERSION', '2.0.0');
define('LMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LMT_PLUGIN_DIR', plugin_dir_path(__FILE__));

// ─── Cargar archivos ──────────────────────────────────────────────────────────
require_once LMT_PLUGIN_DIR . 'includes/class-yahoo-finance.php';
require_once LMT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once LMT_PLUGIN_DIR . 'includes/class-admin.php';
require_once LMT_PLUGIN_DIR . 'includes/class-shortcode.php';

// ─── Init ─────────────────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    LMT_REST_API::init();
    LMT_Admin::init();
    LMT_Shortcode::init();
});

// ─── Activación: crear tabla de caché ─────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table = $wpdb->prefix . 'lmt_cache';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        symbol VARCHAR(20) PRIMARY KEY,
        data LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});
