# Axismundi Actors — Routing

> Status: **Living specification. Pre-implementation.**
> Two URLs per actor: an immutable identity URI and a mutable human alias. Plain
> query endpoints work without pretty permalinks; pretty aliases are sugar.

## 0. The two URLs

```
identity (immutable)   {home}/?ax_actor={uuid}          → actor_uri (federation identity)
human alias (mutable)  {home}/@{preferred_username}/     → profile hub
```

The alias is a convenience over the identity. Resolving the alias always yields the
canonical identity; canonical links (and any future JSON-LD `id`) use the identity
URI, never the alias.

## 1. Identity endpoint — `/?ax_actor={uuid}`

- A plain query var `ax_actor` (registered via `query_vars` + `parse_request`),
  so it works with plain permalinks and needs no rewrite flush.
- Resolves the identity row by `uuid`; 404 when absent, `disabled`, `internal`
  (to a non-privileged viewer), or `tombstone`.
- This is the stable target for federation and for any link that must survive a
  username change.

## 2. Human alias — `/@{preferred_username}/`

- Pretty rewrite: `^@([^/]+)/?$` → `index.php?ax_actor_handle=$matches[1]`, plus a
  plain fallback `/?ax_actor_handle={username}`.
- Resolution: `preferred_username → actor → identity`. Confirm the canonical
  `actor_uri`, then render the hub. A username change moves the alias; the identity
  URI is unchanged.
- Only `status = public` actors render here. `internal` / `disabled` / `tombstone`
  → 404 for non-privileged viewers (owner / `manage_options` may preview — see
  SECURITY).

The `@` prefix avoids collision with existing top-level slugs (pages, `/author/`,
`/media/`). A reserved-handle guard rejects usernames that would shadow routing
(`author`, `media`, `notes`, `feed`, `wp-*`, etc.).

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

- Register the `@handle` rewrite on activation and flush **once**; remove on
  deactivation and flush. The identity endpoint is a pure query var (no rewrite),
  so the plugin still functions with pretty permalinks disabled.
- No global `pre_get_posts` hijack; resolution is confined to the registered query
  vars, mirroring the Media Library routing discipline.
