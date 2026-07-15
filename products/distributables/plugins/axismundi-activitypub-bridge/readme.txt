=== Axismundi ActivityPub Bridge ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: activitypub, axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, federation, compatibility, adapter

Connects Axismundi domain stores to supported S2S transport extension points in the official ActivityPub plugin.

== Description ==

This package is the only intended dependency boundary between Axismundi and the official
ActivityPub plugin. Actors, Object Projections, and Activities remain independently usable.

Version 0.0.2 runs the official plugin's overlapping domain modules in a conflict-safe dormant
mode. Axismundi owns profiles, object negotiation, and local Activity lifecycle records. The
official Router, Scheduler, Handler, and Dispatcher callbacks are suppressed without deleting
their stored data. Cached rewrites are rebuilt once under Axismundi ownership. Inbox writes
return 503 until a verified handoff can claim them safely.

Inbound handoff and outbound transport will be added only against supported upstream APIs.

== Changelog ==

= 0.0.2 =
* Add conflict-safe dormant transport mode for simultaneous plugin activation.
* Restore Axismundi object negotiation and post lifecycle ownership.
* Fail closed on unclaimed official Inbox writes before default persistence can run.

= 0.0.1 =
* Add the isolated official-plugin dependency boundary and runtime readiness API.
* Preserve the official plugin as the single post lifecycle publisher.
* Lock transport, storage, identity, and license boundaries without enabling federation.
