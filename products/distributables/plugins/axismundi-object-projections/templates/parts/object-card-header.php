<?php
/**
 * The identity row every Object card opens with.
 *
 * Shared because it is the same question on every card — who posted this, what is it,
 * and when — while the material below it differs by Object type. Keeping one copy means
 * a change to the header does not have to be made three times and drift twice.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:axismundi/object-status /-->
<!-- wp:group {"className":"axismundi-object-card__header","style":{"spacing":{"blockGap":"var:preset|spacing|150"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center","justifyContent":"space-between"}} -->
<div class="wp-block-group axismundi-object-card__header">
	<!-- wp:axismundi/actor-avatar {"size":48} /-->
	<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical"}} -->
		<div class="wp-block-group">
			<!-- wp:axismundi/actor-name /-->
			<!-- wp:axismundi/actor-handle /-->
		</div>
		<!-- /wp:group -->
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"right"}} -->
		<div class="wp-block-group">
			<!-- wp:axismundi/object-type /--><?php
			/*
			 * The row an audience marker would sit in, kept empty on purpose.
			 *
			 * Beside the time is where it belongs when it belongs anywhere: the audience is a
			 * property of this Object and not of whoever wrote it, so it has to change while the
			 * name above it does not. It is not drawn today because nothing on the writing side
			 * lets an author choose an audience and no delivery rule is closed behind one — a
			 * marker for a choice that was never offered states a promise the product does not
			 * keep. The block still exists and can be placed here; see
			 * `axismundi_op_object_visibility_marker_enabled`.
			 */
			?>
			<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center","justifyContent":"right"}} -->
			<div class="wp-block-group">
			<?php
			/*
			 * `published`, not `updated`. Only a post that has actually been edited carries
			 * an `updated` time, and most federated Objects never do — Mastodon and Misskey
			 * send none — so a header asking for it renders an empty string and the card
			 * silently loses its timestamp. `published` is the one time every Object has,
			 * and it is the time both peers show. An "edited" indicator is a separate
			 * signal and would need its own block placed beside this one.
			 */
			?>
			<!-- wp:axismundi/object-date /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
