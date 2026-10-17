<?php
/**
 * Server render bridge for the Question block.
 *
 * NOTE: transitional. The frontend app's single-object routing follows the object
 * hierarchy, so a Question's results and voting controls become a React component owned
 * by the `axismundi` plugin rather than this block. The accessibility contract that has
 * to travel with that move -- a `role="progressbar"` carries `aria-valuemin`,
 * `aria-valuemax`, `aria-valuenow` and a human `aria-label`, because a progress bar with
 * no value reports only its own existence -- is recorded in that plugin's
 * docs/DECISION-FRONTEND-COMPONENT-CONVENTIONS.md, together with the fact that
 * tests/audit-question-view.php checks this server path and will stop protecting anything
 * once rendering moves.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_question_block(), axismundi_op_allowed_block_html() );