<?php
/**
 * Plugin Name: WP Markdown for AI
 * Plugin URI:  https://ajrwebdesign.com
 * Description: Exposes WordPress content as clean Markdown for AI agents via llms.txt, per-page endpoints, HTTP Link headers, and robots.txt integration.
 * Version:     0.2.0
 * Author:      AJR Web Design
 * Author URI:  https://ajrwebdesign.com
 * License:     GPL-2.0-or-later
 * Text Domain: wp-markdown-for-ai
 */

namespace AJR\MarkdownForAI;

defined( 'ABSPATH' ) || exit;

define( 'WPMAI_VERSION', '0.2.0' );
define( 'WPMAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMAI_TEXT_DOMAIN', 'wp-markdown-for-ai' );

require_once WPMAI_PLUGIN_DIR . 'includes/class-cache.php';
require_once WPMAI_PLUGIN_DIR . 'includes/class-indexability.php';
require_once WPMAI_PLUGIN_DIR . 'includes/class-markdown-converter.php';
require_once WPMAI_PLUGIN_DIR . 'includes/class-llms-txt.php';
require_once WPMAI_PLUGIN_DIR . 'includes/class-rewrite-rules.php';
require_once WPMAI_PLUGIN_DIR . 'includes/class-settings.php';

/**
 * Bootstraps the plugin by registering all component hooks.
 */
function boot(): void {
	( new Cache() )->register();
	( new Rewrite_Rules() )->register();
	( new Llms_Txt() )->register();
	( new Settings() )->register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\on_deactivate' );

function on_activate(): void {
	( new Rewrite_Rules() )->add_rules();
	( new Llms_Txt() )->add_rewrite_rules();
	flush_rewrite_rules();
}

function on_deactivate(): void {
	Cache::flush_all();
	flush_rewrite_rules();
}
