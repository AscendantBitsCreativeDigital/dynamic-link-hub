<?php
/**
 * Fires when the plugin is deleted (not just deactivated) from the Plugins
 * screen, so we can clean up the option we created.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dlh_settings' );
