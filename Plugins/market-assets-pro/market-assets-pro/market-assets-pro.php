<?php
/**
 * Plugin Name: Market Assets Pro
 * Description: Lista avanzada de criptomonedas, stocks y ETFs con gráficos detallados y predicciones XGBoost.
 * Version: 1.1.3
 * Author: Tu Sitio
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('MAP_VERSION',    '1.1.3');
define('MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MAP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// ── AUTENTICACIÓN REST ────────────────────────────────────────────────────────
//
// Problema: este WordPress corre en IP directa (100.124.60.60:81) sin dominio.
// WordPress REST rechaza peticiones con 401 cuando no puede verificar la cookie
// porque el referer no coincide con siteurl.
//
// Solución A (determine_current_user): leer la cookie logged_in directamente.
// Solución B (rest_authentication_errors): para rutas /map/v1/ nunca bloquear.
// Ambas actúan juntas como doble seguro.

// Solución A: autenticar por cookie antes de que WordPress diga "no hay usuario"
add_filter('determine_current_user', function ($uid) {
    if ($uid || !defined('REST_REQUEST') || !REST_REQUEST) return $uid;
    foreach ($_COOKIE as $k => $v) {
        if (strpos($k, 'wordpress_logged_in_') === 0) {
            $validated = wp_validate_auth_cookie($v, 'logged_in');
            if ($validated) return (int) $validated;
            break;
        }
    }
    return $uid;
}, 10); // prioridad 10, antes del sistema por defecto de WP (20)

// Solución B: suprimir cualquier error de autenticación para nuestras rutas
add_filter('rest_authentication_errors', function ($result) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/map/v1/') !== false) {
        return null; // dejar pasar siempre — el callback gestiona la autorización
    }
    return $result;
}, 1); // prioridad 1 = ejecutar ANTES que cualquier otro plugin

// ── INCLUDES ──────────────────────────────────────────────────────────────────
require_once MAP_PLUGIN_DIR . 'includes/class-yahoo-finance.php';
require_once MAP_PLUGIN_DIR . 'includes/class-prediction.php';
require_once MAP_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once MAP_PLUGIN_DIR . 'includes/class-admin.php';
require_once MAP_PLUGIN_DIR . 'includes/class-shortcode.php';

add_action('plugins_loaded', function () {
    MAP_REST_API::init();
    MAP_Admin::init();
    MAP_Shortcode::init();
});

// ── ACTIVACIÓN ────────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}map_watchlists (
        id         BIGINT       NOT NULL AUTO_INCREMENT,
        user_id    BIGINT       NOT NULL,
        symbol     VARCHAR(20)  NOT NULL,
        label      VARCHAR(100) NOT NULL DEFAULT '',
        color      VARCHAR(30)  NOT NULL DEFAULT 'blue',
        icon_url   TEXT,
        added_at   DATETIME     NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_symbol (user_id, symbol)
    ) $charset;");
});
