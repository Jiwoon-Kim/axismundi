<?php
/**
 * The identity row every Object card opens with.
 *
 * One block now, where this was four levels of core Groups carrying the arrangement in their
 * attributes. The nesting was not the problem by itself — a template that saved a copy of it was.
 * A card edited in the Site Editor froze that structure, so changing the header afterwards changed
 * this file and not the page, and the two card densities drifted apart the same way.
 *
 * Which Actor it names is not stated here. The header's children resolve their own subject, and
 * the surface being rendered decides whose: the Object's author on a timeline, the Group on a
 * Person's community surface, where the profile above already says who the Person is.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:axismundi/object-status /-->
<!-- wp:axismundi/object-card-header -->
	<!-- wp:axismundi/actor-avatar {"size":48} /-->
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
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
		 * Beside the time is where it belongs when it belongs anywhere: the audience is a property
		 * of this Object and not of whoever wrote it, so it has to change while the name above it
		 * does not. It is not drawn today because nothing on the writing side lets an author choose
		 * an audience and no delivery rule is closed behind one — a marker for a choice that was
		 * never offered states a promise the product does not keep. The block still exists and can
		 * be placed here; see `axismundi_op_object_visibility_marker_enabled`.
		 */
		?>
		<?php
		/*
		 * `published`, not `updated`. Only a post that has actually been edited carries an
		 * `updated` time, and most federated Objects never do — Mastodon and Misskey send none —
		 * so a header asking for it renders an empty string and the card silently loses its
		 * timestamp. `published` is the one time every Object has, and it is the time both peers
		 * show. An "edited" indicator is a separate signal and would need its own block beside it.
		 */
		?>
		<!-- wp:axismundi/object-date /-->
	</div>
	<!-- /wp:group -->
<!-- /wp:axismundi/object-card-header -->
