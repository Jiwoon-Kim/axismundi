<?php defined( 'ABSPATH' ) || exit; ?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|500","bottom":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--500);padding-bottom:var(--wp--preset--spacing--500)">
		<!-- wp:axismundi/account-header -->
		<!-- wp:group {"layout":{"type":"constrained"}} -->
		<div class="wp-block-group"><!-- wp:axismundi/object-featured-image {"showPlaceholder":true,"borderColor":"outline-variant","style":{"dimensions":{"minHeight":"200px"},"border":{"radius":"0 0 24px 24px","width":"1px"}}} /-->

		<!-- wp:group {"className":"ax-account-header__head","style":{"spacing":{"margin":{"top":"-36px"},"padding":{"right":"var:preset|spacing|100","left":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","orientation":"horizontal","verticalAlignment":"bottom"}} -->
		<div class="wp-block-group ax-account-header__head" style="margin-top:-36px;padding-right:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--100)"><!-- wp:axismundi/actor-avatar {"size":128,"style":{"shadow":"var:preset|shadow|elevation-2"}} /-->

		<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->
		<div class="wp-block-group"><!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"constrained","justifyContent":"left"}} -->
		<div class="wp-block-group"><!-- wp:axismundi/actor-identity /--></div>
		<!-- /wp:group -->

		<!-- wp:axismundi/follow-button /--></div>
		<!-- /wp:group --></div>
		<!-- /wp:group --></div>
		<!-- /wp:group -->

		<!-- wp:axismundi/actor-biography /-->
		<!-- wp:axismundi/actor-profile-fields /-->
		<!-- /wp:axismundi/account-header -->

		<!-- wp:axismundi/actor-activity-feed /-->
		<!-- wp:axismundi/actor-projections {"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}}} /-->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
