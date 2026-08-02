<?php
/**
 * One profile surface's layout: its chrome, the cards it repeats, and where its pager goes.
 *
 * Included once per surface rather than written out per surface. Both start identical — the point
 * of the tabs is that they can stop being identical the moment an author edits one, not that they
 * begin different. Two hand-written copies would begin drifting before anyone edited anything,
 * which is the failure this restructure exists to remove.
 *
 * What lands in the saved template is still a copy per tab, and that is correct: independent
 * editing means independent saved markup. This file only decides what each copy starts as.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;
?>
	<!-- wp:axismundi/feed-filters /-->
	<!-- wp:axismundi/feed-density-switch /-->
	<!-- wp:axismundi/feed-item-templates -->
	<!-- wp:axismundi/feed-item-template {"density":"card"} -->
		<?php require WP_PLUGIN_DIR . '/axismundi-object-projections/templates/parts/object-card-header.php'; ?>
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
	<?php
	/*
	 * The same card, composed for a reader who asked for less of each entry.
	 *
	 * Both are saved, and the switch on the page picks between them — so how much an entry shows is
	 * decided by blocks an author can edit rather than by CSS hiding things that were rendered
	 * anyway. A hidden summary still costs the work of building it and still tells anything reading
	 * the markup that the entry has one.
	 *
	 * Seeded rather than left to be added later: a template carrying only one of the two would
	 * leave the other density falling back to a card it did not choose, and the fallback is meant
	 * to be the upgrade path for templates saved before this existed, not the normal case.
	 */
	?>
	<!-- wp:axismundi/feed-item-template {"density":"compact"} -->
		<!-- wp:axismundi/object-status /-->
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group">
			<!-- wp:axismundi/actor-avatar {"size":24,"style":{"border":{"radius":"50%"}}} /-->
			<!-- wp:axismundi/object-title /-->
			<!-- wp:axismundi/object-date /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:axismundi/interactions -->
			<!-- wp:axismundi/interaction {"type":"reply","size":"xs"} /-->
			<!-- wp:axismundi/interaction {"type":"like","size":"xs"} /-->
			<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true,"size":"xs"} /-->
		<!-- /wp:axismundi/interactions -->
	<!-- /wp:axismundi/feed-item-template -->
	<!-- /wp:axismundi/feed-item-templates -->
	<!-- wp:axismundi/feed-pagination /-->
