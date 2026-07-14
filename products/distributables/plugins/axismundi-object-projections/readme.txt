=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: activitypub, activitystreams, jsonld, federation

Projects WordPress objects into ActivityStreams JSON-LD through a transformer registry and one renderer.

== Description ==

Axismundi Object Projections turns a WordPress object (post, attachment, archive, folder)
into an ActivityStreams 2.0 object or collection, so the existing WordPress URL can answer
with JSON-LD under content negotiation. It owns representation only — a transformer registry,
object/collection URIs, and the single JSON-LD renderer.

It does not own an Activity ledger, inbox/outbox, Follow/Like, HTTP signatures, or delivery;
those belong to Axismundi Activities and Axismundi Federation. It works standalone and treats
the official ActivityPub plugin as optional (see docs/COMPATIBILITY.md).

This release also ships standalone content negotiation on the existing WordPress URL,
the Core Post → Article transformer, and an optional first-party Axismundi Media Library
attachment adapter. It still creates no rewrite, REST route, or table.
When the official ActivityPub plugin is active, the standalone negotiator turns itself off
so a future adapter can preserve that plugin's established object ids.

== Changelog ==

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
