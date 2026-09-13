<?php
/**
 * Button Action regression fixture (dev-only).
 *
 * Renders a togglable Dialog Button Group through do_blocks() and checks the
 * three contracts the Action axis depends on:
 *
 *   - an action that runs its own click is never overwritten by actions.toggle;
 *   - a stored <a> with an action renders as a <button>;
 *   - an overlay with no template renders neither ARIA nor a surface.
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-dialogs/tests/audit-button-action.php
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_button_action_results = array();

function ax_button_action_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * The attributes of each `.wp-block-button__link`, in order.
 *
 * @param string $html Rendered markup.
 * @return array<int, array<string, string|bool|null>> Tag plus the attributes the checks read.
 */
function ax_button_action_controls( string $html ) : array {
	$controls = array();
	$tags     = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( array( 'class_name' => 'wp-block-button__link' ) ) ) {
		$control = array( 'tag' => $tags->get_tag() );
		foreach ( array( 'href', 'aria-haspopup', 'aria-controls', 'aria-pressed', 'data-wp-on--click' ) as $name ) {
			$control[ $name ] = $tags->get_attribute( $name );
		}
		$controls[] = $control;
	}
	return $controls;
}

// A real Overlay template, so the opening case is the one that renders a surface.
$ax_button_action_parts = get_block_templates( array( 'area' => 'navigation-overlay' ), 'wp_template_part' );
$ax_button_action_slug  = $ax_button_action_parts ? $ax_button_action_parts[0]->slug : '';
ax_button_action_assert( $ax_button_action_results, 'a navigation-overlay template part exists to open', '' !== $ax_button_action_slug );

// The group's saved wrapper is part of the fixture: without it the group's
// filter stamps its context on the first button instead of the group.
$ax_button_action_html = do_blocks(
	'<!-- wp:axismundi/dialog-button-group {"togglable":true} -->'
	. '<div class="wp-block-axismundi-dialog-button-group wp-block-buttons">'
	. '<!-- wp:axismundi/dialog-icon-button {"action":"overlay","actionTarget":"' . $ax_button_action_slug . '","tagName":"a","url":"https://example.com/","text":"Menu","icon":"menu"} /-->'
	. '<!-- wp:axismundi/dialog-button {"action":"overlay-close","tagName":"a","url":"https://example.com/","text":"Close"} /-->'
	. '<!-- wp:axismundi/dialog-icon-button {"action":"overlay","text":"No target","icon":"menu"} /-->'
	. '<!-- wp:axismundi/dialog-icon-button {"text":"Star","icon":"star"} /-->'
	. '</div>'
	. '<!-- /wp:axismundi/dialog-button-group -->'
);
list( $ax_open, $ax_close, $ax_untargeted, $ax_toggle ) = array_pad( ax_button_action_controls( $ax_button_action_html ), 4, array() );

ax_button_action_assert(
	$ax_button_action_results,
	'Open Overlay template keeps actions.open inside a togglable group',
	'actions.open' === ( $ax_open['data-wp-on--click'] ?? null ) && null === ( $ax_open['aria-pressed'] ?? null )
);
ax_button_action_assert(
	$ax_button_action_results,
	'Navigation overlay close keeps actions.close inside a togglable group',
	'actions.close' === ( $ax_close['data-wp-on--click'] ?? null ) && null === ( $ax_close['aria-pressed'] ?? null )
);
ax_button_action_assert(
	$ax_button_action_results,
	'a stored <a> with Open Overlay template renders as a <button> without href',
	'BUTTON' === ( $ax_open['tag'] ?? null ) && null === ( $ax_open['href'] ?? null ) && 'dialog' === ( $ax_open['aria-haspopup'] ?? null )
);
ax_button_action_assert(
	$ax_button_action_results,
	'a stored <a> with Navigation overlay close renders as a <button> without href',
	'BUTTON' === ( $ax_close['tag'] ?? null ) && null === ( $ax_close['href'] ?? null )
);
ax_button_action_assert(
	$ax_button_action_results,
	'an overlay with no template renders no ARIA, no directive and no toggle',
	'BUTTON' === ( $ax_untargeted['tag'] ?? null )
		&& null === ( $ax_untargeted['aria-haspopup'] ?? null )
		&& null === ( $ax_untargeted['aria-controls'] ?? null )
		&& null === ( $ax_untargeted['data-wp-on--click'] ?? null )
		&& null === ( $ax_untargeted['aria-pressed'] ?? null )
);
ax_button_action_assert(
	$ax_button_action_results,
	'only the targeted overlay renders a surface',
	1 === preg_match_all( '/class="ax-overlay"/', $ax_button_action_html )
);
ax_button_action_assert(
	$ax_button_action_results,
	'a button with no action is still the group toggle',
	'actions.toggle' === ( $ax_toggle['data-wp-on--click'] ?? null ) && null !== ( $ax_toggle['aria-pressed'] ?? null )
);

$ax_button_action_failures = count( array_filter( $ax_button_action_results, static fn( bool $passed ) : bool => ! $passed ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_button_action_results ), $ax_button_action_failures );
exit( $ax_button_action_failures > 0 ? 1 : 0 );
