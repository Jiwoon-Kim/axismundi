# Axismundi Object Projections — specification

> Status: **Phases 0–4b implemented** (local projections plus metadata-only remote
> discovery/cache inspection). No custom rewrite, REST route, Activity
> ledger, or transport. This package owns the projection contract and representation.

## 1. Purpose

Project a WordPress object (post, attachment, archive, folder) into an ActivityStreams
2.0 object or `OrderedCollection`, so the existing WordPress URL can answer with JSON-LD
under content negotiation. It does **not** own an Activity store, inbox/outbox, Follow /
Like, signatures, delivery, or the objects' own storage.

```
WP_Post ──(Transformer)──▶ normalized AS object ──(Renderer)──▶ JSON-LD
```

## 2. Ownership

**Owns**

- The transformer registry (object + collection).
- Object / collection URIs (the stable AS `id`).
- ActivityStreams JSON-LD serialization and the single `@context` assembly.
- Object/collection representation + content negotiation on the existing URL.
- The Core Post → `Article` transformer.
- First-party integration adapters that translate stable domain service APIs into
  ActivityStreams semantics (currently Axismundi Media Library attachments).
- Object lifecycle *events* (publish/update/delete) — emitted, not stored.
- URI-keyed **remote object projections**: rebuildable observed snapshots independent
  of Activity ingestion, with bounded administrator discovery and metadata-only
  inspection (see REMOTE-OBJECTS.md).

**Does not own**

- `wp_ax_activities`, inbox/outbox, Follow/Like/Announce, signatures, delivery, retry.
- Note / media object storage itself (the domain plugins own it).
- Authority over a remote canonical object: its URI remains the source identity and a
  local projection row is only a refreshable cache, never a replacement identity.
- The official ActivityPub plugin's internal models.
- Remote binary files; the object table stores metadata/payload observations only.

## 3. Terms (kept distinct)

- **Actor projection** — the Posts / Media / Notes tabs and links on an Actor profile
  (owned by Axismundi Actors; a navigation concern).
- **Object transformer** — `WP_Post → Article`, `attachment → Image`, …
- **Collection transformer** — author / archive / folder → `OrderedCollection(Page)`.
- **Object Projections** — this plugin: the transformer registry, JSON-LD representation,
  and (Phase 2) object/collection negotiation.

## 4. Frozen contracts

1. **id vs url are different.** The AS `id` is a stable, permalink-independent URI; `url`
   is the human HTML permalink (§ROUTING). A transformer's `id` **must** equal its
   declared object URI — the renderer rejects a mismatch.
2. **The renderer is the sole owner of `@context`.** A transformer returns a plain object
   with no `@context`; the renderer strips any supplied one and sets the canonical
   context. Extensions come only through `axismundi_op_jsonld_context`.
3. **Transformers are pure projections** — no DB writes, no network, no route ownership,
   no content-negotiation decisions.
4. **Three outcomes are distinct**: no transformer (`ax_op_no_transformer`), a transformer
   error (its own `WP_Error`), and a not-public source (`ax_op_not_public`). The router
   passes unsupported sources through, maps not-public to 404 and real failures to 500.
5. **Visibility is a callback, not the transformer body.** Public/private is decided by a
   registered `visible` callback, so the transform stays a pure mapping.
6. **The official ActivityPub plugin is not a dependency.** Object Projections works fully
   standalone; when ActivityPub *is* active, only one negotiator owns a URL (§COMPATIBILITY).
7. **First-party domain plugins remain ActivityStreams-agnostic.** Object Projections
   detects them and consumes their public service functions; it does not inspect their
   private SQL or metadata schema. Third-party plugins may register their own transformer.

## 5. Public API

```
axismundi_op_register_object_transformer( string $id, array $args ) : true|WP_Error
axismundi_op_register_collection_transformer( string $id, array $args ) : true|WP_Error
    // $args: supports(callable), object_uri|collection_uri(callable),
    //        transform(callable):array|WP_Error, [visible(callable):bool], [priority=10]

axismundi_op_transform_object( mixed $source ) : array|WP_Error
axismundi_op_transform_collection( mixed $source ) : array|WP_Error
```

Registration is done on the `axismundi_op_register_transformers` action.

The built-in `core-post-article` transformer is available in standalone mode. Adapters
may filter `axismundi_op_post_object_uri` to preserve an established external id and
`axismundi_op_post_actor_uri` to bridge another Actor provider.

See TRANSFORMERS.md (contract detail), ROUTING.md (URI contract), COMPATIBILITY.md
(ActivityPub co-existence), PHASES.md (roadmap).
