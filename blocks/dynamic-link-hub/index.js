/**
 * Block editor registration for the Link Hub block.
 *
 * Plain JS (no build step/JSX), matching the rest of this plugin's admin
 * scripts. This is a dynamic block: save() always returns null, and the
 * actual markup is produced server-side by DLH_Block::render() (the same
 * settings-driven output as the [dynamic_link_hub] shortcode). The editor
 * preview is powered by core's <ServerSideRender>, which calls that same
 * render callback over REST so what you see while editing matches the
 * front end.
 *
 * @package   DynamicLinkHub
 * @author    Ascendant Bits Creative Digital (https://ascendantbits.com/)
 * @copyright 2026 Ascendant Bits Creative Digital
 * @license   GPL-2.0-or-later
 */
( function ( blocks, element, blockEditor, serverSideRender, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ExternalLink = components.ExternalLink;
	var ServerSideRender = serverSideRender;

	var settingsUrl = ( window.dlhBlockData && window.dlhBlockData.settingsUrl ) || '';

	blocks.registerBlockType( 'dynamic-link-hub/hub', {
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'dlh-block-editor-preview' } );

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Link Hub settings', 'dynamic-link-hub' ) },
						el(
							'p',
							{},
							__( 'Colors, buttons, avatar, and social links are all configured in one place for every Link Hub on your site.', 'dynamic-link-hub' )
						),
						settingsUrl
							? el(
									ExternalLink,
									{ href: settingsUrl },
									__( 'Edit Link Hub settings', 'dynamic-link-hub' )
							  )
							: null
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'dynamic-link-hub/hub',
						attributes: props.attributes,
					} )
				)
			);
		},
		save: function () {
			// Server-rendered block: markup always comes from PHP.
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.serverSideRender,
	window.wp.components,
	window.wp.i18n
);
