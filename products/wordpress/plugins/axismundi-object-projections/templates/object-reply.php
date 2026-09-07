<?php
/**
 * One reply, as blocks — the bubble `axismundi/replies` repeats down a thread.
 *
 * Authored here rather than concatenated in the renderer for the reason the Object Card is: a
 * composition built from PHP strings cannot be read as blocks, cannot be reordered, and drifts from
 * the card the moment either changes. The renderer's job is to bind the reply's model and hand it
 * this markup; what a reply looks like is decided in one readable place.
 *
 * Note, quote-post and Question share it. Each block renders only when the Object carries that part,
 * so one composition covers all three with no type switch.
 *
 * `object-content-warning` must keep wrapping the body, quote, poll and attachments: it supplies the
 * `axismundi/objectDisclosure` context they read, so one cover folds all of them. Lifting any of
 * them out would print warned material with nothing over it — which is exactly what the excerpt this
 * replaced used to do.
 *
 * Deliberately absent: `axismundi/replies`, a title and a featured image. A bubble is a line in a
 * conversation, not a page; nesting the replies block here would recurse a thread into itself.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"article","className":"axismundi-thread__reply","layout":{"type":"default"}} -->
<article class="wp-block-group axismundi-thread__reply">
	<!-- wp:group {"className":"axismundi-thread__avatar","layout":{"type":"default"}} -->
	<div class="wp-block-group axismundi-thread__avatar">
		<!-- wp:axismundi/actor-avatar {"size":32} /-->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"className":"axismundi-thread__body","layout":{"type":"default"}} -->
	<div class="wp-block-group axismundi-thread__body">
		<!-- wp:group {"className":"axismundi-thread__bubble","layout":{"type":"default"}} -->
		<div class="wp-block-group axismundi-thread__bubble">
			<!-- wp:group {"className":"axismundi-thread__identity","layout":{"type":"default"}} -->
			<div class="wp-block-group axismundi-thread__identity">
				<!-- wp:axismundi/actor-name /-->
				<!-- wp:axismundi/actor-handle /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:axismundi/object-content-warning -->
				<!-- wp:axismundi/object-content /-->
				<!-- wp:axismundi/quote-context /-->
				<!-- wp:axismundi/question /-->
				<!-- wp:axismundi/object-attachments /-->
			<!-- /wp:axismundi/object-content-warning -->
		</div>
		<!-- /wp:group -->
		<?php
		/*
		 * Date and Reply only, matching what Core's comment meta row offers. The remaining verbs
		 * live on the Object's own page; five controls on every line of a fifty-reply thread would
		 * make the thread about its buttons. `interaction` belongs to Activities, and an
		 * unregistered block renders nothing, so a site without it simply shows the date.
		 */
		?>
		<!-- wp:group {"className":"axismundi-thread__meta","layout":{"type":"default"}} -->
		<div class="wp-block-group axismundi-thread__meta">
			<!-- wp:axismundi/object-date /-->
			<!-- wp:axismundi/interaction {"type":"reply"} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</article>
<!-- /wp:group -->
