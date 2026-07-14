=== Axismundi Activities ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: axismundi-actors
Stable tag: 0.0.6
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

Version 0.0.6 implements the immutable URI-keyed Activity ledger, Follow/Block relation
state, local Person-to-Person Follow controls, and read-only administrator inspection. It
also records one local outbound Create when a projectable Core Post is first published.
It creates no public Activity route, cron event, network request, inbox, notification, or
delivery queue. Media upload remains intentionally silent.

== Changelog ==

= 0.0.6 =
* Add verified DB v3 source-event identities so retries and concurrent WordPress save
  requests converge on one immutable Activity.
* Consume Object Projections Core Post publish candidates and record one URI-referenced
  outbound Create. Publish edits and unpublish/re-publish do not duplicate Create; a later
  effective Delete begins a new lifecycle generation.
* Keep password posts and media uploads silent, perform no transport, and defer Reply until
  the Axismundi Notes CPT establishes the canonical local Note model.

= 0.0.5 =
* Require Contributor-level `edit_posts` access for local Follow controls and management.
  Subscribers remain read-only even if an older Actor record exists.
* Add nonce-protected local Follow state and actions to the administrator Users table.
  Keep cached remote Actors display-only until an official ActivityPub transport adapter exists.
* Show pending Follow requests sent by the current Actor with a cancellation action.

= 0.0.4 =
* Add local-only Follow, request cancellation, Unfollow, Accept, and Reject workflows for
  activated public Person actors. Auto-accept undeclared policies by default while honoring
  an Actor's explicit `manually_approves_followers` policy.
* Add Follow controls to local Actor profiles and a self-service `Follows` admin screen
  for approval policy, pending requests, followers, and following.
* Keep every workflow offline, reject remote Actors, and add an Actors-owned policy setter
  instead of writing the identity repository directly.

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
