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

<!-- wp:group {"tagName":"main","align":"full","style":{"spacing":{"margin":{"top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"},"padding":{"right":"var:preset|spacing|0","left":"var:preset|spacing|0"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="margin-top:var(--wp--preset--spacing--0);margin-bottom:var(--wp--preset--spacing--0);padding-right:var(--wp--preset--spacing--0);padding-left:var(--wp--preset--spacing--0)"><!-- wp:group {"metadata":{"name":"Profile Header"},"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|0","left":"var:preset|spacing|0","bottom":"var:preset|spacing|100"},"margin":{"top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"},"top":[],"right":[],"left":[]}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;margin-top:var(--wp--preset--spacing--0);margin-bottom:var(--wp--preset--spacing--0);padding-right:var(--wp--preset--spacing--0);padding-bottom:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--0)"><!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:axismundi/object-featured-image {"showPlaceholder":true,"style":{"dimensions":{"height":"200px"},"border":{"radius":{"topLeft":"0px","topRight":"0px","bottomLeft":"20px","bottomRight":"20px"}}}} /-->

<!-- wp:group {"className":"ax-actor-profile__head","style":{"spacing":{"margin":{"top":"-36px"},"padding":{"right":"var:preset|spacing|100","left":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","orientation":"horizontal","verticalAlignment":"bottom"}} -->
<div class="wp-block-group ax-actor-profile__head" style="margin-top:-36px;padding-right:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--100)"><!-- wp:axismundi/actor-avatar {"size":120,"style":{"shadow":"var:preset|shadow|elevation-1","border":{"width":"4px","color":"var(--md-sys-color-surface)","radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}}} /-->

<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:axismundi/actor-identity /--></div>
<!-- /wp:group -->

<!-- wp:axismundi/follow-button /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"ax-actor-profile__header","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","left":"var:preset|spacing|200"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide ax-actor-profile__header" style="padding-right:var(--wp--preset--spacing--200);padding-left:var(--wp--preset--spacing--200)"><!-- wp:axismundi/actor-social-counts /-->

<!-- wp:axismundi/actor-biography /-->

<!-- wp:axismundi/actor-profile-fields {"display":"grid"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
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
<!-- wp:group {"className":"<?php echo esc_attr( $axismundi_actor_feed_class ); ?>","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo esc_attr( $axismundi_actor_feed_class ); ?>"><!-- wp:axismundi/feed {"align":"wide"} -->
<!-- wp:axismundi/feed-tabs -->
<?php if ( 'person' === $axismundi_actor_profile_kind ) : ?>
<!-- wp:axismundi/feed-tab -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:axismundi/feed-filters /--></div>
<!-- /wp:group -->

<!-- wp:axismundi/feed-loop -->
<!-- wp:axismundi/feed-item-template -->
<!-- wp:axismundi/object-status /-->

<!-- wp:axismundi/object-card-header -->
<!-- wp:axismundi/actor-avatar {"size":48} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:axismundi/actor-name /-->

<!-- wp:axismundi/actor-handle /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:axismundi/object-type /-->

<!-- wp:axismundi/object-date /--></div>
<!-- /wp:group -->
<!-- /wp:axismundi/object-card-header -->

<!-- wp:axismundi/object-card-body /-->

<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->

<!-- wp:axismundi/reaction-bar /-->

<!-- wp:axismundi/interactions -->
<!-- wp:axismundi/interaction {"type":"reply"} /-->

<!-- wp:axismundi/interaction {"type":"like"} /-->

<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true} /-->

<!-- wp:axismundi/interaction {"type":"reaction"} /-->
<!-- /wp:axismundi/interactions -->
<!-- /wp:axismundi/feed-item-template -->
<!-- /wp:axismundi/feed-loop -->

<!-- wp:axismundi/feed-pagination /-->
<!-- /wp:axismundi/feed-tab -->
<?php endif; ?>

<!-- wp:axismundi/feed-tab {"surface":"<?php echo 'group' === $axismundi_actor_profile_kind ? 'activity' : 'community'; ?>","navigation":"pagination","filterStyle":"tabs"} -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:axismundi/feed-filters /-->

<!-- wp:axismundi/feed-density-switch /--></div>
<!-- /wp:group -->

<!-- wp:axismundi/feed-loop -->
<!-- wp:axismundi/feed-item-template -->
<!-- wp:axismundi/object-card-header -->
<!-- wp:axismundi/actor-avatar {"size":48} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:axismundi/actor-name /-->

<!-- wp:axismundi/actor-handle /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:axismundi/object-type /-->

<!-- wp:axismundi/object-date /--></div>
<!-- /wp:group -->
<!-- /wp:axismundi/object-card-header -->

<!-- wp:axismundi/object-card-body /-->

<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->

<!-- wp:axismundi/reaction-bar /-->

<!-- wp:axismundi/interactions -->
<!-- wp:axismundi/interaction {"type":"reply"} /-->

<!-- wp:axismundi/interaction {"type":"like"} /-->

<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true} /-->

<!-- wp:axismundi/interaction {"type":"reaction"} /-->
<!-- /wp:axismundi/interactions -->
<!-- /wp:axismundi/feed-item-template -->

<!-- wp:axismundi/feed-item-template {"density":"compact"} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group"><!-- wp:axismundi/object-featured-image {"scale":"contain","style":{"dimensions":{"height":"108px","aspectRatio":"4/3"},"layout":{"selfStretch":"fit","flexSize":null}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|100","bottom":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|100"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--100);padding-bottom:var(--wp--preset--spacing--100)"><!-- wp:axismundi/object-card-header -->
<!-- wp:axismundi/actor-avatar {"size":24} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"horizontal","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:axismundi/actor-name /-->

<!-- wp:axismundi/actor-handle /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"horizontal","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:axismundi/object-type /-->

<!-- wp:axismundi/object-date /--></div>
<!-- /wp:group -->
<!-- /wp:axismundi/object-card-header -->

<!-- wp:axismundi/object-title {"level":2,"style":{"typography":{"fontSize":"1.5em"}}} /-->

<!-- wp:axismundi/object-summary {"showMoreOnNewLine":false} /-->

<!-- wp:axismundi/reaction-bar /-->

<!-- wp:axismundi/interactions -->
<!-- wp:axismundi/interaction {"type":"reply","size":"xs"} /-->

<!-- wp:axismundi/interaction {"type":"like","size":"xs"} /-->

<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"size":"xs"} /-->
<!-- /wp:axismundi/interactions --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:axismundi/feed-item-template -->
<!-- /wp:axismundi/feed-loop -->

<!-- wp:axismundi/feed-pagination /-->
<!-- /wp:axismundi/feed-tab -->
<!-- /wp:axismundi/feed-tabs -->
<!-- /wp:axismundi/feed --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
<?php
/*
 * One media dialog for the whole profile, owned by Axismundi Dialogs. The feed above renders
 * Object cards whose attachments carry openers, and those openers do nothing without a hub on the
 * page — a timeline needs one just as the single Object page does. One per page rather than one
 * per card: a feed of twenty posts must not emit twenty dialogs. If Dialogs is inactive the block
 * is unregistered and renders nothing.
 */
?>

<!-- wp:axismundi/object-media-dialog /-->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
