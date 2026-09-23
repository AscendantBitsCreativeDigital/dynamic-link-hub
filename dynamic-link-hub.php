<?php
/**
 * Plugin Name:       Dynamic Link Hub
 * Plugin URI:        https://ascendantbits.com/
 * Description:       A configurable link-in-bio hub shortcode, rendered in its own width-configurable container with an optional round avatar image. Always links your most recent post, plus editable custom link buttons and an optional social menu. Use the [dynamic_link_hub] shortcode anywhere.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ascendant Bits Creative Digital
 * Author URI:        https://ascendantbits.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dynamic-link-hub
 *
 * Dynamic Link Hub is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 2 of the
 * License, or (at your option) any later version.
 *
 * Dynamic Link Hub is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Constants ---
define( 'DLH_VERSION', '1.4.0' );
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
		'button_bg'           => '#7E00B8',
		'button_text'         => '#ffffff',
		'button_bg_hover'     => '#FEB400',
		'button_text_hover'   => '#000000',
		'button_radius'       => 8,
		'container_width'     => 480,
		'avatar_id'           => 0,
		'avatar_url'          => '',
		'avatar_size'         => 140,
		'avatar_border_width' => 4,
		'avatar_border_color' => '#7E00B8',
		'show_recent_post'    => 1,
		'recent_post_label'   => 'My Latest Post',
		'links'               => array(),
		'social_links'        => array(),
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
 * This plugin isn't distributed through WordPress.org, so WordPress has no
 * built-in way to know a newer version exists. This wires up update checks
 * against the plugin's own public GitHub repository instead, using the
 * third-party Plugin Update Checker library (MIT licensed; bundled at
 * includes/plugin-update-checker/, not loaded from anywhere external).
 * Once wired up, "Update available" / "Update Now" behave exactly like an
 * ordinary WordPress.org plugin.
 *
 * @see https://github.com/YahnisElsts/plugin-update-checker
 */
function dlh_init_update_checker() {
	require_once DLH_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/AscendantBitsCreativeDigital/dynamic-link-hub/',
		DLH_PLUGIN_FILE,
		'dynamic-link-hub'
	);

	$update_checker->setBranch( 'main' );

	// Pull the update zip from a GitHub Release's attached asset (the same
	// build we hand out everywhere else) instead of GitHub's auto-generated
	// "source code" archive, which names its top-level folder
	// "dynamic-link-hub-<tag>" instead of "dynamic-link-hub".
	$update_checker->getVcsApi()->enableReleaseAssets();
}
add_action( 'plugins_loaded', 'dlh_init_update_checker' );

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
