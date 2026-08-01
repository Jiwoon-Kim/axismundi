/**
 * axismundi/actor-projections editor registration (no build step).
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	function Header( type ) {
		return el(
			'header',
			{ className: 'ax-actor-projections__preview-header' },
			el( 'span', { className: 'ax-actor-projections__preview-avatar', 'aria-hidden': true }, 'A' ),
			el(
				'div',
				{ className: 'ax-actor-projections__preview-author' },
				el( 'strong', {}, __( 'Actor display name', 'axismundi-actors' ) ),
				el( 'span', {}, '@actor@example.test' )
			),
			el(
				'div',
				{ className: 'ax-actor-projections__preview-meta' },
				el( 'span', {}, type ),
				el( 'span', {}, __( 'Today', 'axismundi-actors' ) )
			)
		);
	}

	function Card( type, body ) {
		return el(
			'article',
			{ className: 'axismundi-object-card ax-actor-projections__preview-card ax-actor-projections__preview-card--' + type.toLowerCase() },
			Header( type ),
			body
		);
	}

	function Preview() {
		return el(
			'div',
			{ className: 'ax-actor-projections__preview' },
			Card(
				__( 'Article', 'axismundi-actors' ),
				el( element.Fragment, {},
					el( 'h3', {}, __( 'A field guide to connected places', 'axismundi-actors' ) ),
					el( 'p', {}, __( 'A longer-form piece begins with a title and a short summary, then continues on its own page.', 'axismundi-actors' ) ),
					el( 'span', { className: 'ax-actor-projections__preview-more' }, __( 'Read more', 'axismundi-actors' ) )
				)
			),
			Card(
				__( 'Note', 'axismundi-actors' ),
				el( 'p', {}, __( 'A short update belongs directly in the timeline, with the full thought already present in the card.', 'axismundi-actors' ) )
			),
			Card(
				__( 'Question', 'axismundi-actors' ),
				el( element.Fragment, {},
					el( 'h3', {}, __( 'Where should the next meetup be?', 'axismundi-actors' ) ),
					el(
						'ul',
						{ className: 'ax-actor-projections__preview-poll' },
						el( 'li', {}, el( 'span', {}, __( 'Near the river', 'axismundi-actors' ) ), el( 'strong', {}, '72%' ) ),
						el( 'li', {}, el( 'span', {}, __( 'Near the station', 'axismundi-actors' ) ), el( 'strong', {}, '28%' ) )
					)
				)
			),
			Card(
				__( 'Quote', 'axismundi-actors' ),
				el( element.Fragment, {},
					el( 'p', {}, __( 'A response can carry its own words while preserving a readable reference to the original.', 'axismundi-actors' ) ),
					el( 'blockquote', {}, el( 'p', {}, __( 'Good community space makes room for a thought to travel.', 'axismundi-actors' ) ), el( 'cite', {}, '@quoted@example.test' ) )
				)
			)
		);
	}

	blocks.registerBlockType( 'axismundi/actor-projections', {
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps(),
				Preview()
			);
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n );
