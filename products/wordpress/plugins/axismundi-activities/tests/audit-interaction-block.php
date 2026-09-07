<?php
/**
 * Unified interaction block regression (dev-only; dist-excluded).
 *
 * Six blocks each drew their own button and their six stylesheets had already drifted. This locks
 * the contract that replaced them: a type says what it is, the block says what a button is, and
 * neither reaches into the other.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ib_results = array();
$ax_ib_users   = array();
$ax_ib_posts   = array();
$ax_ib_ids     = array();

/** @param bool[] $results Results. */
function ax_ib_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Read one attribute off the rendered control, without matching a binding of the same name. */
function ax_ib_button_attr( string $html, string $attribute ) : string {
	if ( ! preg_match( '#<button\b([^>]*)>#', $html, $tag ) ) {
		return '';
	}
	return preg_match( '#(?<![-\w])' . preg_quote( $attribute, '#' ) . '="([^"]*)"#', $tag[1], $found ) ? $found[1] : '';
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axib_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ib_users[] = $owner;
	wp_set_current_user( $owner );
	$actor       = axismundi_actors_ensure_for_user( $owner );
	$ax_ib_ids[] = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axib' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_act_current_local_actor();

	$note          = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Interaction target.</p>' ) );
	$ax_ib_posts[] = $note;
	$saved         = axismundi_note_save( $note, array( 'visibility' => 'public' ) );
	if ( ! is_wp_error( $saved ) ) {
		wp_update_post( array( 'ID' => $note, 'post_status' => 'publish' ) );
	}
	$envelope   = axismundi_note_get( $note );
	$object_uri = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';
	$markup     = '<!-- wp:axismundi/interaction {"type":"like","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->';

	ax_ib_assert(
		$ax_ib_results,
		'Like and Dislike are offered through the registry rather than being built into the block',
		array_key_exists( 'like', axismundi_act_interaction_types() )
			&& is_callable( axismundi_act_interaction_types()['like']['describe'] )
			&& array_key_exists( 'dislike', axismundi_act_interaction_types() )
			&& is_callable( axismundi_act_interaction_types()['dislike']['describe'] )
	);

	// A type nobody registered is not a broken button, it is no button.
	ax_ib_assert(
		$ax_ib_results,
		'an unregistered type renders nothing at all',
		'' === trim( do_blocks( '<!-- wp:axismundi/interaction {"type":"nonesuch","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' ) )
	);

	/*
	 * The defect this block exists partly to fix. The control it replaced added its selected class
	 * at hydration, so a reader who had liked something was shown, until the page finished
	 * booting, that they had not — and was shown that forever with JavaScript off.
	 */
	$liked      = axismundi_act_like_object( $actor, $object_uri );
	$was_liked  = axismundi_act_get_like_state( $actor->get_uri(), $object_uri );
	$liked_html = do_blocks( $markup );
	axismundi_act_unlike_object( $actor, $object_uri );
	$now_liked  = axismundi_act_get_like_state( $actor->get_uri(), $object_uri );
	$plain_html = do_blocks( $markup );

	ax_ib_assert(
		$ax_ib_results,
		'the ledger really moved in both directions, so the markup below reflects a state and not a default',
		! is_wp_error( $liked ) && '' !== $object_uri && true === $was_liked && false === $now_liked
	);

	ax_ib_assert(
		$ax_ib_results,
		'selected state is in the markup the server sends, not added when the page boots',
		false !== strpos( ax_ib_button_attr( $liked_html, 'class' ), 'is-selected' )
			&& 'true' === ax_ib_button_attr( $liked_html, 'aria-pressed' )
			&& false === strpos( ax_ib_button_attr( $plain_html, 'class' ), 'is-selected' )
			&& 'false' === ax_ib_button_attr( $plain_html, 'aria-pressed' )
	);

	// Small is what every other button on the site is; extra small is chosen, never inherited.
	$xs_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"like","size":"xs","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	ax_ib_assert(
		$ax_ib_results,
		'an interaction is small unless it is asked to be extra small',
		false !== strpos( ax_ib_button_attr( $plain_html, 'class' ), 'is-size-sm' )
			&& false !== strpos( ax_ib_button_attr( $xs_html, 'class' ), 'is-size-xs' )
	);

	/*
	 * Two owners for one click is a bug you have to remember not to write. The delegated variant
	 * omits the directives instead of guarding them, so absent markup cannot double-fire.
	 */
	$GLOBALS['axismundi_op_object_template_options'] = array( 'interactionOwner' => 'feed' );
	$delegated_html = do_blocks( $markup );
	$GLOBALS['axismundi_op_object_template_options'] = array();

	ax_ib_assert(
		$ax_ib_results,
		'a feed-owned interaction carries its target as data and none of the interactive directives',
		false !== strpos( $delegated_html, 'data-ax-action="like"' )
			&& false === strpos( $delegated_html, 'data-wp-interactive' )
			&& false === strpos( $delegated_html, 'data-wp-on--click' )
			&& false !== strpos( $plain_html, 'data-wp-interactive' )
			&& false === strpos( $plain_html, 'data-ax-action' )
	);

	/*
	 * The reason the six stylesheets drifted: each owned properties the theme also owned, and at
	 * equal specificity the later sheet won silently. This block owns appearance and size, and
	 * shape and motion arrive through `wp-element-button` — so those must not appear here at all.
	 */
	$stylesheet = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/interaction/style.css' );
	ax_ib_assert(
		$ax_ib_results,
		'the block never redeclares the shape the theme owns, nor sizes icons behind the icon system',
		// The command-menu surface has its own radius. Only the interaction button must inherit
		// shape from the theme's core-button bridge.
		0 === preg_match( '#\.axismundi-interaction[^}]*\.axismundi-interaction__button[^}]*border-radius#s', $stylesheet )
			// The theme's icon rule reads `--md-icon-size`; setting `font-size` on the glyph both
			// fights that contract and silently changes the size.
			&& 0 === preg_match( '#\.material-symbols-outlined[^}]*font-size#', $stylesheet )
			&& false !== strpos( $stylesheet, '--md-icon-size' )
			&& false !== strpos( $stylesheet, '--md-sys-state-hover-state-layer-opacity' )
	);

	/*
	 * The affordances that let a reader tell one control from another without reading it. Each
	 * belonged to one of the six blocks and would have been lost with them; they hang off the
	 * type now, which is where they always belonged.
	 */
	ax_ib_assert(
		$ax_ib_results,
		'each interaction keeps the gesture it is recognised by',
		false !== strpos( $stylesheet, 'is-type-like' ) && false !== strpos( $stylesheet, '--md-icon-fill: 1' )
			&& false !== strpos( $stylesheet, 'color--error' )
			&& false !== strpos( $stylesheet, 'is-type-announce' ) && false !== strpos( $stylesheet, 'rotate(-180deg)' )
	);

	ax_ib_assert(
		$ax_ib_results,
		'a selected toggle keeps the same state layer after hover ends',
		1 === preg_match(
			'#\.axismundi-interaction \.axismundi-interaction__button\.is-selected\s*\{\s*background-color:\s*color-mix\(in srgb, currentColor calc\(var\(--md-sys-state-hover-state-layer-opacity, 0\.08\) \* 100%\), transparent\);#s',
			$stylesheet
		)
	);

	ax_ib_assert(
		$ax_ib_results,
		'a community vote is one connected up-score-down control while its buttons retain the theme shape',
		1 === preg_match(
			'#\.axismundi-interaction\.is-type-vote \.axismundi-interaction__group\s*\{\s*display:\s*inline-flex;\s*align-items:\s*center;\s*gap:\s*2px;\s*padding:\s*2px;\s*border:#s',
			$stylesheet
		)
			&& false !== strpos( $stylesheet, 'font-variant-numeric: tabular-nums' )
	);

	ax_ib_assert(
		$ax_ib_results,
		'the control carries the core button class so the theme reaches it',
		false !== strpos( ax_ib_button_attr( $plain_html, 'class' ), 'wp-element-button' )
	);

	/*
	 * A Reply goes somewhere; a Like changes something. An anchor that acts and a button that
	 * navigates both mislead anyone not using a mouse, so the tag follows the act — and only a
	 * two-state control claims `aria-pressed`, because promising a pressed state on a Reply
	 * describes a state it does not have.
	 */
	$reply_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"reply","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	ax_ib_assert(
		$ax_ib_results,
		'Reply is a link for a reader who can use it and never claims a pressed state',
		'reply' === ( array_key_exists( 'reply', axismundi_act_interaction_types() ) ? 'reply' : '' )
			&& 1 === preg_match( '#<a\b[^>]*href="#', $reply_html )
			&& '' === ax_ib_button_attr( $reply_html, 'aria-pressed' )
			&& '' !== ax_ib_button_attr( $plain_html, 'aria-pressed' )
	);

	/*
	 * Quote is Reply's twin: both open a composer with one field already filled in, so both are
	 * navigation. Neither has a pressed state — quoting makes a new Object of the reader's own,
	 * and whether they have done it before is not a property of what they quoted.
	 */
	$quote_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"quote","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	ax_ib_assert(
		$ax_ib_results,
		'Quote opens a composer prefilled with what is being quoted, and claims no pressed state',
		array_key_exists( 'quote', axismundi_act_interaction_types() )
			&& 1 === preg_match( '#<a\b[^>]*href="[^"]*ax_quote_target#', $quote_html )
			&& '' === ax_ib_button_attr( $quote_html, 'aria-pressed' )
	);

	/*
	 * The reaction trigger is an ordinary control with a popover hanging off it. It is a dialog
	 * rather than a menu because it holds a search field, a jump strip and a grid — the up/down
	 * command model belongs to the Announce chooser, and this does not claim it. It has no pressed
	 * state either: it opens something, and what the reader already reacted with is the chip row's
	 * job.
	 *
	 * The popover ships closed. Both halves of that were being stripped by server-side directive
	 * processing, so every picker on a page arrived open.
	 */
	$reaction_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"reaction","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	$reaction_picker = preg_match( '#<div class="axismundi-reaction-button__picker"([^>]*)>#', $reaction_html, $picker ) ? $picker[1] : '';
	ax_ib_assert(
		$ax_ib_results,
		'the reaction picker hangs off its control, ships closed, and claims a dialog rather than a toggle',
		array_key_exists( 'reaction', axismundi_act_interaction_types() )
			&& false !== strpos( $reaction_html, 'is-type-reaction' )
			// On the wrapper, not the button: the lifecycle callback measures the trigger against
			// the popover and needs the element holding both. Without it the picker was shown and
			// never positioned, which looks exactly like a picker that does not open.
			&& 1 === preg_match( '#<div class="axismundi-interaction[^"]*"[^>]*data-wp-watch="callbacks\.pickerLifecycle"#', $reaction_html )
			&& 'dialog' === ax_ib_button_attr( $reaction_html, 'aria-haspopup' )
			&& 'false' === ax_ib_button_attr( $reaction_html, 'aria-expanded' )
			&& '' === ax_ib_button_attr( $reaction_html, 'aria-pressed' )
			&& '' !== $reaction_picker
			&& 1 === preg_match( '#(?<![-\w])hidden(?=[\s>])#', $reaction_picker )
	);

	/*
	 * A control that mutates has to carry two things or it only looks like it worked: the store
	 * that answers its clicks, and a binding on every part that changes as a result. Without the
	 * store nothing happens at all; without the binding the server is right and the page is stale
	 * until someone reloads it.
	 */
	ax_ib_assert(
		$ax_ib_results,
		'Like and Dislike each bring their own store and keep their own count current without a reload',
		// Rendered here rather than reused, so the reader this markup describes is unambiguous.
		( static function () use ( $object_uri ) : bool {
			$like     = do_blocks( '<!-- wp:axismundi/interaction {"type":"like","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
			$dislike  = do_blocks( '<!-- wp:axismundi/interaction {"type":"dislike","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
			$announce = do_blocks( '<!-- wp:axismundi/interaction {"type":"announce","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
			return false !== strpos( $like, 'data-wp-interactive="axismundi/like-button"' )
				&& false !== strpos( $like, 'data-wp-text="context.likes"' )
				&& false !== strpos( $dislike, 'data-wp-interactive="axismundi/dislike-button"' )
				&& false !== strpos( $dislike, 'data-wp-text="context.dislikes"' )
				&& false !== strpos( $announce, 'data-wp-text="context.announces"' );
		} )()
			// The stores those directives are useless without. They moved out of block directories
			// that were then deleted, so their absence is a thing that has already happened once.
			&& is_readable( dirname( __DIR__ ) . '/assets/interactions/like.js' )
			&& is_readable( dirname( __DIR__ ) . '/assets/interactions/dislike.js' )
			&& is_readable( dirname( __DIR__ ) . '/assets/interactions/announce.js' )
			&& false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/assets/interactions/like.js' ), "store( 'axismundi/like-button'" )
			&& false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/assets/interactions/dislike.js' ), "store( 'axismundi/dislike-button'" )
	);

	/*
	 * Two controls for one Object must not be one control.
	 *
	 * A feed can show the same Object twice — an original and someone's boost of it — and these
	 * ids were once derived from the Object, so both cards claimed the same picker and the same
	 * menu. Opening either opened all of them, and the duplicated `id` left anything pointing at
	 * one aiming for whichever came first. On this site's own timeline that meant six.
	 *
	 * The feed audit normalises these ids away when it compares cards, which is right there and
	 * exactly why the property has to be held here: nothing else would notice them collapsing
	 * back into one.
	 */
	$ax_ib_twice = do_blocks(
		'<!-- wp:axismundi/interaction {"type":"reaction","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->'
		. '<!-- wp:axismundi/interaction {"type":"reaction","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->'
		. '<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->'
		. '<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->'
	);
	preg_match_all( '#\bid="(ax-rx-[^"]+|ax-announce-menu-[^"]+)"#', $ax_ib_twice, $ax_ib_ids );
	$ax_ib_emitted = (array) ( $ax_ib_ids[1] ?? array() );

	ax_ib_assert(
		$ax_ib_results,
		'the same Object rendered twice gets two controls, not one wearing two hats',
		// Four controls were asked for and at least the two popovers must have identified
		// themselves, so an empty match cannot pass this by finding nothing to disagree about.
		count( $ax_ib_emitted ) >= 2
			&& count( $ax_ib_emitted ) === count( array_unique( $ax_ib_emitted ) )
			&& 2 === substr_count( $ax_ib_twice, 'is-type-reaction' )
			&& 2 === substr_count( $ax_ib_twice, 'is-type-announce' )
	);

	/*
	 * The Interactivity API evaluates directives on the server too. Binding an attribute to a
	 * `state` getter that only exists in the JavaScript module makes the server resolve it to
	 * nothing and strip the attribute — so a control rendered disabled arrived enabled, which is
	 * what the block this replaces did. The value lives on the context, which means the same
	 * thing in both places.
	 */
	wp_set_current_user( 0 );
	$anon_like  = do_blocks( $markup );
	$anon_reply = do_blocks( '<!-- wp:axismundi/interaction {"type":"reply","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	$anon_menu = do_blocks( '<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	wp_set_current_user( $owner );

	/*
	 * Announce is two interactions wearing one control: with the menu it offers Repost and Quote,
	 * without it it reposts directly. A menu of two verbs is a menu, not the dialog the emoji
	 * picker is — that one holds a search field and a grid, and the picker's own reasoning is what
	 * settles this.
	 */
	$menu_html   = do_blocks( '<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	$direct_html = do_blocks( '<!-- wp:axismundi/interaction {"type":"announce","objectUri":"' . esc_url_raw( $object_uri ) . '"} /-->' );
	$controls    = preg_match( '#aria-controls="([^"]*)"#', $menu_html, $found ) ? $found[1] : '';
	ax_ib_assert(
		$ax_ib_results,
		'Announce opens a menu of two commands when asked and reposts directly when not',
		false !== strpos( $menu_html, 'role="menu"' )
			&& 'menu' === ax_ib_button_attr( $menu_html, 'aria-haspopup' )
			&& false !== strpos( ax_ib_button_attr( $menu_html, 'data-wp-on--click' ), 'toggleMenu' )
			&& '' === ax_ib_button_attr( $direct_html, 'aria-haspopup' )
			&& false !== strpos( ax_ib_button_attr( $direct_html, 'data-wp-on--click' ), 'toggleAnnounce' )
	);

	// Advertising a popup that was not rendered, and pointing at an id that is not on the page,
	// describes a control that does not exist.
	ax_ib_assert(
		$ax_ib_results,
		'a menu is only advertised when one is actually on the page, and its target resolves',
		'' !== $controls
			&& false !== strpos( $menu_html, 'id="' . $controls . '"' )
			&& '' === ax_ib_button_attr( $anon_menu, 'aria-haspopup' )
			&& '' === ax_ib_button_attr( $anon_menu, 'aria-controls' )
			&& false === strpos( $anon_menu, 'role="menu"' )
	);

	ax_ib_assert(
		$ax_ib_results,
		'a visitor who cannot act is served a control that is still disabled after directives are processed',
		1 === preg_match( '#<button\b[^>]*\sdisabled[\s>]#', $anon_like )
			&& 1 === preg_match( '#<button\b[^>]*\sdisabled[\s>]#', $anon_reply )
			&& 0 === preg_match( '#<a\b[^>]*href="#', $anon_reply )
	);
} finally {
	$GLOBALS['axismundi_op_object_template_options'] = array();
	foreach ( array_unique( $ax_ib_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_ib_ids ) ) as $identity_id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_filter( array_unique( $ax_ib_users ) ) as $user_id ) {
		if ( get_userdata( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		}
	}
}

$ax_ib_failed = count( array_filter( $ax_ib_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ib_results ) - $ax_ib_failed, count( $ax_ib_results ) );
exit( $ax_ib_failed > 0 ? 1 : 0 );
