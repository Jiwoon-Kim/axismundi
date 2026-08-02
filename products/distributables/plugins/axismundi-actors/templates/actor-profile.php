<?php
defined( 'ABSPATH' ) || exit;

// The legacy Actor Profile template remains a Person-compatible shell. Dedicated Person and
// Group templates set this before including it, so their feed surfaces can evolve independently.
$axismundi_actor_profile_kind = isset( $axismundi_actor_profile_kind ) && 'group' === $axismundi_actor_profile_kind ? 'group' : 'person';
$axismundi_actor_feed_class   = 'group' === $axismundi_actor_profile_kind
	? 'ax-group-profile__community'
	: 'ax-person-profile__timeline';
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","style":{"spacing":{"margin":{"top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="margin-top:var(--wp--preset--spacing--0);margin-bottom:var(--wp--preset--spacing--0)"><!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|500"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-bottom:var(--wp--preset--spacing--500)"><!-- wp:axismundi/account-header {"align":"wide"} -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:axismundi/object-featured-image {"showPlaceholder":true,"style":{"dimensions":{"height":"200px"},"border":{"radius":{"topLeft":"0px","topRight":"0px","bottomLeft":"20px","bottomRight":"20px"}}}} /-->

<!-- wp:group {"className":"ax-account-header__head","style":{"spacing":{"margin":{"top":"-36px"},"padding":{"right":"var:preset|spacing|100","left":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","orientation":"horizontal","verticalAlignment":"bottom"}} -->
<div class="wp-block-group ax-account-header__head" style="margin-top:-36px;padding-right:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--100)"><!-- wp:axismundi/actor-avatar {"size":120,"style":{"shadow":"var:preset|shadow|elevation-1","border":{"width":"4px","color":"var(--md-sys-color-surface)","radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}}} /-->

<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:axismundi/actor-identity /--></div>
<!-- /wp:group -->

<!-- wp:axismundi/follow-button /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:axismundi/actor-biography /-->

<!-- wp:axismundi/actor-profile-fields {"display":"grid"} /-->

<!-- wp:axismundi/actor-social-counts /-->
<!-- /wp:axismundi/account-header -->

<?php
/*
 * Who runs the community, on the Group profile only.
 *
 * The block belongs to whichever product owns the concept — Actors has no idea what a moderator
 * is — and it renders nothing when that product is not installed, because an unregistered block
 * produces no output. Placing it here rather than in the shared follower list is deliberate: the
 * roster is small and complete, while a subscriber list is large, paginated, and does not
 * necessarily contain the people who run the place at all.
 */
if ( 'group' === $axismundi_actor_profile_kind ) :
	?>
<!-- wp:axismundi/group-moderators {"style":{"spacing":{"margin":{"top":"var:preset|spacing|300"}}}} /-->
	<?php
endif;
?>

<!-- wp:group {"className":"<?php echo esc_attr( $axismundi_actor_feed_class ); ?>"} -->
<div class="wp-block-group <?php echo esc_attr( $axismundi_actor_feed_class ); ?>"><!-- wp:axismundi/feed -->
	<?php
	/*
	 * The card the feed repeats, saved here so an author can edit it.
	 *
	 * It is inside the feed rather than beside it because the feed is what renders it — once per
	 * Object on the first page, and once per Object again for every page that arrives after
	 * "Load more". Both read this same saved markup, which is the only way an edit here can reach
	 * the cards a reader has not scrolled to yet.
	 *
	 * The `<article>` shell and the type modifier are not here: those depend on the Object being
	 * rendered, and only the loop knows which one a given row holds.
	 */
	?>
	<!-- wp:axismundi/feed-tabs -->
	<!-- wp:axismundi/feed-tab {"surface":"activity"} -->
	<?php require __DIR__ . '/parts/feed-surface-layout.php'; ?>
	<!-- /wp:axismundi/feed-tab -->
	<?php
	/*
	 * The same layout to begin with, and separately editable from here on.
	 *
	 * A community row is not a timeline row even when it is drawn the same way: its header names
	 * the Group rather than the profile owner, and its entries are chosen by where they were
	 * addressed. Its own saved markup is what lets that reading diverge — different filters, a
	 * different card, the pager somewhere else — without either surface having to be described
	 * as a variation of the other.
	 */
	?>
	<?php
	/*
	 * A community is browsed, not scrolled: a reader goes into a thread, comes back, and links to
	 * what they found, which is a page number rather than a position in a cursor.
	 *
	 * Declared for the surface, not for a kind of profile. A Group community serves numbered pages
	 * and gets them; a Person community serves a cursor today, so the same declaration is refused
	 * by its surface and it keeps Load more. Nothing here has to know which is which — that is the
	 * point of the request being bounded by what the source declares.
	 */
	?>
	<!-- wp:axismundi/feed-tab {"surface":"community","navigation":"pagination"} -->
	<?php require __DIR__ . '/parts/feed-surface-layout.php'; ?>
	<!-- /wp:axismundi/feed-tab -->
	<!-- /wp:axismundi/feed-tabs -->
<!-- /wp:axismundi/feed --></div>
<!-- /wp:group -->

</div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<?php
/*
 * One media dialog for the whole profile, owned by Axismundi Dialogs. The feed below
 * renders Object cards whose attachments carry openers, and those openers do nothing
 * without a hub on the page — a timeline needs one just as the single Object page does.
 * One per page rather than one per card: a feed of twenty posts must not emit twenty
 * dialogs. Composed here the same way this template already composes the Activities
 * feed; if Dialogs is inactive the block is unregistered and renders nothing.
 */
?>
<!-- wp:axismundi/object-media-dialog /-->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
