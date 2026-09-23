<?php
/**
 * Plugin Name:       Dynamic Link Hub
 * Plugin URI:        https://ascendantbits.com/
 * Description:       A configurable Linktree-style button hub shortcode. Always links your most recent post, plus editable custom link buttons and an optional social menu. Use the [dynamic_link_hub] shortcode anywhere.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ascendant Bits
 * Author URI:        https://ascendantbits.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dynamic-link-hub
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Constants ---
define( 'DLH_VERSION', '1.2.1' );
define( 'DLH_PLUGIN_FILE', __FILE__ );
define( 'DLH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DLH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DLH_OPTION_KEY', 'dlh_settings' );

/**
 * Default settings. Kept in one place so admin + shortcode always agree
 * on what a "complete" settings array looks like.
 *
 * @return array
 */
function dlh_get_default_settings() {
	return array(
		'button_bg'          => '#7E00B8',
		'button_text'        => '#ffffff',
		'button_bg_hover'    => '#FEB400',
		'button_text_hover'  => '#000000',
		'button_radius'      => 8,
		'show_recent_post'   => 1,
		'recent_post_label'  => 'My Latest Post',
		'links'              => array(),
		'social_links'       => array(),
	);
}

/**
 * Get plugin settings merged with defaults, so new options added in later
 * versions always have a sane fallback for existing installs.
 *
 * @return array
 */
function dlh_get_settings() {
	$saved = get_option( DLH_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, dlh_get_default_settings() );
}

// --- Includes ---
require_once DLH_PLUGIN_DIR . 'includes/class-dlh-admin.php';
require_once DLH_PLUGIN_DIR . 'includes/class-dlh-icons.php';
require_once DLH_PLUGIN_DIR . 'includes/class-dlh-shortcode.php';

/**
 * Boot the plugin.
 */
function dlh_init() {
	load_plugin_textdomain( 'dynamic-link-hub', false, dirname( plugin_basename( DLH_PLUGIN_FILE ) ) . '/languages' );

	new DLH_Admin();
	new DLH_Shortcode();
}
add_action( 'plugins_loaded', 'dlh_init' );

/**
 * On activation, seed the option with defaults if it doesn't exist yet
 * (does not overwrite an existing config on re-activation).
 */
function dlh_activate() {
	if ( false === get_option( DLH_OPTION_KEY, false ) ) {
		add_option( DLH_OPTION_KEY, dlh_get_default_settings() );
	}
}
register_activation_hook( DLH_PLUGIN_FILE, 'dlh_activate' );
