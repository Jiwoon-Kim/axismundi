=== Axismundi Activities ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors
Stable tag: 0.0.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, federation, social

Records ActivityStreams activities and derives social relationship state without owning
network transport, notifications, or delivery.

== Description ==

Axismundi Activities is the URI-first activity ledger and social relationship layer for
Axismundi. Actors owns identities, Object Projections owns object representations and remote
object cache retention, Notifications will own read state and recipient presentation, and
Federation will own HTTP inbox/outbox transport, signatures, and remote delivery.

Axismundi Actors is a required dependency and remains the authority for every actor URI.

Version 0.0.3 implements the immutable URI-keyed Activity ledger, Follow/Block relation
state, and a read-only administrator Activity Log. It creates no public route, cron event,
network request, inbox, outbox, notification, or delivery queue.

== Changelog ==

= 0.0.3 =
* Add verified DB v2 `wp_ax_activity_relations` materialization for Follow, Accept, Reject,
  Undo, and Block in the same transaction as the immutable Activity ledger.
* Derive followers/following from accepted Follow edges, enforce transition Actor authority,
  and reconcile an Accept or Reject that arrives before its Follow.
* Add the read-only `Tools > Activity Log` administrator inspector for recent Activities,
  immutable payloads, and current social relation state.

= 0.0.2 =
* Add the verified InnoDB `wp_ax_activities` repository with UUID local Activity URIs,
  exact URI/hash identity, bounded immutable payloads, normalized audience, and Actor/Object
  reverse lookups. Keep prefix tenancy and omit `blog_id`.
* Require every Actor URI to resolve through Axismundi Actors and reject direction/origin
  conflicts. Preserve remote inbound Activity ids exactly.
* Add idempotent replay, payload identity-conflict protection, post-commit recorded hooks,
  and same-Actor Undo effectiveness including out-of-order and Undo-of-Undo reconciliation.

= 0.0.1 =
* Lock Activity, relation, lifecycle, logical collection, media no-Create, lease, and
  prefix-tenancy contracts in docs without creating runtime state.
