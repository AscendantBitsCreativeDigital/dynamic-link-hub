<?php
/**
 * Gutenberg block editor support for Dynamic Link Hub.
 *
 * Registers a dynamic "Link Hub" block (dynamic-link-hub/hub) that renders
 * through the same DLH_Shortcode::render() output as the [dynamic_link_hub]
 * shortcode, so the block, the shortcode, and the front end can never drift
 * apart. No build tooling is used for the editor script: it's plain JS
 * loaded as a manually registered, manually dependency-declared script,
 * matching the rest of this plugin's admin assets.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DLH_Block {

	const BLOCK_DIR = 'blocks/dynamic-link-hub';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		// register_block_type() reads block.json for everything except
		// render_callback, which block.json can't express without WP 6.1's
		// "render" field (this plugin supports WP 5.8+), so it's supplied
		// here instead.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'dlh-block-editor',
			DLH_PLUGIN_URL . self::BLOCK_DIR . '/index.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-server-side-render',
				'wp-components',
				'wp-i18n',
			),
			DLH_VERSION,
			true
		);

		wp_localize_script(
			'dlh-block-editor',
			'dlhBlockData',
			array(
				'settingsUrl' => admin_url( 'admin.php?page=dynamic-link-hub' ),
			)
		);

		register_block_type(
			DLH_PLUGIN_DIR . self::BLOCK_DIR,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render callback for both the editor's ServerSideRender preview and
	 * the front end. Reuses the single DLH_Shortcode instance created at
	 * boot rather than instantiating a second one (which would otherwise
	 * re-register the same shortcode tags redundantly).
	 *
	 * @param array $attributes Block attributes (unused — the block has no
	 *                          attributes of its own; everything comes from
	 *                          the plugin's one shared settings option).
	 * @return string
	 */
	public function render( $attributes ) {
		return DLH_Shortcode::instance()->render( array() );
	}
}
