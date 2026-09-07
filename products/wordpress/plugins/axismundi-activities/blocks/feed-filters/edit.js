/**
 * axismundi/feed-filters editor registration (no build step).
 *
 * The containing feed-tab provides its surface, so the editor can show the same control shape an
 * author is placing: Activity's disclosure or Community's filter tabs. The controls are static
 * previews because their values belong to a reader's URL, not to this saved template.
 */
( function ( blocks, blockEditor, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;

	function activityPreview() {
		return el(
			'div',
			{ className: 'axismundi-activity-feed__filters axismundi-feed-filters-preview__activity', 'aria-hidden': 'true' },
			el(
				'span',
				{ className: 'axismundi-activity-feed__filters-trigger' },
				__( 'Posts and boosts', 'axismundi-activities' ),
				el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, 'arrow_drop_down' )
			)
		);
	}

	function communityPreview() {
		return el(
			'nav',
			{ className: 'axismundi-feed-filters__views', 'aria-hidden': 'true' },
			el( 'span', { className: 'axismundi-feed-filters__view is-current' }, __( 'Posts', 'axismundi-activities' ) ),
			el( 'span', { className: 'axismundi-feed-filters__view' }, __( 'Comments', 'axismundi-activities' ) )
		);
	}

	blocks.registerBlockType( 'axismundi/feed-filters', {
		usesContext: [ 'axismundi/feedSurface', 'axismundi/feedFilterStyle' ],
		edit: function ( props ) {
			/*
			 * Which shape, read from the tab's declaration rather than guessed from its surface.
			 *
			 * Guessing was wrong for the case that matters: a Person's community tab offers the same
			 * two switches the timeline does, so a preview keyed on the surface name showed tabs
			 * where the page renders a disclosure. The surface is still the fallback, because that
			 * is what an undeclared tab resolves to on the server.
			 */
			var style = props.context[ 'axismundi/feedFilterStyle' ] || '';
			var surface = props.context[ 'axismundi/feedSurface' ] || 'activity';
			var community = 'tabs' === style || ( '' === style && 'activity' !== surface );
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'axismundi-feed-slot' } ),
				community ? communityPreview() : activityPreview(),
				el(
					'span',
					{ className: 'screen-reader-text' },
					community
						? __( 'Community feed filters preview. Posts is selected.', 'axismundi-activities' )
						: __( 'Activity feed filters preview.', 'axismundi-activities' )
				)
			);
		},
		// Dynamic: the loop renders the controls, this only records where they belong.
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
