<?php
/** Object media dialog variant + shared-contract regression fixture (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

$ax_omd_results = array();

function ax_omd_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Render the hub with one variant attribute. */
function ax_omd_render( $variant ) : string {
	return render_block(
		array(
			'blockName'    => 'axismundi/object-media-dialog',
			'attrs'        => null === $variant ? array() : array( 'variant' => $variant ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

/** The class list on the rendered `<dialog>`. */
function ax_omd_dialog_classes( string $html ) : string {
	return preg_match( '/<dialog[^>]*class="([^"]*)"/', $html, $m ) ? $m[1] : '';
}

$ax_omd_basic = ax_omd_dialog_classes( ax_omd_render( 'basic' ) );
$ax_omd_full  = ax_omd_dialog_classes( ax_omd_render( 'fullscreen' ) );

ax_omd_assert(
	$ax_omd_results,
	'the contained variant is marked and does not borrow the M3 basic-dialog geometry',
	str_contains( $ax_omd_basic, 'is-variant-basic' )
		// `ax-dialog--basic` caps at 560px, a width a media viewer cannot use.
		&& ! str_contains( $ax_omd_basic, 'ax-dialog--basic' )
		&& ! str_contains( $ax_omd_basic, 'ax-dialog--full-screen' )
);

ax_omd_assert(
	$ax_omd_results,
	'the full-screen variant also carries the shared class so a theme styling either dialog reaches both',
	str_contains( $ax_omd_full, 'is-variant-fullscreen' ) && str_contains( $ax_omd_full, 'ax-dialog--full-screen' )
);

ax_omd_assert(
	$ax_omd_results,
	'an unknown variant falls back to the contained dialog rather than rendering unsized',
	str_contains( ax_omd_dialog_classes( ax_omd_render( 'sideways' ) ), 'is-variant-basic' )
		&& str_contains( ax_omd_dialog_classes( ax_omd_render( null ) ), 'is-variant-basic' )
);

/*
 * The block stylesheet must carry the full-screen geometry itself. A block stylesheet
 * only loads when its own block renders, so a page holding this dialog without an
 * `axismundi/dialog` would otherwise apply `ax-dialog--full-screen` with no rules behind
 * it and the dialog would shrink to its contents.
 */
$ax_omd_css = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/object-media-dialog/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled asset read.
$ax_omd_view = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/object-media-dialog/view.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled asset read.
ax_omd_assert(
	$ax_omd_results,
	'the block owns its full-screen geometry instead of depending on a sibling block stylesheet',
	str_contains( $ax_omd_css, '.ax-object-media-dialog.is-variant-fullscreen' )
		&& preg_match( '/\.ax-object-media-dialog\.is-variant-fullscreen\s*\{[^}]*block-size:\s*100dvh/', $ax_omd_css ) === 1
);

/*
 * The shared dialog base resets `margin: 0`, which removes the centring a browser gives a
 * modal `<dialog>` for free. A contained variant that does not restate `inset: 0` and
 * `margin: auto` renders in the top-left corner — and because it is still the right size,
 * a size-only check passes while the dialog sits in the wrong place.
 */
ax_omd_assert(
	$ax_omd_results,
	'the contained variant restates the centring the shared base resets',
	preg_match( '/\.ax-object-media-dialog\.is-variant-basic\s*\{[^}]*inset:\s*0/', $ax_omd_css ) === 1
		&& preg_match( '/\.ax-object-media-dialog\.is-variant-basic\s*\{[^}]*margin:\s*auto/', $ax_omd_css ) === 1
);

/*
 * The runtime toggles the shared `ax-dialog-scroll-locked` class, which is defined in
 * assets/shared.css. Without that stylesheet registered for this block the lock is a
 * class with no rule and the page scrolls behind an open dialog.
 */
$ax_omd_styles = wp_styles();
ax_omd_assert(
	$ax_omd_results,
	'the shared dialog stylesheet is registered for this block, so its scroll lock has a rule',
	isset( $ax_omd_styles->registered['axismundi-dialogs-shared'] )
		|| false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/axismundi-dialogs.php' ), "wp_enqueue_block_style( 'axismundi/object-media-dialog'" ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled source read.
);
ax_omd_assert(
	$ax_omd_results,
	'the dialog removes gallery crop and aspect-ratio overrides from cloned media while retaining its contain rule',
	false !== strpos( $ax_omd_view, "media.style.removeProperty( 'aspect-ratio' )" )
		&& false !== strpos( $ax_omd_view, "media.style.removeProperty( 'object-fit' )" )
		&& preg_match( '/\.ax-object-media-dialog__media \.axismundi-object__carousel-slide img,\s*\.ax-object-media-dialog__media \.axismundi-object__carousel-slide video\s*\{[^}]*object-fit:\s*contain/', $ax_omd_css ) === 1
);

/*
 * Cloning is what makes sensitivity correct for free, but it also carries the gallery's
 * own presentation state across. The preview limit is one of those: the dialog holds
 * every attachment, so a "+N more" badge would sit over the first picture counting items
 * the reader can already reach, and the overflow class marks slides the gallery chose
 * not to show. Both have to be dropped from the clone — the media and its sensitive
 * overlay must not be.
 */
ax_omd_assert(
	$ax_omd_results,
	'the clone drops the gallery preview-limit badge and overflow marker but keeps the media',
	false !== strpos( $ax_omd_view, ".axismundi-object__attachment-more' ).forEach" )
		&& false !== strpos( $ax_omd_view, "clone.classList.remove( 'is-preview-overflow' )" )
		// The sensitive overlay is deliberately not stripped: opening a dialog is not
		// answering a content warning.
		&& false === strpos( $ax_omd_view, 'ax-media-sensitive' )
);

$ax_omd_failed = count( array_filter( $ax_omd_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n%d/%d passed\n", count( $ax_omd_results ) - $ax_omd_failed, count( $ax_omd_results ) );
exit( $ax_omd_failed > 0 ? 1 : 0 );
