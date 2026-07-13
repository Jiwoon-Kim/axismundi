# Axismundi Actors — Routing

> Status: **Living specification. Phase 2 implemented.**
> Two URLs per actor: an immutable identity URI and a mutable human alias. Plain
> query endpoints work without pretty permalinks; pretty aliases are sugar.

## 0. The URLs

```
canonical identity   {home}/actors/{uuid}          → actor_uri (federation id; the immutable UUID)
  plain fallback     {home}/?ax_actor={uuid}       → same target, works without pretty permalinks
human alias (mutable){home}/@{preferred_username}/ → profile hub
```

The alias is a convenience over the identity. Resolving the alias always yields the
canonical identity; canonical links (and any future JSON-LD `id`) use `/actors/{uuid}`,
never the alias. The DB `id` is **never** in a URL (`/actors/42` is forbidden) — only
the `uuid`, which survives re-import and domain moves.

## 1. Canonical identity — `/actors/{uuid}` (+ `/?ax_actor={uuid}` fallback)

- Pretty route: `^actors/([0-9a-f-]{36})/?$` → `index.php?ax_actor={uuid}`, plus the
  plain query var `ax_actor` (registered via `query_vars` + `parse_request`) so the
  same target resolves with pretty permalinks disabled and needs no rewrite to
  function.
- Resolves the identity row by `uuid`; 404 when absent, `disabled`, `internal` (to a
  non-privileged viewer), or `tombstone` (410 once federation lands).
- This is the stable target for federation and for any link that must survive a
  username change. **Remote** actors are served from their own remote
  `canonical_uri`; they are never re-served under our `/actors/{uuid}`.

## 2. Human alias — `/@{preferred_username}/`

- Pretty rewrite: `^@([^/]+)/?$` → `index.php?ax_actor_handle=$matches[1]`, plus a
  plain fallback `/?ax_actor_handle={username}`.
- Resolution: `local_handle_key → local actor → identity` (remote actors are not
  reachable via `/@handle/`; their handles are not locally unique). Confirm the
  canonical `actor_uri`, then render the hub. A username change moves the alias; the
  identity URI is unchanged.
- Only `status = public` actors render here. `internal` / `disabled` / `tombstone`
  → 404 for non-privileged viewers (owner / `manage_options` may preview — see
  SECURITY).

The `@` prefix avoids collision with existing top-level slugs (pages, `/author/`,
`/media/`). A reserved-handle guard rejects usernames that would shadow routing or
another actor (`actors`, `ap`, `author`, `media`, `notes`, `feed`, `wp-*`, etc.), and
`local_handle_key` is `UNIQUE` across local actors (DATA-MODEL §3) so a local handle
resolves to exactly one actor, while remote actors may share a handle.

## 3. Hub content & projection sub-routes

The hub `/@{username}/` renders:

- actor header (name, avatar, bio, type badge — all read live for local),
- projection navigation (PROJECTIONS §1), each link pointing at the domain
  plugin's existing archive URL.

Projection archives keep their **own** existing URLs in v0.1 — Actors links out,
it does not proxy:

```
/@alice/            actor hub (Actors)
/author/alice/      Posts projection (core)
/media/author/alice/  Media projection (Media Library)
```

Namespaced sub-routes under the handle (`/@alice/activity/`, `/@alice/outbox`) are
**reserved** for later phases (Activities, Federation) and are not minted in v0.1.

## 4. Rewrite hygiene

- Register both `/actors/{uuid}` and `/@handle/` rewrites on activation and flush
  **once**; remove them on deactivation and flush. The public query vars remain the
  routing foundation, so `/?ax_actor={uuid}` and `/?ax_actor_handle={handle}` work
  with pretty permalinks disabled.
- No global `pre_get_posts` hijack; resolution is confined to the registered query
  vars, mirroring the Media Library routing discipline.
