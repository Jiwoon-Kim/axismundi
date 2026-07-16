=== Axismundi ActivityPub Bridge ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: activitypub, axismundi-actors, axismundi-object-projections, axismundi-activities
Stable tag: 0.0.14
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, federation, compatibility, adapter

Connects Axismundi domain stores to supported S2S transport extension points in the official ActivityPub plugin.

== Description ==

This package is the only intended dependency boundary between Axismundi and the official
ActivityPub plugin. Actors, Object Projections, and Activities remain independently usable.

Version 0.0.14 uses the patched official plugin's module gate to retain Signature, REST Server,
WebFinger, and Inbox routes. After the official permission callback verifies the HTTP signature,
the bridge consumes the existing `activitypub_inbox` and `activitypub_inbox_shared` actions and
records Activities addressed to public local Axismundi Actors. Official domain handlers remain
dormant and `activitypub_skip_inbox_storage` prevents duplicate CPT persistence. No additional
verified-envelope API is required.

Outbound Activities use the supported external-delivery API in the patched official plugin;
the official spool remains transport-only and Axismundi Activities remains authoritative.

== Changelog ==

= 0.0.14 =
* Finalize outbound Activities through Object Projections before transport so Follow,
  Accept, and other payloads carry the canonical ActivityStreams JSON-LD context.
* Keep the Activities ledger payload immutable and leave signing, spool, retry, and HTTP
  delivery with the official plugin.

= 0.0.13 =
* Add a bounded, content-free administrator diagnostic buffer that distinguishes recorded
  and unclaimed per-Actor/shared Inbox deliveries by result code.
* Accept an embedded ActivityStreams Mention href as a supplemental local target only after
  the official shared Inbox has verified the request.
* Keep official Inbox snapshot fallback for every unclaimed Activity and continue deferring
  destructive legacy-data purge until legacy local Actor aliases are designed.

= 0.0.12 =
* Consume the official controllers' existing per-Actor and shared Inbox actions after request
  validation instead of requiring a new verified-Inbox handoff API.
* Keep official type handlers dormant and skip official Inbox CPT persistence only after
  Axismundi records one URI-keyed Activity and relationship state.
* Remove the temporary 503 compatibility guard while retaining official Inbox snapshot storage
  as a fail-safe for Activities the bridge cannot claim.
* Keep destructive legacy-data purge deferred until legacy local Actor aliases are designed.

= 0.0.11 =
* Keep the official WebFinger REST controller enabled in dormant transport mode so the
  0.0.10 Axismundi descriptor adapter is reachable at the standard well-known endpoint.
* Keep destructive legacy-data purge deferred to a later release.

= 0.0.10 =
* Supply public Axismundi Actors through the official plugin's WebFinger controller while
  preserving official and third-party ownership of every other resource.
* Advertise the canonical ActivityStreams Actor document with a WebFinger `self` link.
* Keep destructive legacy-data purge deferred to a later release.

= 0.0.9 =
* Import accepted followers, accepted following, and pending following from official Actor
  snapshots with explicit legacy provenance and without synthetic Activities.
* Replay Inbox history first so real Follow state takes precedence; preserve pending requests
  without retransmitting them and map official blog/application IDs to the site Actor.
* Keep `ap_actor` rows runtime-required and defer every purge operation to a later release.

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
