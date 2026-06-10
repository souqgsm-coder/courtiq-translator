<?php
/**
 * Plugin Name: COURTIQ Translator Pro
 * Plugin URI: https://courtiq.com/translator
 * Description: 🌐 Moteur de traduction multilingue professionnel avec support Arabe, Anglais, Français. Utilise Google Gemini API + cache intelligent.
 * Version: 34.1
 * Author: COURTIQ Dev Team
 * Author URI: https://courtiq.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: courtiq-translator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Stable tag: 34.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CTQ_VERSION',      '34.1' );
define( 'CTQ_PLUGIN_PATH',  plugin_dir_path( __FILE__ ) );
define( 'CTQ_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );
define( 'CTQ_CACHE_DIR',    WP_CONTENT_DIR . '/ctq-cache' );
define( 'CTQ_BATCH_LIMIT',  30 );
define( 'CTQ_PAGE_LIMIT',   120 );
define( 'CTQ_GEMINI_MODEL', 'gemma-3-27b-it' );
define( 'CTQ_API_TIMEOUT',  45 );

if ( ! defined( 'CTQ_GEMINI_KEY' ) ) {
	define( 'CTQ_GEMINI_KEY', '__NOT_SET__' );
}

add_action( 'plugins_loaded', function() {
	load_plugin_textdomain(
		'courtiq-translator',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
} );

add_action( 'init', function() {
	if ( ! is_dir( CTQ_CACHE_DIR ) ) {
		wp_mkdir_p( CTQ_CACHE_DIR );
		file_put_contents(
			CTQ_CACHE_DIR . '/.htaccess',
			"Order deny,allow\nDeny from all\n"
		);
	}
} );

require_once CTQ_PLUGIN_PATH . 'includes/class-core.php';
require_once CTQ_PLUGIN_PATH . 'includes/class-ajax.php';
require_once CTQ_PLUGIN_PATH . 'includes/class-admin.php';
require_once CTQ_PLUGIN_PATH . 'includes/class-widget.php';
require_once CTQ_PLUGIN_PATH . 'includes/class-shortcode.php';
require_once CTQ_PLUGIN_PATH . 'includes/helpers.php';

add_action( 'plugins_loaded', function() {
	\COURTIQ\Core::get_instance();
	\COURTIQ\Admin::get_instance();
	\COURTIQ\Ajax::get_instance();
	\COURTIQ\Shortcode::get_instance();
}, 10 );

add_action( 'widgets_init', function() {
	register_widget( '\COURTIQ\Widget' );
} );

register_activation_hook( __FILE__, function() {
	\COURTIQ\Core::activate();
} );

register_deactivation_hook( __FILE__, function() {
	\COURTIQ\Core::deactivate();
} );

add_action( 'wp_enqueue_scripts', function() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'ctq-engine',
		CTQ_PLUGIN_URL . 'assets/js/ctq-engine.js',
		[],
		CTQ_VERSION,
		true
	);

	wp_localize_script( 'ctq-engine', 'CTQ_CONFIG', \COURTIQ\Helpers::get_frontend_config() );

	wp_enqueue_style(
		'ctq-rtl',
		CTQ_PLUGIN_URL . 'assets/css/ctq-rtl.css',
		[],
		CTQ_VERSION
	);

	wp_enqueue_style(
		'ctq-fonts-arabic',
		'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Tajawal:wght@400;500;700&display=swap',
		[],
		null
	);

	wp_add_inline_script( 'ctq-engine', "
	(function(){
		if((localStorage.getItem('courtiq_pref_lang')||'fr')!=='fr'){
			var s=document.createElement('style');
			s.id='ctq-body-hide';
			s.textContent='body{opacity:0!important;transition:opacity .4s ease}';
			document.head.appendChild(s);
		}
	})();
	", 'before' );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( strpos( $hook, 'courtiq' ) === false ) {
		return;
	}

	wp_enqueue_style(
		'ctq-admin',
		CTQ_PLUGIN_URL . 'assets/css/admin.css',
		[],
		CTQ_VERSION
	);

	wp_enqueue_script(
		'ctq-admin',
		CTQ_PLUGIN_URL . 'assets/js/admin.js',
		['jquery'],
		CTQ_VERSION,
		true
	);

	wp_localize_script( 'ctq-admin', 'CTQ_ADMIN', [
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'ctq_admin_nonce' ),
		'version'  => CTQ_VERSION,
	] );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$links['settings'] = '<a href="' . admin_url( 'admin.php?page=courtiq' ) . '">' . __( 'Paramètres', 'courtiq-translator' ) . '</a>';
	$links['support'] = '<a href="https://courtiq.com/support" target="_blank">' . __( 'Support', 'courtiq-translator' ) . '</a>';
	return $links;
} );
