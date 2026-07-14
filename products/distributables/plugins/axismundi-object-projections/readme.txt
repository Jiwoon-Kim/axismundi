=== Axismundi Object Projections ===
Contributors: kimjiwoon
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.0.1
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

This release ships the Phase 0 contract and the Phase 1 registry + renderer only: there is no
HTTP routing, rewrite, REST route, or table yet. Content negotiation on the existing URL and
the Core Post → Article transformer are the next increments (docs/PHASES.md).

== Changelog ==

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
