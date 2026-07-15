=== Axismundi ActivityPub Bridge ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: activitypub, axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.9
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, federation, compatibility, adapter

Connects Axismundi domain stores to supported S2S transport extension points in the official ActivityPub plugin.

== Description ==

This package is the only intended dependency boundary between Axismundi and the official
ActivityPub plugin. Actors, Object Projections, and Activities remain independently usable.

Version 0.0.9 uses the patched official plugin's module gate to retain only Signature, REST
Server, and Inbox routes. After the official permission callback verifies the HTTP signature,
the bridge claims Activities addressed to public local Axismundi Actors and records them in the
Axismundi Activity ledger. Official domain handlers and persistence remain dormant. Stock
ActivityPub releases have no verified handoff API and therefore retain the fail-closed 503 guard.

Outbound Activities use the supported external-delivery API in the patched official plugin;
the official spool remains transport-only and Axismundi Activities remains authoritative.

== Changelog ==

= 0.0.9 =
* Import accepted followers, accepted following, and pending following from official Actor
  snapshots with explicit legacy provenance and without synthetic Activities.
* Replay Inbox history first so real Follow state takes precedence; preserve pending requests
  without retransmitting them and map official blog/application IDs to the site Actor.
* Keep `ap_actor` rows runtime-required and defer every purge operation to 0.0.10.

= 0.0.8 =
* Add explicit, administrator-confirmed import and immediate verification for legacy remote
  Actors, remote Objects, and signature-verified Inbox Activities.
* Replay Inbox history through the Activities state machine while refusing direct follower
  snapshot writes, transport Outbox import, lifecycle synthesis, profile-field import, or key copying.
* Keep every official source row intact, perform no network request, and make repeat imports
  idempotent without allowing older official snapshots to overwrite existing Axismundi caches.

= 0.0.7 =
* Add a read-only legacy ActivityPub migration dry scan under Tools > ActivityPub Bridge.
* Classify import and purge independently for remote Actors, remote Objects, Inbox history,
  follower snapshots, transport Outbox rows, local lifecycle markers, profile fields, comments,
  and official signing keys.
* Perform no import, deletion, option update, network request, or payload/key rendering. Keep
  `ap_actor` and signing keys runtime-required until external public-key resolution exists.

= 0.0.6 =
* Move public Actor Outbox representation and its GET route to Object Projections.
* Keep Bridge ownership limited to Inbox/sharedInbox/publicKey transport fields, verified
  Inbox handoff, signing identity resolution, and outbound delivery.
* Continue showing the Object Projections-owned Outbox URL in the transport inspector.

= 0.0.5 =
* Supply inbox, sharedInbox, and publicKey transport properties to
  the Object Projections-owned Actor JSON-LD representation.
* Add a read-only Tools > ActivityPub Bridge inspector.
* Queue complete outbound Activities through the official plugin's external transport
  spool. Persist only a private-key reference; resolve signing material at send time.

= 0.0.4 =
* Claim signature-verified Inbox Activities through the patched upstream handoff.
* Resolve local recipients and remote Actors before recording in the Axismundi ledger.
* Bypass official domain handlers and persistence while retaining signature verification.

= 0.0.3 =
* Use the upstream module gate with a minimum explicit allowlist.
* Block dormant Inbox writes before signature lookup and remote Actor caching.
* Retain callback removal as a compatibility fallback for stock releases.

= 0.0.2 =
* Add conflict-safe dormant transport mode for simultaneous plugin activation.
* Restore Axismundi object negotiation and post lifecycle ownership.
* Fail closed on unclaimed official Inbox writes before default persistence can run.

= 0.0.1 =
* Add the isolated official-plugin dependency boundary and runtime readiness API.
* Preserve the official plugin as the single post lifecycle publisher.
* Lock transport, storage, identity, and license boundaries without enabling federation.
