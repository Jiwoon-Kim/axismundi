<?php defined( 'ABSPATH' ) || exit; ?>
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

<!-- wp:axismundi/actor-activity-feed /-->

<!-- wp:axismundi/actor-projections {"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}}} /--></div>
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
