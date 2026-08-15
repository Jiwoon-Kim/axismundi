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

## 0.05. Three Actors, three names

Any request can have up to three different Actors in play, and confusing two of them
is a privilege bug rather than a naming untidiness. The names are fixed; nothing may
resolve one from another.

```
profile_actor       the Actor this request is ABOUT
                    → set by routing after the visibility gate
                    → axismundi_actors_profile_actor()
                    → implemented

acting_actor        the local Actor a signed-in user has CHOSEN to publish as
                    → attributedTo, Create.actor, Event organizer, Invite.actor
                    → session-scoped, switched from the admin bar
                    → NOT IMPLEMENTED

user_default_actor  the Actor a user falls back to before choosing one
                    → no shared resolver yet; see below
```

**`profile_actor` must never stand in for `acting_actor`.** Visiting an
Organization's profile page would then publish under that Organization's name — and
the code would look correct, because on that page the value really is that
Organization. This is why the routing function is called `profile_actor()` and not
`current_actor()`: "current" invites exactly that substitution. The switcher, when it
arrives, adds its own resolver and does not touch this one.

Membership is re-checked on every mutation, not at switch time. Being able to select
an Actor is not authority to act as it later — manager roles are revocable, and the
selection is a stored preference, not a capability.

Switching the acting Actor is **not** switching WordPress user. Capabilities, the
session, and the editor recorded in `post_author` all stay with the logged-in user;
only the published identity changes.

### `user_default_actor` is deliberately not unified yet

Three resolvers exist today, and they disagree on purpose:

| resolver | requires |
|---|---|
| `axismundi_act_current_local_actor()` | Person, `public`, handle locked |
| `axismundi_cal_signed_in_actor_uri()` | nothing — whether a profile is published has no bearing on who runs an Event |
| `axismundi_cal_current_actor_uri()` | `federation_ready`, via `axismundi_op_local_author_actor_uri()` |

Each was right for its own question, and collapsing them now would have to pick one
publicness rule for all three. The switcher slice defines a "default acting Actor"
contract and converges them there. Until then: do not add a fourth.

## 0.1. The Actor handle is NOT the WordPress profile name

The Actor handle and the WordPress author/media archive slugs are **independent** —
different names, different URLs, connected only by `local_user_id`:

```
WP account       user_login / user_nicename
Posts archive    /author/{user_nicename}/            (core; e.g. /author/kimjiwoon96/)
Media archive    /media/author/{user_nicename}/…     (Media Library; owned by post_author)
Actor hub        /@{actor_handle}/                   (e.g. /@thaumiel/)
Actor identity   /actors/{uuid}
```

- Changing the Actor handle never changes `user_nicename` or the author/media URLs,
  and renaming the WP user never changes an already-registered Actor handle. The
  activation UI (Phase 4) offers to *seed* the handle from `user_nicename` or the
  nickname, but that is a one-time copy, not a live link.
- **Media archive URLs stay put.** Media Library runs without Actors, ownership is
  `post_author`, and its `/media/author/…/folder/…` paths (plus the plain-id
  fallback) must not move when an Actor handle changes. Actors *links to* the Media
  archive as a projection; it never becomes its canonical URL.
- A future Actor-handle-centric view (e.g. `/@thaumiel/posts/`) is only ever an
  **alias/redirect** onto the existing archive, never a replacement.

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
