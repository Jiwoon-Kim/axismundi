=== Axismundi Activities ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.1
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

Version 0.0.1 is a contract-only scaffold. It creates no table, route, cron event, network
request, inbox, outbox, notification, or delivery queue.

== Changelog ==

= 0.0.1 =
* Lock Activity, relation, lifecycle, logical collection, media no-Create, lease, and
  prefix-tenancy contracts in docs without creating runtime state.
