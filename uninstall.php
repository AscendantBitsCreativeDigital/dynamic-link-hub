<?php
/**
 * Fires when the plugin is deleted (not just deactivated) from the Plugins
 * screen, so we can clean up the option we created.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dlh_settings' );
