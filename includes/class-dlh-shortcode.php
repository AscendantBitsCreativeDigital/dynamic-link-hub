<?php
/**
 * Shortcode rendering for Dynamic Link Hub.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DLH_Shortcode {

	/** @var DLH_Shortcode|null Shared instance, so other components (e.g. the block's render_callback) can reuse the one created at boot instead of re-registering shortcodes. */
	private static $instance = null;

	/** @var bool Whether the shared CSS has already been printed for this request. */
	private static $styles_printed = false;

	/**
	 * @return DLH_Shortcode
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_shortcode( 'dynamic_link_hub', array( $this, 'render' ) );

		// Keep the original shortcode tag working too, in case it's already
		// placed on pages from the old snippet.
		add_shortcode( 'dynamic_link_hub_final', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$settings = dlh_get_settings();

		ob_start();

		// Printed inline (once per request) rather than queued to wp_footer:
		// the block editor's live preview renders this markup through a
		// single REST call (ServerSideRender) where wp_footer never fires,
		// so footer-queued CSS would leave the preview unstyled. Printing it
		// here works identically on the front end, since the shortcode's
		// own output already appears well before </body>.
		$this->maybe_print_styles( $settings );

		printf(
			'<div class="dlh-link-hub" style="max-width:%dpx;">',
			absint( $settings['container_width'] )
		);

		// --- Avatar image ---
		$this->render_avatar( $settings );

		// --- Most recent post button ---
		if ( ! empty( $settings['show_recent_post'] ) ) {
			$recent = new WP_Query(
				array(
					'post_type'           => 'post',
					'posts_per_page'      => 1,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			if ( $recent->have_posts() ) {
				echo '<div class="link-hub-section link-hub-posts">';
				while ( $recent->have_posts() ) {
					$recent->the_post();
					printf(
						'<a href="%1$s" class="link-hub-button">%2$s</a>',
						esc_url( get_permalink() ),
						esc_html( $settings['recent_post_label'] )
					);
				}
				echo '</div>';
				wp_reset_postdata();
			}
		}

		// --- Custom link buttons ---
		if ( ! empty( $settings['links'] ) ) {
			echo '<div class="link-hub-section link-hub-pages">';
			foreach ( $settings['links'] as $link ) {
				$this->render_link_button( $link );
			}
			echo '</div>';
		}

		// --- Social links ---
		if ( ! empty( $settings['social_links'] ) ) {
			$this->render_social_links( $settings['social_links'], $settings['button_bg'] );
		}

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * The round profile image shown above the button stack, when one has
	 * been chosen in Appearance. Size and border are inline styles
	 * (per-install values from settings); the circular shape itself
	 * lives in the shared inline CSS block.
	 *
	 * @param array $settings Full plugin settings.
	 */
	private function render_avatar( $settings ) {
		if ( empty( $settings['avatar_url'] ) ) {
			return;
		}

		printf(
			'<div class="dlh-avatar-wrap"><img src="%1$s" alt="" class="dlh-avatar" style="width:%2$dpx;height:%2$dpx;border-width:%3$dpx;border-color:%4$s;" /></div>',
			esc_url( $settings['avatar_url'] ),
			absint( $settings['avatar_size'] ),
			absint( $settings['avatar_border_width'] ),
			esc_attr( sanitize_hex_color( $settings['avatar_border_color'] ) ? $settings['avatar_border_color'] : '#7E00B8' )
		);
	}

	private function render_link_button( $link ) {
		$url   = '';
		$label = isset( $link['label'] ) ? $link['label'] : '';

		if ( 'existing' === $link['type'] && ! empty( $link['post_id'] ) ) {
			$post_id = absint( $link['post_id'] );
			$url     = get_permalink( $post_id );
			if ( '' === $label ) {
				$label = get_the_title( $post_id );
			}
			if ( ! $url ) {
				return; // The page/post may have been deleted since.
			}
		} elseif ( 'custom' === $link['type'] && ! empty( $link['url'] ) ) {
			$url = $link['url'];
			if ( '' === $label ) {
				$label = $url;
			}
		} else {
			return;
		}

		$target_attr = ! empty( $link['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		printf(
			'<a href="%1$s" class="link-hub-button"%2$s>%3$s</a>',
			esc_url( $url ),
			$target_attr, // phpcs:ignore -- built from static/known-safe strings above.
			esc_html( $label )
		);
	}

	/**
	 * Renders the horizontally centered row of social icons below the
	 * button stack, built straight from the saved Social Links repeater
	 * — no WordPress menu involved. The icon itself (not a circle behind
	 * it) carries that platform's own official brand color, on a
	 * transparent background; the color lightens on hover/focus.
	 *
	 * @param array $links    Sanitized entries: { platform, url, label }.
	 * @param string $fallback_hex Button background color, used where a
	 *                              platform has no official color of its own.
	 */
	private function render_social_links( $links, $fallback_hex ) {
		echo '<ul class="dlh-social-menu dlh-social-menu-icons">';
		foreach ( $links as $link ) {
			$platform = isset( $link['platform'] ) ? $link['platform'] : 'custom';
			$url      = isset( $link['url'] ) ? $link['url'] : '';
			if ( '' === $url ) {
				continue;
			}

			$label  = ! empty( $link['label'] ) ? $link['label'] : DLH_Icons::default_label( $platform );
			$target = ( 0 === stripos( $url, 'http' ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';
			$color  = DLH_Icons::color( $platform, $fallback_hex );

			printf(
				'<li><a href="%1$s" class="dlh-social-icon" style="--dlh-icon-color:%5$s;color:%5$s" aria-label="%2$s" title="%2$s"%3$s>%4$s</a></li>',
				esc_url( $url ),
				esc_attr( $label ),
				$target, // phpcs:ignore -- fixed string, never user input.
				DLH_Icons::markup( $platform ), // phpcs:ignore -- fixed, known-safe SVG/text from DLH_Icons.
				esc_attr( $color )
			);
		}
		echo '</ul>';
	}

	private function maybe_print_styles( $settings ) {
		if ( self::$styles_printed ) {
			return;
		}
		self::$styles_printed = true;

		$css = sprintf(
			'
.dlh-link-hub { margin: 0 auto; }
.dlh-avatar-wrap { display: flex; justify-content: center; margin-bottom: 1.25rem; }
.dlh-avatar { display: block; border-radius: 50%%; border-style: solid; object-fit: cover; }
.dlh-link-hub .link-hub-section { display: flex; flex-direction: column; }
.dlh-link-hub .link-hub-button {
	display: block;
	background-color: %1$s;
	color: %2$s;
	padding: 15px 20px;
	margin-bottom: 1rem;
	border-radius: %5$dpx;
	text-align: center;
	text-decoration: none;
	font-weight: 600;
	transition: background-color 0.2s ease, color 0.2s ease;
}
.dlh-link-hub .link-hub-button:hover,
.dlh-link-hub .link-hub-button:focus {
	background-color: %3$s;
	color: %4$s;
}
.dlh-social-menu { list-style: none; display: flex; flex-wrap: wrap; gap: .85rem; justify-content: center; align-items: center; margin: 1.5rem 0 0; padding: 0; }
.dlh-social-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 60px;
	height: 60px;
	border-radius: 50%%;
	background-color: transparent;
	text-decoration: none;
	font-weight: 700;
	font-size: 20px;
	line-height: 1;
	transition: transform 0.15s ease, opacity 0.15s ease, color 0.15s ease;
}
.dlh-social-icon:hover, .dlh-social-icon:focus {
	transform: scale(1.08);
	opacity: 0.7;
	color: color-mix(in srgb, var(--dlh-icon-color) 55%%, white);
}
.dlh-social-icon svg { width: 30px; height: 30px; fill: currentColor; }
.dlh-social-glyph { font-size: 22px; }
',
			sanitize_hex_color( $settings['button_bg'] ),
			sanitize_hex_color( $settings['button_text'] ),
			sanitize_hex_color( $settings['button_bg_hover'] ),
			sanitize_hex_color( $settings['button_text_hover'] ),
			absint( $settings['button_radius'] )
		);

		echo '<style id="dynamic-link-hub-styles">' . $css . '</style>'; // phpcs:ignore -- printf-built CSS, values sanitized above.
	}
}
