# Axismundi Object Projections — specification

> Status: **Phase 0–1 (contract + registry/renderer) implemented.** No table, no rewrite,
> no REST route, no HTTP content negotiation yet (that is Phase 2). This package owns the
> **projection contract** and the **JSON-LD serialization** of one object/collection —
> nothing else.

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
- Object/collection representation + (Phase 2) content negotiation on the existing URL.
- The Core Post → `Article` transformer (Phase 2/3).
- Registering the `articles` projection on the Actor profile.
- Object lifecycle *events* (publish/update/delete) — emitted, not stored.

**Does not own**

- `wp_ax_activities`, inbox/outbox, Follow/Like/Announce, signatures, delivery, retry.
- Note / media object storage itself (the domain plugins own it).
- The official ActivityPub plugin's internal models.

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
   error (its own `WP_Error`), and a not-public source (`ax_op_not_public`). A future
   router maps these to 404 vs 500 vs 404 respectively — never silently to an empty 200.
5. **Visibility is a callback, not the transformer body.** Public/private is decided by a
   registered `visible` callback, so the transform stays a pure mapping.
6. **The official ActivityPub plugin is not a dependency.** Object Projections works fully
   standalone; when ActivityPub *is* active, only one negotiator owns a URL (§COMPATIBILITY).

## 5. Public API (Phase 1)

```
axismundi_op_register_object_transformer( string $id, array $args ) : true|WP_Error
axismundi_op_register_collection_transformer( string $id, array $args ) : true|WP_Error
    // $args: supports(callable), object_uri|collection_uri(callable),
    //        transform(callable):array|WP_Error, [visible(callable):bool], [priority=10]

axismundi_op_transform_object( mixed $source ) : array|WP_Error
axismundi_op_transform_collection( mixed $source ) : array|WP_Error
```

Registration is done on the `axismundi_op_register_transformers` action.

See TRANSFORMERS.md (contract detail), ROUTING.md (URI contract), COMPATIBILITY.md
(ActivityPub co-existence), PHASES.md (roadmap).
