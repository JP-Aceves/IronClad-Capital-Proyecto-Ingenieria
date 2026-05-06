<?php
/**
 * IronClad functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package IronClad
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.0.0' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function ironclad_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on IronClad, use a find and replace
		* to change 'ironclad' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'ironclad', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support( 'title-tag' );

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__( 'Primary', 'ironclad' ),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'ironclad_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action( 'after_setup_theme', 'ironclad_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function ironclad_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'ironclad_content_width', 640 );
}
add_action( 'after_setup_theme', 'ironclad_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function ironclad_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'ironclad' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'ironclad' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'ironclad_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function ironclad_scripts() {
	wp_enqueue_style( 'ironclad-style', get_stylesheet_uri(), array(), _S_VERSION );
	wp_style_add_data( 'ironclad-style', 'rtl', 'replace' );

	wp_enqueue_script( 'ironclad-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'ironclad_scripts' );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}



// Encolar Tailwind y Google Fonts
function tech_solutions_scripts() {
    // 1. Encolar tu archivo Tailwind local
    wp_enqueue_script( 
        'tailwind-js', 
        get_template_directory_uri() . '/js/tailwind.js', 
        array(), 
        '3.4.1', // Puedes poner la versión que descargaste
        false    // Se pone en el header porque Tailwind necesita procesar el DOM rápido
    );

    // 2. Encolar Google Fonts e Iconos (necesarios para el diseño de Stitch)
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&display=swap', array(), null);
    wp_enqueue_style('material-icons', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1', array(), null);
}
add_action( 'wp_enqueue_scripts', 'tech_solutions_scripts' );

// Eliminar la barra de administración en el frontend
add_filter('show_admin_bar', '__return_false');


// Función para obtener datos de Yahoo Finance
function get_ticker_data($symbol) {
    $cache_key = 'ticker_' . sanitize_title($symbol);
    $cached_data = get_transient($cache_key);
    if ($cached_data !== false) return $cached_data;

    $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols=" . urlencode($symbol);
    
    // Cabeceras más completas para "engañar" a Yahoo
    $args = array(
        'timeout'     => 15,
        'redirection' => 5,
        'httpversion' => '1.1',
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
        'headers'     => array(
            'Accept' => 'application/json',
        ),
        'sslverify'   => false // A veces el servidor falla al verificar el SSL de Yahoo
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) return null;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $result = $body['quoteResponse']['result'][0] ?? null;

    if ($result) {
        $data = [
            'price' => $result['regularMarketPrice'] ?? 0,
            'change' => $result['regularMarketChangePercent'] ?? 0,
            'name' => $result['shortName'] ?? $symbol
        ];
        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
        return $data;
    }
    return null;
}