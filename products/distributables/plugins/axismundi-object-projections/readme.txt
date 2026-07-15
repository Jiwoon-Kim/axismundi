=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.9
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, jsonld, federation

Projects WordPress objects into ActivityStreams JSON-LD through a transformer registry and one renderer.

== Description ==

Axismundi Object Projections turns a WordPress object (post, attachment, archive, folder)
or Axismundi Actor into an ActivityStreams 2.0 object or collection, so its existing URL can answer
with JSON-LD under content negotiation. It owns representation only — a transformer registry,
object/collection URIs, and the single JSON-LD renderer.

It does not own an Activity ledger, inbox/outbox, Follow/Like, HTTP signatures, or delivery;
those belong to Axismundi Activities and Axismundi Federation. It works standalone and treats
the official ActivityPub plugin as optional (see docs/COMPATIBILITY.md).

This release also ships standalone content negotiation on the existing WordPress URL,
the Core Post → Article transformer, and an optional first-party Axismundi Media Library
attachment adapter. It creates no custom rewrite or REST route.
When the official ActivityPub plugin is active, the standalone negotiator turns itself off
so a future adapter can preserve that plugin's established object ids.

The remote-object repository stores URI-keyed, rebuildable observations for later
administrator inspection and Activities integration. It performs no network requests and
exposes no public mirror route. Administrators may fetch and inspect metadata-only remote
objects under Tools > Remote Objects, including tags, mentions, audience declarations,
attachment descriptors, extension properties, and the complete escaped JSON payload;
remote media is never hotlinked or downloaded.

== Changelog ==

= 0.0.9 =
* Project public local Axismundi Actor URLs as Person, Application, Organization, Group,
  or Service JSON-LD through the same renderer and content-negotiation surface.
* Keep Actor representation ownership here while allowing the ActivityPub Bridge to add
  only transport properties such as inbox, outbox, sharedInbox, and publicKey.
* Keep Inbox/Outbox routes, Activity data, signatures, queues, and delivery outside this plugin.

= 0.0.8 =
* Emit an idempotent Core Post publish candidate from `wp_after_insert_post`, after terms
  and post meta are stored. Object Projections performs no Activity write or transport.
* Exclude drafts, password posts, pages, attachments, and Actor-less posts. Keep media
  uploads silent and defer Reply semantics until Axismundi Notes defines local Note identity.
* Fail closed when the official ActivityPub plugin owns post lifecycle publication; a
  compatibility adapter must explicitly transfer single-publisher ownership.

= 0.0.7 =
* Expand Remote Object details with structured Tags/Mentions, audience declarations,
  attachment metadata, extension properties, and complete escaped raw JSON. Remote media
  remains metadata-only and is never rendered or downloaded.
* Link Actor references to the cached Actors administrator record when available and fall
  back to the canonical remote URI when absent; do not fetch while rendering.
* Replace synchronous post-fetch Actor discovery with one deduplicated WP-Cron event for
  the primary attributedTo Actor only. Object storage no longer waits on Actor discovery,
  and Mention/audience members never cause request fan-out.

= 0.0.6 =
* Add Tools > Remote Objects: bounded public-HTTPS ActivityStreams fetch, conditional
  ETag/Last-Modified refresh, explicit signed-fetch-required errors, refresh/delete, and
  a text/metadata-only inspector that strips every media/embed element.
* Add schema v2 metadata retention (`expires_at`, `last_accessed_at`) with a filterable
  30-day sliding default, capped failure backoff, daily expiry maintenance, and manual
  expired-cache purge. No front-end render path performs a network request.
* Reserve metadata-only/preview/display/original cache levels while deferring every
  binary, hotlink, shared-blob, and shadow-attachment decision.

= 0.0.5 =
* Add the InnoDB `wp_ax_remote_objects` repository for URI-keyed remote ActivityStreams
  observations, with hash-indexed long URIs, normalized display fields, lossless bounded
  payload JSON, tri-state sensitivity, fetch validators, refresh state, and Tombstones.
* Keep invalid refresh input from overwriting the last good snapshot; verify the table,
  unique identity index, and storage engine before recording schema version 1.
* Keep fetching and public mirroring out of this increment. The repository performs no
  network request; bounded administrator discovery follows separately.

= 0.0.4 =
* Detect Axismundi Media Library in Independent mode and project public/unlisted,
  ungated attachments as Image, Video, Audio, or Document. Keep stable attachment ids,
  human media-page URLs, bounded image renditions, sensitivity, and canonical licenses.
* Keep the first-party boundary explicit: Media Library owns data and access services;
  Object Projections owns ActivityStreams mapping and never uses authenticated bypasses.
* Clarify that an Actor's primary feed is an Activities-owned outbox, not an Article
  archive. Article and Media views may later be optional filtered profile tabs.

= 0.0.3 =
* Add the browser-friendly `?activitypub` representation selector alongside standard
  Accept negotiation. It changes only retrieval format: the selector is never included
  in the emitted object id, and all existing visibility and single-negotiator gates apply.

= 0.0.2 =
* Add precise Accept negotiation for application/activity+json and ActivityStreams-
  profiled application/ld+json on existing WordPress object URLs. Bare application/json
  and unprofiled application/ld+json never hijack HTML; responses emit Vary, alternate
  Link, CORS, and nosniff headers, with GET/HEAD support.
* Add the Core Post → Article transformer: stable /?p={ID} id, human permalink url,
  public Actor attribution, rendered HTML content, manual summary, and timestamps.
  Draft, private, password-protected, or Actor-less posts fail closed.
* Disable standalone negotiation whenever the official ActivityPub plugin is active,
  while leaving registry and renderer APIs available to the future compatibility adapter.

= 0.0.1 =
* Phase 0 — lock the projection contract and scaffold the plugin: object vs actor
  projections, stable object id vs human url, the renderer as the sole @context owner,
  transformers as pure projections, and the single-negotiator + legacy-id-preserving
  compatibility contract for the official ActivityPub plugin (docs/).
* Phase 1 — the transformer registry and renderer, with no table and no route. Public API:
  axismundi_op_register_object_transformer(), axismundi_op_register_collection_transformer(),
  axismundi_op_transform_object(), axismundi_op_transform_collection(). The renderer
  validates the four required members, forces the emitted id to equal the declared object
  URI, owns the JSON-LD @context, sanitizes HTML members, and keeps "no transformer", a
  transformer error, and "not public" as three distinct outcomes.
