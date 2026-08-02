<?php
/**
 * Feed Pagination server render.
 *
 * Nothing, for the same reason the filters render nothing: the continuation link carries the
 * cursor the surrounding loop just resolved, and only the loop knows whether there is a further
 * page at all. This block places the control; the loop builds it.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;
