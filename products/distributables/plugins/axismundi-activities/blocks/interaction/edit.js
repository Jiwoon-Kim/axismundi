/**
 * axismundi/interaction editor registration (no build step).
 *
 * The preview draws the same control the front end will, from the same type list the server
 * renders from, so an author picking "Announce" sees an Announce. Counts and selected state are
 * not editable: they come from the Activity ledger at render time.
 *
 * Each type is also a block variation, which is what puts them in the inserter as separate items
 * the way `core/post-terms` does for taxonomies — one block, several ways in.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	var TYPES = [
		{ name: 'reply', title: __( 'Reply', 'axismundi-activities' ), icon: 'reply', label: __( 'Reply', 'axismundi-activities' ) },
		{ name: 'like', title: __( 'Like', 'axismundi-activities' ), icon: 'favorite', label: __( 'Like', 'axismundi-activities' ) },
		{ name: 'announce', title: __( 'Announce', 'axismundi-activities' ), icon: 'sync', label: __( 'Announce', 'axismundi-activities' ) },
		{ name: 'quote', title: __( 'Quote', 'axismundi-activities' ), icon: 'format_quote', label: __( 'Quote', 'axismundi-activities' ) },
		{ name: 'reaction', title: __( 'Reaction', 'axismundi-activities' ), icon: 'add_reaction', label: __( 'React', 'axismundi-activities' ) },
		/*
		 * The one type that is several controls rather than one.
		 *
		 * A vote is a like and a dislike either side of the score they produce, and the server
		 * describes it that way. Drawing only the first of those here left an author placing the
		 * block looking at a lone thumb-up and reasonably concluding the downvote was missing or
		 * broken -- the preview disagreeing with the page it previews, which is the one thing it
		 * exists not to do.
		 */
		{
			name: 'vote',
			title: __( 'Vote', 'axismundi-activities' ),
			icon: 'thumb_up',
			label: __( 'Vote', 'axismundi-activities' ),
			groupLabel: __( 'Community vote', 'axismundi-activities' ),
			controls: [ { icon: 'thumb_up' }, { value: '0' }, { icon: 'thumb_down' } ]
		}
	];

	function typeFor( name ) {
		for ( var i = 0; i < TYPES.length; i++ ) {
			if ( TYPES[ i ].name === name ) {
				return TYPES[ i ];
			}
		}
		return TYPES[ 1 ];
	}

	blocks.registerBlockType( 'axismundi/interaction', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var type = typeFor( attributes.type );
			var size = 'xs' === attributes.size ? 'xs' : 'sm';
			var children = [ el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true', key: 'icon' }, type.icon ) ];
			if ( attributes.showLabel ) {
				children.push( el( 'span', { className: 'axismundi-interaction__label', key: 'label' }, type.label ) );
			}
			if ( false !== attributes.showCount ) {
				children.push( el( 'span', { className: 'axismundi-interaction__count', key: 'count' }, '0' ) );
			}
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					{ key: 'inspector' },
					el(
						components.PanelBody,
						{ title: __( 'Interaction', 'axismundi-activities' ) },
						el( components.SelectControl, {
							label: __( 'Type', 'axismundi-activities' ),
							value: type.name,
							options: TYPES.map( function ( item ) {
								return { label: item.title, value: item.name };
							} ),
							onChange: function ( value ) {
								setAttributes( { type: value } );
							}
						} ),
						el( components.SelectControl, {
							label: __( 'Size', 'axismundi-activities' ),
							value: size,
							options: [
								{ label: __( 'Extra small', 'axismundi-activities' ), value: 'xs' },
								{ label: __( 'Small', 'axismundi-activities' ), value: 'sm' }
							],
							onChange: function ( value ) {
								setAttributes( { size: value } );
							}
						} ),
						el( components.ToggleControl, {
							label: __( 'Show label', 'axismundi-activities' ),
							checked: !! attributes.showLabel,
							onChange: function ( value ) {
								setAttributes( { showLabel: !! value } );
							}
						} ),
						el( components.ToggleControl, {
							label: __( 'Show count', 'axismundi-activities' ),
							checked: false !== attributes.showCount,
							onChange: function ( value ) {
								setAttributes( { showCount: !! value } );
							}
						} ),
						'announce' === type.name
							? el( components.ToggleControl, {
								label: __( 'Offer repost and quote in a menu', 'axismundi-activities' ),
								checked: !! attributes.announceMenu,
								onChange: function ( value ) {
									setAttributes( { announceMenu: !! value } );
								}
							} )
							: null
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'axismundi-interaction is-type-' + type.name } ),
					type.controls
						? el(
							'div',
							{ className: 'axismundi-interaction__group', role: 'group', 'aria-label': type.groupLabel },
							type.controls.map( function ( control, index ) {
								// A value entry is the score between the two sides, not a control.
								if ( undefined !== control.value ) {
									return el( 'span', { className: 'axismundi-interaction__value', key: 'value-' + index }, control.value );
								}
								return el(
									'button',
									{
										type: 'button',
										className: 'wp-element-button axismundi-interaction__button is-size-' + size,
										disabled: true,
										key: 'control-' + index
									},
									el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, control.icon )
								);
							} )
						)
						: el(
							'button',
							{
								type: 'button',
								className: 'wp-element-button axismundi-interaction__button is-size-' + size,
								disabled: true
							},
							children
						)
				)
			);
		},
		save: function () {
			return null;
		}
	} );

	TYPES.forEach( function ( item ) {
		blocks.registerBlockVariation( 'axismundi/interaction', {
			name: item.name,
			title: item.title,
			description: item.title,
			attributes: { type: item.name },
			scope: [ 'inserter' ],
			isActive: [ 'type' ]
		} );
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
