/**
 * axismundi/feed-pagination editor registration (no build step).
 *
 * A preview of the control, not of the answer. Whether there is a next page, and what cursor
 * addresses it, are answers to a query the editor has not run — but which *kind* of control this
 * is was decided by the feed, and an author placing it should see the thing they are placing.
 *
 * The shape follows the feed's `navigation` through block context rather than a setting of its
 * own. One decision, in one place: a pager with its own switch could be set to number a list the
 * loop is continuing by cursor, and the editor would show the disagreement as if it were a design.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	/* The numbered shape, in the classes the theme already styles for `core/query-pagination`. */
	function numbered() {
		return el(
			'nav',
			{ className: 'wp-block-query-pagination axismundi-feed-pagination is-navigation-pagination' },
			el( 'span', { className: 'wp-block-query-pagination-previous axismundi-feed-pagination__previous' }, __( 'Newer', 'axismundi-activities' ) ),
			el( 'span', { className: 'wp-block-query-pagination-numbers axismundi-feed-pagination__numbers' }, __( 'Page 1 of 4', 'axismundi-activities' ) ),
			el( 'span', { className: 'wp-block-query-pagination-next axismundi-feed-pagination__next' }, __( 'Older', 'axismundi-activities' ) )
		);
	}

	/* The cursor shape: one control that continues the feed in place. */
	function cursor() {
		return el(
			'nav',
			{ className: 'axismundi-feed-pagination axismundi-activity-feed__pagination is-navigation-infinite' },
			el(
				'span',
				{ className: 'axismundi-feed-pagination__more axismundi-activity-feed__more-link' },
				el( 'span', { className: 'axismundi-feed-pagination__more-label' }, __( 'Load more', 'axismundi-activities' ) )
			)
		);
	}

	blocks.registerBlockType( 'axismundi/feed-pagination', {
		usesContext: [ 'axismundi/feedNavigation' ],
		edit: function ( props ) {
			/*
			 * Automatic is shown as the cursor control, because that is what an Actor timeline is
			 * and what an unnamed mode resolves to on every surface that offers both. It is a
			 * preview of the likely answer, not a claim about a surface the editor cannot see.
			 */
			var mode = props.context[ 'axismundi/feedNavigation' ] || '';
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-pagination-preview' } ),
				'pagination' === mode ? numbered() : cursor()
			);
		},
		// Dynamic: the block renders the control, this only records where it belongs.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
