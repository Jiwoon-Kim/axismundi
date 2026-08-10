/**
 * Editor side of the Calendar block.
 *
 * The grid is rendered by PHP, so the editor asks the server for it rather than reimplementing the
 * layout in JavaScript. Two implementations of one calendar drift, and the one in the editor is the
 * one nobody checks against real data.
 *
 * No JSX, no build -- plain wp.element.createElement, as the Event panel does.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'axismundi-calendar/calendar', {
		edit: function ( props ) {
			var blockProps = wp.blockEditor.useBlockProps();
			var controls = el(
				wp.blockEditor.InspectorControls,
				{ key: 'inspector' },
				el(
					C.PanelBody,
					{ title: __( 'Calendar', 'axismundi-calendar' ) },
					el( C.SelectControl, {
						label: __( 'View', 'axismundi-calendar' ),
						value: props.attributes.view,
						options: [
							{ label: __( 'Month', 'axismundi-calendar' ), value: 'month' },
							{ label: __( 'Week', 'axismundi-calendar' ), value: 'week' }
						],
						onChange: function ( view ) { props.setAttributes( { view: view } ); }
					} )
				)
			);

			var preview = ServerSideRender
				? el( ServerSideRender, { block: 'axismundi-calendar/calendar', attributes: props.attributes } )
				: el( C.Placeholder, { label: __( 'Calendar', 'axismundi-calendar' ) }, __( 'The calendar is shown on the site.', 'axismundi-calendar' ) );

			return el( 'div', blockProps, [ controls, el( 'div', { key: 'preview' }, preview ) ] );
		},
		save: function () {
			// Dynamic: the grid depends on when it is viewed, so nothing is stored in post content.
			return null;
		}
	} );
} )( window.wp );
