/**
 * Admin screen behavior for Dynamic Link Hub.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */
( function ( $ ) {
	'use strict';

	function initColorPickers() {
		$( '.dlh-color-field' ).wpColorPicker( {
			change: function () {
				// Give the live preview a moment to pick up the new value.
				setTimeout( updatePreview, 50 );
				setTimeout( updateAvatarPreviewBorder, 50 );
			},
			clear: function () {
				setTimeout( updatePreview, 50 );
				setTimeout( updateAvatarPreviewBorder, 50 );
			},
		} );
	}

	function updatePreview() {
		var $preview = $( '.dlh-preview-button' );
		if ( ! $preview.length ) {
			return;
		}

		var bg       = $( 'input[name$="[button_bg]"]' ).val(),
			text     = $( 'input[name$="[button_text]"]' ).val(),
			bgHover  = $( 'input[name$="[button_bg_hover]"]' ).val(),
			textHov  = $( 'input[name$="[button_text_hover]"]' ).val(),
			radius   = $( '#dlh_button_radius' ).val();

		$preview.css( {
			backgroundColor: bg || '#7E00B8',
			color: text || '#ffffff',
			borderRadius: ( radius || 8 ) + 'px',
		} );

		$preview.off( 'mouseenter.dlhPreview mouseleave.dlhPreview' );
		$preview.on( 'mouseenter.dlhPreview', function () {
			$( this ).css( {
				backgroundColor: bgHover || '#FEB400',
				color: textHov || '#000000',
			} );
		} );
		$preview.on( 'mouseleave.dlhPreview', function () {
			$( this ).css( {
				backgroundColor: bg || '#7E00B8',
				color: text || '#ffffff',
			} );
		} );
	}

	/**
	 * Keeps each range slider and its paired number field in sync in both
	 * directions, so admins can drag or type either one.
	 */
	function initRangeControls() {
		$( '.dlh-range-input' ).on( 'input change', function () {
			$( '#' + $( this ).data( 'paired-number' ) ).val( $( this ).val() );
		} );
		$( '.dlh-range-number' ).on( 'input change', function () {
			$( '#' + $( this ).data( 'paired-range' ) ).val( $( this ).val() );
		} );
	}

	/**
	 * The avatar image "Choose Image" / "Remove" buttons, backed by the
	 * standard WordPress media library modal, plus a live border preview
	 * that responds to the size/thickness/color fields next to it.
	 */
	function initAvatarPicker() {
		var $chooseBtn = $( '#dlh-avatar-choose' ),
			$removeBtn = $( '#dlh-avatar-remove' ),
			$idField   = $( '#dlh_avatar_id' ),
			$img       = $( '#dlh-avatar-preview-img' ),
			frame;

		if ( ! $chooseBtn.length || 'undefined' === typeof wp || ! wp.media ) {
			return;
		}

		$chooseBtn.on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: 'Select avatar image',
				library: { type: 'image' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON(),
					url        = attachment.url;

				if ( attachment.sizes && attachment.sizes.medium ) {
					url = attachment.sizes.medium.url;
				}

				$idField.val( attachment.id );
				$img.attr( 'src', url ).show();
				$removeBtn.show();
			} );

			frame.open();
		} );

		$removeBtn.on( 'click', function ( e ) {
			e.preventDefault();
			$idField.val( 0 );
			$img.attr( 'src', '' ).hide();
			$removeBtn.hide();
		} );

		updateAvatarPreviewBorder();
		$( '#dlh_avatar_border_width, #dlh_avatar_border_width_number, #dlh_avatar_border_color, #dlh_avatar_size, #dlh_avatar_size_number' )
			.on( 'input change', updateAvatarPreviewBorder );
	}

	function updateAvatarPreviewBorder() {
		var $img    = $( '#dlh-avatar-preview-img' ),
			width   = $( '#dlh_avatar_border_width_number' ).val(),
			color   = $( '#dlh_avatar_border_color' ).val(),
			size    = $( '#dlh_avatar_size_number' ).val();

		if ( ! $img.length ) {
			return;
		}

		$img.css( {
			borderWidth: ( width || 0 ) + 'px',
			borderColor: color || '#7E00B8',
			width: ( size || 140 ) + 'px',
			height: ( size || 140 ) + 'px',
		} );
	}

	function initTabs() {
		var $nav = $( '#dlh-tab-nav' );
		if ( ! $nav.length ) {
			return;
		}

		$nav.on( 'click', 'a.nav-tab', function ( e ) {
			e.preventDefault();

			var tab = $( this ).data( 'tab' );

			$nav.find( 'a.nav-tab' ).removeClass( 'nav-tab-active' );
			$( this ).addClass( 'nav-tab-active' );

			$( '.dlh-tab-panel' ).hide();
			$( '#dlh-panel-' + tab ).show();

			if ( history.replaceState ) {
				history.replaceState( null, '', '#dlh-panel-' + tab );
			}
		} );

		// Deep-link support: if the URL already has a matching hash, show that tab.
		var hash = window.location.hash.replace( '#dlh-panel-', '' );
		if ( hash ) {
			$nav.find( 'a[data-tab="' + hash + '"]' ).trigger( 'click' );
		}
	}

	/**
	 * Generic add/remove/drag-to-reorder repeater. Both the Links tab and
	 * the Social Links tab use this — only the per-row wiring differs,
	 * via the optional onRowReady callback.
	 */
	function initRepeater( config ) {
		var $repeater = $( config.repeaterSelector ),
			$list     = $repeater.find( '.dlh-links-list' ),
			$template = $( config.templateSelector );

		if ( ! $list.length ) {
			return;
		}

		if ( config.onRowReady ) {
			$list.find( '.dlh-link-row' ).each( function () {
				config.onRowReady( $( this ) );
			} );
		}

		$( config.addButtonSelector ).on( 'click', function ( e ) {
			e.preventDefault();

			var html = $template.html();
			if ( ! html ) {
				return;
			}

			var index = 'new_' + Date.now() + '_' + Math.floor( Math.random() * 1000 );
			html = html.split( '__INDEX__' ).join( index );

			var $row = $( html );
			$list.append( $row );

			if ( config.onRowReady ) {
				config.onRowReady( $row );
			}
		} );

		$list.on( 'click', '.dlh-remove-link', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.dlh-link-row' ).remove();
		} );

		if ( config.onChange ) {
			$list.on( 'change', config.onChangeSelector, function () {
				config.onChange( $( this ).closest( '.dlh-link-row' ) );
			} );
		}

		$list.sortable( {
			handle: '.dlh-drag-handle',
			items: '.dlh-link-row',
			placeholder: 'dlh-link-row dlh-dragging',
			axis: 'y',
		} );
	}

	function toggleLinkFields( $row ) {
		var type = $row.find( '.dlh-link-type:checked' ).val();
		$row.find( '.dlh-field-existing' ).toggle( 'existing' === type );
		$row.find( '.dlh-field-custom' ).toggle( 'custom' === type );
	}

	function initLinksRepeater() {
		initRepeater( {
			repeaterSelector: '#dlh-links-repeater',
			addButtonSelector: '#dlh-add-link',
			templateSelector: '#dlh-link-row-template',
			onRowReady: toggleLinkFields,
			onChange: toggleLinkFields,
			onChangeSelector: '.dlh-link-type',
		} );
	}

	function updateSocialPlaceholder( $row ) {
		var $select      = $row.find( '.dlh-social-platform' ),
			$value       = $row.find( '.dlh-social-value' ),
			placeholder  = $select.find( 'option:selected' ).data( 'placeholder' );

		if ( placeholder ) {
			$value.attr( 'placeholder', placeholder );
		}
	}

	function initSocialRepeater() {
		initRepeater( {
			repeaterSelector: '#dlh-social-repeater',
			addButtonSelector: '#dlh-add-social',
			templateSelector: '#dlh-social-row-template',
			onRowReady: updateSocialPlaceholder,
			onChange: updateSocialPlaceholder,
			onChangeSelector: '.dlh-social-platform',
		} );
	}

	$( function () {
		initTabs();
		initColorPickers();
		updatePreview();
		initLinksRepeater();
		initSocialRepeater();
		initRangeControls();
		initAvatarPicker();
	} );
} )( jQuery );
