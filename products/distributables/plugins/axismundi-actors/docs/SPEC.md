# Axismundi Actors — Specification

> Status: **Living specification. Phases 0–2 implemented.**
> Plugin brand: **Axismundi Actors** · slug: `axismundi-actors`
> This document defines the product, its invariants, the v0.1 scope, and the
> non-goals. Data model, routing, security, projections, and phases live in the
> sibling docs. **This is the contract-lock pass: `actor_uri` format and the
> projection registry are frozen here before any code.**

## 1. Purpose

Give every publishable identity on the site — each local person, the site itself,
and (later) remote federated actors — **one stable identity record** and **one
human profile hub**, without collapsing that identity into the WordPress user
account and without owning the content it points at.

WordPress already answers "what did user 12 write?" (`/author/`). It has no home
for "who is this identity, across posts, media, folders, collections, and
activity?" Axismundi Actors is that home. Each domain plugin keeps its own storage
and screens; Actors only holds identity and wires the **projections** (Activity,
Articles, Media, Notes, …) under one actor. The profile's primary surface is an
**activity feed** (owned by Axismundi Activities), not a post list.

```
Actor  (identity hub)
├─ Activity feed      → owned by Axismundi Activities (primary surface)
├─ Articles projection → core /author/{user_nicename}/   (a future registrar)
├─ Media projection    → Media Library /media/author/{user_nicename}/
├─ (later) Notes, Collections, Folders
```

Actors ships **no** built-in projection; each tab is registered by its own domain
plugin (PROJECTIONS §4). The Actor handle and the `{user_nicename}` archive slug are
independent (ROUTING §0.1).

## 2. Invariants (do not break across phases)

1. **Actor ≠ WordPress user.** A `WP_User` is a login account. An Actor is an
   identity record. A local **Person** actor *links* to a user (`local_user_id`),
   but Site / Organization / Service / remote actors have no user at all.
2. **One actor, many projections.** An actor is never per-screen. Author, Media,
   Notes, Activity are views *of the same actor*, registered by their own plugins.
3. **The UUID is the immutable anchor; URLs are not eternal constants.** `uuid`
   never changes. The local `actor_uri` = `/actors/{uuid}` (plain fallback
   `/?ax_actor={uuid}`) is *derived* from that uuid + the site URL and only changes
   if the whole site migrates domains — an explicit, federation-visible event, not
   a silent break. The human alias `/@{preferred_username}/` changes freely with the
   username. Federation identity binds to the uuid / `actor_uri`, never the alias.
4. **These identifiers are distinct and never conflated:** `local_user_id` (login
   account), `identity.id` (local DB key, also the actor row's PK; changes on
   re-import), `identity.uuid` (immutable anchor) → `actor_uri` (`/actors/{uuid}`;
   federation identity), and `profile_url` (`/@handle/`; human alias). See
   DATA-MODEL §1.
5. **Actors owns identity only.** It does not own or store Likes, Collections,
   Activities, folder membership, or content. It coordinates **URL, visibility,
   and ordering** of projections and nothing else.
6. **Record existence ≠ public exposure.** Creating an actor record and publishing
   its profile are separate steps. An `internal` actor is usable as a membership
   key (collections, shared folders) but its `/@handle/` returns 404.
7. **Local person profile data is read live from the user.** Display name, bio,
   URL, and avatar are read from `WP_User` / usermeta at render time — never copied
   into the actor row (copying causes drift). `payload_json` is for **remote**
   snapshots only.
8. **Email is never exposed** in any HTML or REST projection of an actor.
9. **Deleting a user tombstones the actor, never deletes it** — federation
   identity and back-references must survive.

## 3. Identity model

The stable identity is split out of the actor profile into a shared **identity
registry** (`wp_ax_identities`) so that S2S actors *and* non-actor objects
(collections, folders, media, activities) can reuse one UUID/URI layer. An actor
row is a *profile* attached to one identity row.

```
wp_ax_identities   uuid + canonical_uri + object_kind + origin + status
        ▲
        │ identity_id (1:1 for actors)
wp_ax_actors       actor_type + actor_scope + preferred_username + local_user_id …
```

`actor_uri` is **not** a column on the actor row — it *is* the linked identity's
`canonical_uri`. The actor row is a **1:1 specialization** of its identity, keyed by
`identity_id` (there is no separate `actor.id`): one object, one DB key. This is the
whole reason for the split: one identity layer, many object kinds. See DATA-MODEL
for the full schema and constraints.

The repository returns actors as a read-only value object (`Axismundi_Actor`) whose
public interface is frozen in PROJECTIONS §1.5, so domain plugins never touch the
tables directly.

## 4. Seeded actors (on activation)

Seeding is `internal` and must **never** depend on a specific admin account
existing — WordPress has no single `site_owner` (`user_id=1`, "first admin", and the
`admin_email` user are all unreliable). So:

- **Site actor — always created** on activation. `actor_scope=site`, default
  `actor_type=Application` (configurable to `Organization`),
  `preferred_username` = `blog` or the site slug, no `local_user_id`. Activation
  success never depends on any user existing.
- **Site-owner Person actor — created only when the activating current user is a
  valid administrator.** On CLI / no current user, the Person seed is **skipped**
  (activation still succeeds). The site owner is otherwise chosen **explicitly**
  later in Settings (`ax_actors_site_owner_user_id`).

Other users are **not** bulk-seeded or bulk-published. A user's Person actor is
created lazily via `ensure_for_user()` the first time it is needed (e.g. a Media
projection link), and stays `internal` until published.

Managing the site actor (via `manage_options`) does **not** make the managing
user the same identity as the site — Person and Site actors are distinct records.

### 4.1 Actor types & managed actors

`actor_type` follows the ActivityStreams actor vocabulary; the right type is a
per-actor decision, not one giant site actor:

| Use | `actor_type` | provisioning |
|---|---|---|
| The site itself / auto-publisher | `Application` (default) | site seed (`scope=site`) |
| A real company / org / team | `Organization` | site or managed |
| A local person | `Person` | `scope=user` (1:1 WP user) |
| Forum / Lemmy community / subreddit-like space | `Group` | **managed** (reserved) |
| Automated geodata / feed publisher | `Service` (or `Application`) | **managed** (reserved) |

- The **default site actor stays `Application`** (configurable to `Organization`).
- **Forums and services are their own `managed` actors**, not tabs on the site
  actor — they Follow, receive, post, and Announce, so they need their own identity,
  inbox, and outbox. `managed` scope + the `wp_ax_actor_managers` table are reserved
  (DATA-MODEL §3); v0.1 ships only `site` and `user`.
- **A place is not an actor.** `Place` (e.g. "Busan Station") and a place/map
  `Collection` are objects in the identity registry with `attributedTo` an actor and
  **no** inbox/outbox — created by the geodata / Media Library plugins, never by
  Actors (DATA-MODEL §2).

## 5. v0.1 scope

- `wp_ax_identities` + `wp_ax_actors` schema (dbDelta) with immutable UUID / URI.
- Actor repository: create, `get_by_uri`, `get_by_uuid`, `get_for_user`,
  `get_by_handle`, `ensure_for_user` (handle-less), `register_handle` (one-time,
  immutable), seed on activation, tombstone on user delete.
- Handle lifecycle: a user's actor is handle-less until activation; the handle is
  registered once and then immutable; public exposure requires a locked handle.
- Canonical identity endpoint `/actors/{uuid}` (plain fallback `/?ax_actor={uuid}`)
  + human `/@{username}/` hub with a block template, actor header, and projection
  navigation (the profile header ships in Phase 2; registry-driven navigation in
  Phase 3).
- **Projection registry** (`axismundi_actors_register_projection`) — a public API for
  domain plugins. Actors ships **no** built-in projection (the profile is
  activity-first; header-only until a plugin registers). See PROJECTIONS.
- Admin: a public/internal toggle on the user profile screen; a "Actor profile"
  row link on Users; view/edit capabilities.
- Media Library registers `media` / `folders` projections; Collection and Shared
  Folder owner/member keys move to `actor_uri` (Media Library Phase 5).

## 6. Non-goals (v0.1)

Activity table · Like / Undo · JSON-LD transformer & content negotiation ·
inbox / outbox · Follow / followers / following · HTTP signatures · remote actor
fetch / cache / delivery · Organization / Service editing UI · `wp_ax_actor_managers`
table (v0.1 uses `manage_options` + the site-owner option). These are later phases
or separate plugins (Axismundi Activities, Axismundi Federation); the schema and
routing here are shaped so they attach without a rewrite.
