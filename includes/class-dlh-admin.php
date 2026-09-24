<?php
/**
 * Admin settings screen for Dynamic Link Hub.
 *
 * All three tabs (Appearance, Links, Social Links) live inside ONE <form>
 * that always submits every field; the tabs themselves are just CSS/JS
 * show/hide panels. This keeps the settings save dead simple: every save
 * is a full, unambiguous snapshot of the whole option, so there's no
 * "which tab was this partial submission for" logic that could silently
 * drop a save.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DLH_Admin {

	const OPTION_GROUP = 'dlh_settings_group';
	const PAGE_SLUG    = 'dynamic-link-hub';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DLH_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
		add_action( 'admin_post_dlh_export_settings', array( $this, 'handle_export' ) );
		add_action( 'admin_post_dlh_import_settings', array( $this, 'handle_import' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Link Hub', 'dynamic-link-hub' ),
			__( 'Link Hub', 'dynamic-link-hub' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-admin-links',
			58
		);
	}

	public function add_settings_link( $links ) {
		$url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'dynamic-link-hub' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			DLH_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => dlh_get_default_settings(),
			)
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_media();

		wp_enqueue_style( 'dlh-admin', DLH_PLUGIN_URL . 'assets/admin.css', array(), DLH_VERSION );
		wp_enqueue_script(
			'dlh-admin',
			DLH_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
			DLH_VERSION,
			true
		);
	}

	/**
	 * The whole form is always submitted together, so this always produces
	 * a complete, self-consistent settings array — no merging with what
	 * was previously saved.
	 *
	 * @param array $input Raw posted value for the dlh_settings option.
	 * @return array
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$defaults = dlh_get_default_settings();
		$output   = array();

		// Appearance.
		$output['button_bg']         = $this->sanitize_color( $input, 'button_bg', $defaults['button_bg'] );
		$output['button_text']       = $this->sanitize_color( $input, 'button_text', $defaults['button_text'] );
		$output['button_bg_hover']   = $this->sanitize_color( $input, 'button_bg_hover', $defaults['button_bg_hover'] );
		$output['button_text_hover'] = $this->sanitize_color( $input, 'button_text_hover', $defaults['button_text_hover'] );

		$radius                  = isset( $input['button_radius'] ) ? absint( $input['button_radius'] ) : $defaults['button_radius'];
		$output['button_radius'] = min( 50, max( 0, $radius ) );

		$width                      = isset( $input['container_width'] ) ? absint( $input['container_width'] ) : $defaults['container_width'];
		$output['container_width'] = min( 800, max( 280, $width ) );

		// Avatar image.
		$avatar_id            = isset( $input['avatar_id'] ) ? absint( $input['avatar_id'] ) : 0;
		$output['avatar_id']  = $avatar_id;
		$output['avatar_url'] = $avatar_id ? esc_url_raw( (string) wp_get_attachment_url( $avatar_id ) ) : '';

		$avatar_size            = isset( $input['avatar_size'] ) ? absint( $input['avatar_size'] ) : $defaults['avatar_size'];
		$output['avatar_size']  = min( 300, max( 60, $avatar_size ) );

		$avatar_border_width           = isset( $input['avatar_border_width'] ) ? absint( $input['avatar_border_width'] ) : $defaults['avatar_border_width'];
		$output['avatar_border_width'] = min( 20, max( 0, $avatar_border_width ) );

		$output['avatar_border_color'] = $this->sanitize_color( $input, 'avatar_border_color', $defaults['avatar_border_color'] );

		// Links.
		$output['show_recent_post']  = ! empty( $input['show_recent_post'] ) ? 1 : 0;
		$output['recent_post_label'] = isset( $input['recent_post_label'] ) && '' !== trim( (string) $input['recent_post_label'] )
			? sanitize_text_field( wp_unslash( $input['recent_post_label'] ) )
			: $defaults['recent_post_label'];

		$output['links'] = $this->sanitize_links( isset( $input['links'] ) ? $input['links'] : array() );

		// Social links.
		$output['social_links'] = $this->sanitize_social_links( isset( $input['social_links'] ) ? $input['social_links'] : array() );

		return $output;
	}

	private function sanitize_color( $input, $key, $fallback ) {
		if ( empty( $input[ $key ] ) ) {
			return $fallback;
		}
		$color = sanitize_hex_color( wp_unslash( $input[ $key ] ) );
		return $color ? $color : $fallback;
	}

	private function sanitize_links( $raw_links ) {
		$clean = array();

		if ( ! is_array( $raw_links ) ) {
			return $clean;
		}

		foreach ( $raw_links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type = isset( $row['type'] ) && 'custom' === $row['type'] ? 'custom' : 'existing';

			$entry = array(
				'label'   => isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : '',
				'type'    => $type,
				'post_id' => isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0,
				'url'     => isset( $row['url'] ) ? esc_url_raw( trim( wp_unslash( $row['url'] ) ) ) : '',
				'new_tab' => ! empty( $row['new_tab'] ) ? 1 : 0,
			);

			// Skip rows that don't actually point anywhere.
			if ( 'existing' === $entry['type'] && $entry['post_id'] <= 0 ) {
				continue;
			}
			if ( 'custom' === $entry['type'] && '' === $entry['url'] ) {
				continue;
			}

			$clean[] = $entry;
		}

		return $clean;
	}

	private function sanitize_social_links( $raw_links ) {
		$clean = array();

		if ( ! is_array( $raw_links ) ) {
			return $clean;
		}

		$known_platforms = array_keys( DLH_Icons::platforms() );

		foreach ( $raw_links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$platform = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : 'custom';
			if ( ! in_array( $platform, $known_platforms, true ) ) {
				$platform = 'custom';
			}

			$value = isset( $row['value'] ) ? trim( wp_unslash( $row['value'] ) ) : '';
			if ( '' === $value ) {
				continue;
			}

			$url = $this->normalize_social_url( $platform, $value );
			if ( '' === $url ) {
				continue;
			}

			$clean[] = array(
				'platform' => $platform,
				'url'      => $url,
				'label'    => isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : '',
			);
		}

		return $clean;
	}

	/**
	 * Turn whatever the admin typed into a usable URL for the given
	 * platform: email addresses become mailto:, phone numbers become
	 * tel:, and bare domains/handles get https:// prepended.
	 */
	private function normalize_social_url( $platform, $value ) {
		if ( 'email' === $platform ) {
			$email = sanitize_email( $value );
			return $email ? 'mailto:' . $email : '';
		}

		if ( 'phone' === $platform ) {
			$digits = preg_replace( '/[^0-9+]/', '', $value );
			return $digits ? 'tel:' . $digits : '';
		}

		if ( ! preg_match( '#^https?://#i', $value ) && 0 !== stripos( $value, 'mailto:' ) && 0 !== stripos( $value, 'tel:' ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}

		return esc_url_raw( $value );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = dlh_get_settings();
		$tabs     = array(
			'appearance'    => __( 'Appearance', 'dynamic-link-hub' ),
			'links'         => __( 'Links', 'dynamic-link-hub' ),
			'social'        => __( 'Social Links', 'dynamic-link-hub' ),
			'import-export' => __( 'Import / Export', 'dynamic-link-hub' ),
		);
		?>
		<div class="wrap dlh-wrap">
			<h1><?php esc_html_e( 'Dynamic Link Hub', 'dynamic-link-hub' ); ?></h1>
			<p><?php esc_html_e( 'Configure the button hub shown by the [dynamic_link_hub] shortcode.', 'dynamic-link-hub' ); ?></p>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag set by WP core's own options.php redirect; nothing is written based on it. ?>
			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Link Hub settings saved.', 'dynamic-link-hub' ); ?></p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper" id="dlh-tab-nav">
				<?php $first = true; ?>
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="#dlh-panel-<?php echo esc_attr( $slug ); ?>"
						class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>"
						data-tab="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php" class="dlh-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<div class="dlh-tab-panel" id="dlh-panel-appearance">
					<?php $this->render_appearance_tab( $settings ); ?>
				</div>

				<div class="dlh-tab-panel" id="dlh-panel-links" style="display:none;">
					<?php $this->render_links_tab( $settings ); ?>
				</div>

				<div class="dlh-tab-panel" id="dlh-panel-social" style="display:none;">
					<?php $this->render_social_tab( $settings ); ?>
				</div>

				<?php submit_button( __( 'Save Changes', 'dynamic-link-hub' ) ); ?>
			</form>

			<?php // Its own tab, but deliberately its own <form>s (not nested inside the settings form above) since it needs file upload + a different submit target (admin-post.php). ?>
			<div class="dlh-tab-panel" id="dlh-panel-import-export" style="display:none;">
				<?php $this->render_import_export_tab(); ?>
			</div>

			<hr />
			<p>
				<?php
				printf(
					/* translators: %s: shortcode tag */
					esc_html__( 'Shortcode: %s', 'dynamic-link-hub' ),
					'<code>[dynamic_link_hub]</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private function render_appearance_tab( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Button color', 'dynamic-link-hub' ); ?></th>
				<td>
					<input type="text" class="dlh-color-field" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[button_bg]" value="<?php echo esc_attr( $settings['button_bg'] ); ?>" data-default-color="#7E00B8" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Button text color', 'dynamic-link-hub' ); ?></th>
				<td>
					<input type="text" class="dlh-color-field" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[button_text]" value="<?php echo esc_attr( $settings['button_text'] ); ?>" data-default-color="#ffffff" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Button hover color', 'dynamic-link-hub' ); ?></th>
				<td>
					<input type="text" class="dlh-color-field" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[button_bg_hover]" value="<?php echo esc_attr( $settings['button_bg_hover'] ); ?>" data-default-color="#FEB400" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Button hover text color', 'dynamic-link-hub' ); ?></th>
				<td>
					<input type="text" class="dlh-color-field" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[button_text_hover]" value="<?php echo esc_attr( $settings['button_text_hover'] ); ?>" data-default-color="#000000" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dlh_button_radius"><?php esc_html_e( 'Corner radius (px)', 'dynamic-link-hub' ); ?></label></th>
				<td>
					<input type="number" id="dlh_button_radius" min="0" max="50" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[button_radius]" value="<?php echo esc_attr( $settings['button_radius'] ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dlh_container_width"><?php esc_html_e( 'Container width', 'dynamic-link-hub' ); ?></label></th>
				<td>
					<?php $this->render_range_control( 'container_width', $settings['container_width'], 280, 800, 10, 'px' ); ?>
					<p class="description"><?php esc_html_e( 'How wide the whole link hub (avatar, buttons, and social icons) is allowed to grow before it centers itself.', 'dynamic-link-hub' ); ?></p>
				</td>
			</tr>
		</table>
		<div class="dlh-preview">
			<span class="dlh-preview-label"><?php esc_html_e( 'Live preview:', 'dynamic-link-hub' ); ?></span>
			<a href="#" class="dlh-preview-button link-hub-button" onclick="return false;"><?php esc_html_e( 'Example Button', 'dynamic-link-hub' ); ?></a>
		</div>

		<?php $this->render_avatar_fields( $settings ); ?>
		<?php
	}

	/**
	 * A Gutenberg-style range slider paired with a live number readout,
	 * used anywhere we ask for a pixel value with a sane min/max instead
	 * of a free-typed number field.
	 */
	private function render_range_control( $key, $value, $min, $max, $step, $unit ) {
		$id   = 'dlh_' . $key;
		$name = esc_attr( DLH_OPTION_KEY ) . '[' . $key . ']';
		?>
		<span class="dlh-range-control">
			<input
				type="range"
				id="<?php echo esc_attr( $id ); ?>"
				class="dlh-range-input"
				min="<?php echo esc_attr( $min ); ?>"
				max="<?php echo esc_attr( $max ); ?>"
				step="<?php echo esc_attr( $step ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				data-paired-number="<?php echo esc_attr( $id ); ?>_number" />
			<input
				type="number"
				id="<?php echo esc_attr( $id ); ?>_number"
				class="dlh-range-number"
				name="<?php echo $name; // phpcs:ignore -- already escaped above. ?>"
				min="<?php echo esc_attr( $min ); ?>"
				max="<?php echo esc_attr( $max ); ?>"
				step="<?php echo esc_attr( $step ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				data-paired-range="<?php echo esc_attr( $id ); ?>" /><span class="dlh-range-unit"><?php echo esc_html( $unit ); ?></span>
		</span>
		<?php
	}

	private function render_avatar_fields( $settings ) {
		$has_avatar = ! empty( $settings['avatar_url'] );
		?>
		<h2><?php esc_html_e( 'Avatar Image', 'dynamic-link-hub' ); ?></h2>
		<p class="description"><?php esc_html_e( 'An optional round image shown above the buttons.', 'dynamic-link-hub' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Image', 'dynamic-link-hub' ); ?></th>
				<td>
					<div class="dlh-avatar-picker">
						<img
							id="dlh-avatar-preview-img"
							class="dlh-avatar-preview-img"
							src="<?php echo $has_avatar ? esc_url( $settings['avatar_url'] ) : ''; ?>"
							style="<?php echo $has_avatar ? '' : 'display:none;'; ?>border-width:<?php echo esc_attr( $settings['avatar_border_width'] ); ?>px;border-color:<?php echo esc_attr( $settings['avatar_border_color'] ); ?>;" />
						<input type="hidden" id="dlh_avatar_id" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[avatar_id]" value="<?php echo esc_attr( $settings['avatar_id'] ); ?>" />
						<p>
							<button type="button" class="button" id="dlh-avatar-choose"><?php esc_html_e( 'Choose Image', 'dynamic-link-hub' ); ?></button>
							<button type="button" class="button-link" id="dlh-avatar-remove" style="<?php echo $has_avatar ? '' : 'display:none;'; ?>margin-left:8px;color:#b32d2e;"><?php esc_html_e( 'Remove', 'dynamic-link-hub' ); ?></button>
						</p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dlh_avatar_size"><?php esc_html_e( 'Image size', 'dynamic-link-hub' ); ?></label></th>
				<td><?php $this->render_range_control( 'avatar_size', $settings['avatar_size'], 60, 300, 5, 'px' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="dlh_avatar_border_width"><?php esc_html_e( 'Border thickness', 'dynamic-link-hub' ); ?></label></th>
				<td><?php $this->render_range_control( 'avatar_border_width', $settings['avatar_border_width'], 0, 20, 1, 'px' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Border color', 'dynamic-link-hub' ); ?></th>
				<td>
					<input type="text" id="dlh_avatar_border_color" class="dlh-color-field" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[avatar_border_color]" value="<?php echo esc_attr( $settings['avatar_border_color'] ); ?>" data-default-color="#7E00B8" />
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_links_tab( $settings ) {
		$content_options = $this->get_content_options_html();
		?>
		<h2><?php esc_html_e( 'Most Recent Post Button', 'dynamic-link-hub' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Show it', 'dynamic-link-hub' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[show_recent_post]" value="1" <?php checked( ! empty( $settings['show_recent_post'] ) ); ?> />
						<?php esc_html_e( 'Always show a button linking to your most recent blog post', 'dynamic-link-hub' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dlh_recent_post_label"><?php esc_html_e( 'Button label', 'dynamic-link-hub' ); ?></label></th>
				<td>
					<input type="text" id="dlh_recent_post_label" class="regular-text" name="<?php echo esc_attr( DLH_OPTION_KEY ); ?>[recent_post_label]" value="<?php echo esc_attr( $settings['recent_post_label'] ); ?>" />
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Custom Link Buttons', 'dynamic-link-hub' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Add, edit, remove, and drag to reorder buttons that link to any page, post, or custom URL.', 'dynamic-link-hub' ); ?></p>

		<div id="dlh-links-repeater" class="dlh-links-repeater">
			<div class="dlh-links-list">
				<?php
				if ( ! empty( $settings['links'] ) ) {
					foreach ( $settings['links'] as $index => $link ) {
						$this->render_link_row( $index, $link, $content_options );
					}
				}
				?>
			</div>
			<p>
				<button type="button" class="button button-secondary" id="dlh-add-link">
					<?php esc_html_e( '+ Add Link', 'dynamic-link-hub' ); ?>
				</button>
			</p>
		</div>

		<template id="dlh-link-row-template">
			<?php $this->render_link_row( '__INDEX__', array(), $content_options ); ?>
		</template>
		<?php
	}

	private function render_link_row( $index, $link, $content_options ) {
		$link = wp_parse_args(
			$link,
			array(
				'label'   => '',
				'type'    => 'existing',
				'post_id' => 0,
				'url'     => '',
				'new_tab' => 0,
			)
		);
		$name = DLH_OPTION_KEY . '[links][' . $index . ']';
		?>
		<div class="dlh-link-row">
			<span class="dlh-drag-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'dynamic-link-hub' ); ?>"></span>

			<div class="dlh-link-row-fields">
				<p>
					<label><?php esc_html_e( 'Button label (optional — defaults to the page/post title)', 'dynamic-link-hub' ); ?></label>
					<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $link['label'] ); ?>" />
				</p>

				<p class="dlh-link-type-choice">
					<label>
						<input type="radio" class="dlh-link-type" name="<?php echo esc_attr( $name ); ?>[type]" value="existing" <?php checked( 'existing', $link['type'] ); ?> />
						<?php esc_html_e( 'Existing page or post', 'dynamic-link-hub' ); ?>
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" class="dlh-link-type" name="<?php echo esc_attr( $name ); ?>[type]" value="custom" <?php checked( 'custom', $link['type'] ); ?> />
						<?php esc_html_e( 'Custom URL', 'dynamic-link-hub' ); ?>
					</label>
				</p>

				<p class="dlh-field-existing" <?php echo 'custom' === $link['type'] ? 'style="display:none;"' : ''; ?>>
					<select name="<?php echo esc_attr( $name ); ?>[post_id]">
						<option value="0"><?php esc_html_e( '— Select a page or post —', 'dynamic-link-hub' ); ?></option>
						<?php echo $this->mark_selected_option( $content_options, $link['post_id'] ); ?>
					</select>
				</p>

				<p class="dlh-field-custom" <?php echo 'custom' !== $link['type'] ? 'style="display:none;"' : ''; ?>>
					<input type="url" class="regular-text" placeholder="https://example.com/" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $link['url'] ); ?>" />
				</p>

				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[new_tab]" value="1" <?php checked( ! empty( $link['new_tab'] ) ); ?> />
						<?php esc_html_e( 'Open in a new tab', 'dynamic-link-hub' ); ?>
					</label>
				</p>
			</div>

			<button type="button" class="button-link dlh-remove-link" title="<?php esc_attr_e( 'Remove this button', 'dynamic-link-hub' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Build the shared <option> list of pages + posts, used for every row.
	 */
	private function get_content_options_html() {
		$html = '';

		foreach ( array( 'page' => __( 'Pages', 'dynamic-link-hub' ), 'post' => __( 'Posts', 'dynamic-link-hub' ) ) as $post_type => $label ) {
			$items = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			if ( empty( $items ) ) {
				continue;
			}

			$html .= '<optgroup label="' . esc_attr( $label ) . '">';
			foreach ( $items as $item ) {
				$html .= '<option value="' . esc_attr( $item->ID ) . '" data-post-id="' . esc_attr( $item->ID ) . '">' . esc_html( $item->post_title ) . '</option>';
			}
			$html .= '</optgroup>';
		}

		return $html;
	}

	/**
	 * Mark the option matching $selected_id as selected in a pre-built
	 * options HTML blob (avoids rebuilding the whole list per row).
	 */
	private function mark_selected_option( $options_html, $selected_id ) {
		$selected_id = absint( $selected_id );
		if ( $selected_id <= 0 ) {
			return $options_html;
		}
		return preg_replace(
			'/value="' . $selected_id . '"/',
			'value="' . $selected_id . '" selected="selected"',
			$options_html,
			1
		);
	}

	private function render_social_tab( $settings ) {
		?>
		<h2><?php esc_html_e( 'Social Links', 'dynamic-link-hub' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Add, edit, remove, and drag to reorder icons shown in a horizontally centered row below the link buttons. No WordPress menu needed — just pick a platform and paste a link.', 'dynamic-link-hub' ); ?></p>

		<div id="dlh-social-repeater" class="dlh-links-repeater">
			<div class="dlh-links-list">
				<?php
				if ( ! empty( $settings['social_links'] ) ) {
					foreach ( $settings['social_links'] as $index => $social ) {
						$this->render_social_row( $index, $social );
					}
				}
				?>
			</div>
			<p>
				<button type="button" class="button button-secondary" id="dlh-add-social">
					<?php esc_html_e( '+ Add Social Link', 'dynamic-link-hub' ); ?>
				</button>
			</p>
		</div>

		<template id="dlh-social-row-template">
			<?php $this->render_social_row( '__INDEX__', array() ); ?>
		</template>
		<?php
	}

	private function render_social_row( $index, $social ) {
		$social = wp_parse_args(
			$social,
			array(
				'platform' => 'facebook',
				'url'      => '',
				'label'    => '',
			)
		);

		// The stored value is already a finished URL (e.g. mailto:you@x.com);
		// show it back to the admin as-is so editing a row doesn't require
		// re-typing it in a different shape than what they see on save.
		$display_value = $social['url'];
		if ( 0 === stripos( $display_value, 'mailto:' ) ) {
			$display_value = substr( $display_value, 7 );
		} elseif ( 0 === stripos( $display_value, 'tel:' ) ) {
			$display_value = substr( $display_value, 4 );
		}

		$name      = DLH_OPTION_KEY . '[social_links][' . $index . ']';
		$platforms = DLH_Icons::platforms();
		?>
		<div class="dlh-link-row">
			<span class="dlh-drag-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'dynamic-link-hub' ); ?>"></span>

			<div class="dlh-link-row-fields">
				<p>
					<label><?php esc_html_e( 'Platform', 'dynamic-link-hub' ); ?></label>
					<select class="dlh-social-platform" name="<?php echo esc_attr( $name ); ?>[platform]">
						<?php foreach ( $platforms as $slug => $data ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" data-placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php selected( $social['platform'], $slug ); ?>>
								<?php echo esc_html( $data['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<label><?php esc_html_e( 'Link, handle, email, or number', 'dynamic-link-hub' ); ?></label>
					<input type="text" class="regular-text dlh-social-value" placeholder="<?php echo esc_attr( DLH_Icons::get( $social['platform'] )['placeholder'] ); ?>" name="<?php echo esc_attr( $name ); ?>[value]" value="<?php echo esc_attr( $display_value ); ?>" />
				</p>

				<p>
					<label><?php esc_html_e( 'Accessible label (optional — defaults to the platform name)', 'dynamic-link-hub' ); ?></label>
					<input type="text" class="regular-text" placeholder="<?php echo esc_attr( DLH_Icons::get( $social['platform'] )['label'] ); ?>" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $social['label'] ); ?>" />
				</p>
			</div>

			<button type="button" class="button-link dlh-remove-link" title="<?php esc_attr_e( 'Remove this icon', 'dynamic-link-hub' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
		<?php
	}

	private function render_import_export_tab() {
		?>
		<h2><?php esc_html_e( 'Export Settings', 'dynamic-link-hub' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Download every Link Hub setting on this site — appearance, avatar, links, and social links — as a single JSON file, so you can back it up or copy it to another site.', 'dynamic-link-hub' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dlh_export_settings" />
			<?php wp_nonce_field( 'dlh_export_settings', 'dlh_export_nonce' ); ?>
			<?php submit_button( __( 'Download Settings (JSON)', 'dynamic-link-hub' ), 'secondary' ); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Import Settings', 'dynamic-link-hub' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload a settings file exported from this plugin. This immediately replaces ALL current Link Hub settings on this site — export your current settings first if you want to keep a copy.', 'dynamic-link-hub' ); ?></p>
		<p class="description">
			<?php esc_html_e( 'Note: the avatar image and any buttons linking to an existing page or post are specific to the site they were exported from. Importing into a different site will drop the avatar and those buttons (everything else, including custom-URL buttons and social links, carries over normally) — just re-add them afterward.', 'dynamic-link-hub' ); ?>
		</p>
		<?php $this->render_import_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="dlh_import_settings" />
			<?php wp_nonce_field( 'dlh_import_settings', 'dlh_import_nonce' ); ?>
			<p><input type="file" name="dlh_import_file" accept="application/json,.json" required="required" /></p>
			<?php submit_button( __( 'Import Settings', 'dynamic-link-hub' ), 'primary' ); ?>
		</form>
		<?php
	}

	/**
	 * Success/error notice for the import form, driven by the ?dlh_import=
	 * query arg our own admin-post.php redirect adds — never anything an
	 * attacker could use to write data, only to select which message shows.
	 */
	private function render_import_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag from our own redirect (handle_import()); nothing is written based on it.
		if ( ! isset( $_GET['dlh_import'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$status = sanitize_key( wp_unslash( $_GET['dlh_import'] ) );

		if ( 'success' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings imported successfully.', 'dynamic-link-hub' ) . '</p></div>';
			return;
		}

		$messages = array(
			'upload_error' => __( 'That file could not be uploaded. Please try again.', 'dynamic-link-hub' ),
			'invalid_file' => __( 'That does not look like a Dynamic Link Hub settings file (or it is corrupted). Please choose a JSON file exported from this plugin.', 'dynamic-link-hub' ),
		);
		$message = isset( $messages[ $status ] ) ? $messages[ $status ] : __( 'Something went wrong importing that file.', 'dynamic-link-hub' );

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Streams the current settings as a downloadable JSON file. Wrapped in
	 * a small versioned envelope (rather than the raw settings array) so a
	 * future format change has somewhere to add a migration, and so
	 * handle_import() can reject unrelated JSON files instead of silently
	 * misinterpreting them.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dynamic-link-hub' ) );
		}
		check_admin_referer( 'dlh_export_settings', 'dlh_export_nonce' );

		$envelope = array(
			'plugin'      => 'dynamic-link-hub',
			'version'     => DLH_VERSION,
			'exported_at' => gmdate( 'c' ),
			'settings'    => dlh_get_settings(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="dynamic-link-hub-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $envelope, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Reads an uploaded settings JSON file and, if it's valid, saves it as
	 * the site's Link Hub settings. Deliberately reuses sanitize() — the
	 * exact same clamping/validation a normal form save goes through — so
	 * an imported file can never end up less validated than a manual save.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dynamic-link-hub' ) );
		}
		check_admin_referer( 'dlh_import_settings', 'dlh_import_nonce' );

		$redirect_to = admin_url( 'admin.php?page=' . self::PAGE_SLUG ) . '#dlh-panel-import-export';

		if ( empty( $_FILES['dlh_import_file'] ) || UPLOAD_ERR_OK !== $_FILES['dlh_import_file']['error'] ) {
			wp_safe_redirect( add_query_arg( 'dlh_import', 'upload_error', $redirect_to ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- small JSON settings file from a just-completed same-request upload; WP_Filesystem is unnecessary overhead here.
		$raw  = file_get_contents( $_FILES['dlh_import_file']['tmp_name'] );
		$data = json_decode( (string) $raw, true );

		$settings = ( is_array( $data ) && isset( $data['plugin'], $data['settings'] ) && 'dynamic-link-hub' === $data['plugin'] && is_array( $data['settings'] ) )
			? $data['settings']
			: null;

		if ( null === $settings ) {
			wp_safe_redirect( add_query_arg( 'dlh_import', 'invalid_file', $redirect_to ) );
			exit;
		}

		update_option( DLH_OPTION_KEY, $this->sanitize( $settings ) );

		wp_safe_redirect( add_query_arg( 'dlh_import', 'success', $redirect_to ) );
		exit;
	}
}
