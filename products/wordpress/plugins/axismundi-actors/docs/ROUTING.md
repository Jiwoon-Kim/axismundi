# Axismundi Actors — Routing

> Status: **Living specification. Phase 2 implemented.**
> Two URLs per actor: an immutable identity URI and a mutable human alias. Plain
> query endpoints work without pretty permalinks; pretty aliases are sugar.

## 0. The URLs

```
canonical identity   {home}/actors/{uuid}          → actor_uri (federation id; the immutable UUID)
  plain fallback     {home}/?ax_actor={uuid}       → same target, works without pretty permalinks
human alias (mutable){home}/@{preferred_username}/ → profile hub
remote proxy alias   {home}/@{handle}@{host}       → cached remote Person  (see §2.1)
                     {home}/group/@{handle}@{host} → cached remote Group   (see §2.1)
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
                    → stored per user, switched from the admin bar account menu
                    → axismundi_actors_acting_actor()
                    → implemented (includes/acting-actor.php); no domain plugin
                      records it yet — Event storage is the next slice

user_default_actor  the Actor a user falls back to before choosing one
                    → no shared resolver yet; see below
```

**`profile_actor` must never stand in for `acting_actor`.** Visiting an
Organization's profile page would then publish under that Organization's name — and
the code would look correct, because on that page the value really is that
Organization. This is why the routing function is called `profile_actor()` and not
`current_actor()`: "current" invites exactly that substitution. The switcher has its
own resolver in `includes/acting-actor.php` and does not touch this one; neither
falls back to the other.

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
publicness rule for all three.

The contract they converge on now exists — `axismundi_actors_can_act_as()` and
`axismundi_actors_default_acting_actor()`, which require a local, handle-locked,
non-disabled Actor and deliberately do **not** require it to be public. The three
above still answer their own questions and have not been moved onto it: each move
changes who may author in a shipped surface, so they convert one at a time with their
own audit, starting with the Calendar pair when Event storage lands. Until then: do
not add a fourth resolver — call `axismundi_actors_acting_actor()`.

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
  username change.
- **Remote** actors: their federation `id` is always their own remote `canonical_uri`,
  and that is what we publish. But `/actors/{uuid}` does *render* a cached remote actor
  as a local proxy view. Measured 2026-10-04:
  `/actors/2394f52b-bc99-4a7c-8713-b0dc5aeff8de` answers `200` for the cached
  `lemmykorea` Group, and `/actors/ac69d6bd-…` for the cached NodeBB `general` Group.
  An earlier revision of this section stated that remote actors "are never re-served
  under our `/actors/{uuid}`". That is false of the shipped behaviour.
  **Open — owner's call:** either the proxy view stays and must carry a canonical
  pointer to the remote `canonical_uri` (otherwise our domain competes for another
  server's actor URL), or it is withdrawn. Do not cite either sentence as the contract
  until this is settled.

## 2. Human alias — `/@{preferred_username}/`

- Pretty rewrite: `^@([^/]+)/?$` → `index.php?ax_actor_handle=$matches[1]`, plus a
  plain fallback `/?ax_actor_handle={username}`.
- Resolution: `local_handle_key → local actor → identity`. A bare handle with no host
  is always local; a handle containing `@` is a remote proxy alias and takes the
  separate path in §2.1. Confirm the canonical `actor_uri`, then render the hub. A
  username change moves the alias; the identity URI is unchanged.
- Only `status = public` actors render here. `internal` / `disabled` / `tombstone`
  → 404 for non-privileged viewers (owner / `manage_options` may preview — see
  SECURITY).

The `@` prefix avoids collision with existing top-level slugs (pages, `/author/`,
`/media/`). A reserved-handle guard rejects usernames that would shadow routing or
another actor (`actors`, `ap`, `author`, `media`, `notes`, `feed`, `wp-*`, etc.), and
`local_handle_key` is `UNIQUE` across local actors (DATA-MODEL §3) so a local handle
resolves to exactly one actor, while remote actors may share a handle.

## 2.1. Remote proxy aliases — `/@{handle}@{host}`, `/group/@{handle}@{host}`

A handle containing `@` addresses a **cached remote** Actor. Two namespaces, because a
handle alone does not identify an Actor: Lemmy lets a Person and a Community share one
on a host, so answering with whichever was cached first would make the same address mean
different things on different sites.

```
^@([^/]+)/?$                  → ax_actor_handle            (kind defaults to Person)
^group/@([^/]+)/?$            → ax_actor_handle + kind=Group
```

### Cached is not the same as addressable

This is the contract, and it is the one thing to carry away from this section:

> A remote Actor is reachable by handle **only** if it holds a verified `acct` address
> row of the matching kind. Being in the cache is not enough.

Resolution requires a `wp_ax_actor_addresses` row with `address_type = 'acct'`,
`actor_kind` equal to the namespace's kind, and `status = 'primary'`. The only writer of
those rows is `axismundi_actors_record_verified_acct_address()`, which runs after
WebFinger verification. So the dividing line is **how the Actor was discovered**, not
whether it is a Person or a Group:

```text
@user@host entered      → WebFinger → acct row written  → /@h@host resolves
canonical URL entered   → direct AS fetch, no WebFinger → no acct row → 404
```

Measured 2026-10-04:

| Actor | discovered via | acct rows | `/…@host` |
|---|---|---|---|
| `thaumiel999@mastodon.social` (Person) | `@user@host` | 1 | `200` |
| `lemmykorea` (Group, `lemmy.world/c/lemmykorea`) | canonical URL | 0 | `404` |
| `general` (Group, NodeBB `/category/2/…`) | canonical URL | 0 | `404` |

A Person discovered by canonical URL would 404 the same way. The asymmetry is not
Person-versus-Group.

### We do not pay for Lemmy's ambiguity

Owner's decision, 2026-10-04. Lemmy publishes **two** `rel=self` links under one
`acct:` — `/u/foo` as Person and `/c/foo` as Group — and distinguishes them only in each
link's `properties` map. That is a defect in Lemmy: `acct:` is one address space and
Lemmy maps two disjoint namespaces onto it, then depends on unspecified consumer
behaviour ("the last link wins") to disambiguate.

`axismundi_actors_discover_remote_actor()` takes the **first** `self` link with an
ActivityPub media type and does not read `properties`. On a Lemmy handle collision we
therefore resolve the Person, which is what current Mastodon and Misskey are reported to
do. **We are not adding `properties` parsing to match Lemmy.** The cost lands on us for a
problem Lemmy created, and the resulting address would still be ambiguous.

The accepted consequence: a remote Actor discovered by canonical URL is addressable only
at `/actors/{uuid}` (§1), never by handle.

### Deferred option, if this is revisited

Record an `acct` row for a direct-fetch Actor by running one WebFinger round trip under
the **existing** first-`self` rule and accepting it only when it resolves back to the
same Actor. A collision simply yields no `acct` row, which is correct — that address
really is ambiguous. No type logic, one extra outbound request. This would fix
`lemmykorea` (it has no competing Person) without taking on Lemmy's model. Not
implemented; outbound requests carry a disclosure obligation.

### Open

- **`self` link normalisation.** `axismundi_actors_webfinger_self_link()` returns early
  when any `self` is already present, so it adds one when there are none but does not
  reduce two to one. A misbehaving filter could therefore publish the exact shape we
  just rejected in Lemmy. The guard belongs at the end of the response: keep only the
  `self` for the canonical actor URI, or fail the response. WebFinger output is a public
  surface, so this needs the owner's approval before it ships.
- Local handles cannot collide across kinds — `wp_ax_actors.local_handle_key` carries a
  `UNIQUE` index and is independent of `actor_type` (verified against the live schema),
  so `acct:foo@host` always means exactly one Actor here. Nothing to decide; recorded so
  the Lemmy discussion is not reopened against us.

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
