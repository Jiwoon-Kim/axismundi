# Compatibility with the official ActivityPub plugin

> Status: **Standalone gate implemented; adapter not yet built.** Object Projections works
> standalone. This document fixes how it must behave when Automattic's ActivityPub plugin
> (tested against 9.0.2) is *also* active.

## 1. One negotiator per URL

Both plugins want to answer the *same* WordPress URL with JSON-LD. They cannot both own
content negotiation on it. Therefore:

- Object Projections always provides the **transformer API + renderer**.
- It owns the standalone negotiation router only when `ACTIVITYPUB_PLUGIN_VERSION` is not
  defined (filterable through `axismundi_op_standalone_router_enabled`).
- When ActivityPub *is* active, the standalone router stays **off**, and a thin
  **compatibility adapter** feeds Axismundi's projections through the official plugin's
  extension points instead of registering a competing route.

Detecting co-activation and disabling our own WebFinger / NodeInfo / `/@handle` /
negotiation ownership is required — silently double-owning a route is the failure mode
observed when Axismundi Actors and ActivityPub 9.0.2 were enabled together (Actors'
rewrite rules matched first and shadowed the official WebFinger/NodeInfo).

## 2. Preserve the official plugin's per-post id choice

The official Post transformer's `get_id()` is **not** unconditionally `/?p={ID}`:

```
post_id > activitypub_last_post_with_permalink_as_id  →  /?p={ID}
otherwise (older post)                                →  the permalink (legacy id)
```

So in **adapter mode**, Object Projections must emit, for a given post, **the same id the
official plugin would** — it must respect that per-post legacy/`?p=` decision, not force
its own `/?p={ID}`. Minting a different id for a post the official plugin already
federates would split it into two distinct fediverse objects.

Standalone mode (no official plugin) uses the plain stable ids in ROUTING.md §2.

## 3. Division of labor in adapter mode

| Concern                              | Owner                         |
|--------------------------------------|-------------------------------|
| Identity / profile / repository      | Axismundi Actors              |
| Object → AS mapping (transformers)   | Axismundi Object Projections  |
| Inbox / outbox / signatures / delivery | Official ActivityPub plugin |
| Content negotiation on the URL       | Official ActivityPub plugin (via its extension points) |

Relevant official extension points to target when the adapter is built:
`activitypub_pre_get_by_id`, `activitypub_pre_get_by_username`, `webfinger_data`
(and see upstream issues #3073 pluggable virtual actors, #1975 dedicated actor profiles).

## 4. Not sufficient

Setting `ACTIVITYPUB_DISABLE_REWRITES` alone is **not** a solution: it would silence the
official routes while Axismundi's WebFinger still fails to hand back the official Actor's
`self` JSON-LD link, leaving federation discovery incomplete. The adapter must actively
bridge, not merely suppress.
