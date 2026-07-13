# Axismundi Actors — Specification

> Status: **Living specification. Pre-implementation (docs lock, no code yet).**
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
and screens; Actors only holds identity and wires the **projections** (Posts,
Media, Notes, …) under one actor.

```
Actor  (identity hub)
├─ Posts projection   → core /author/{user}/
├─ Media projection   → Media Library /media/author/{user}/
├─ (later) Notes, Collections, Folders, Activity
```

## 2. Invariants (do not break across phases)

1. **Actor ≠ WordPress user.** A `WP_User` is a login account. An Actor is an
   identity record. A local **Person** actor *links* to a user (`local_user_id`),
   but Site / Organization / Service / remote actors have no user at all.
2. **One actor, many projections.** An actor is never per-screen. Author, Media,
   Notes, Activity are views *of the same actor*, registered by their own plugins.
3. **Identity URI is immutable; the profile URL is not.** `actor_uri`
   (`/?ax_actor={uuid}`) never changes for the life of the actor. The human alias
   `/@{preferred_username}/` may change when the username changes. Federation
   identity must never be bound to the mutable alias.
4. **Four identifiers are distinct and never conflated:** `local_user_id`,
   `actor.id` (local DB key), `identity.uuid` / `actor_uri` (federation identity),
   and `profile_url` (human alias). See DATA-MODEL §2.
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
`canonical_uri`. This is the whole reason for the split: one identity layer, many
object kinds. See DATA-MODEL for the full schema and constraints.

## 4. Seeded actors (on activation)

Two actors are seeded, both `internal` until an admin explicitly publishes them:

- **Site actor** — `actor_scope=site`, default `actor_type=Application`
  (configurable to `Organization`), `preferred_username` = `blog` or the site
  slug, no `local_user_id`.
- **Site-owner Person actor** — `actor_scope=user`, `actor_type=Person`, linked to
  the designated site-owner user (default: the activating administrator; stored in
  the `ax_actors_site_owner_user_id` option).

Other users are **not** bulk-seeded or bulk-published. A user's Person actor is
created lazily via `ensure_for_user()` the first time it is needed (e.g. a Media
projection link), and stays `internal` until published.

Managing the site actor (via `manage_options`) does **not** make the managing
user the same identity as the site — Person and Site actors are distinct records.

## 5. v0.1 scope

- `wp_ax_identities` + `wp_ax_actors` schema (dbDelta) with immutable UUID / URI.
- Actor repository: create, `get_by_uri`, `get_by_uuid`, `get_for_user`,
  `ensure_for_user`, seed on activation, tombstone on user delete.
- Plain-query identity endpoint `/?ax_actor={uuid}` + human `/@{username}/` hub
  with a block template, actor header, and projection navigation.
- **Projection registry** (`axismundi_actors_register_projection`) with the built-in
  `posts` projection; a public API for other plugins. See PROJECTIONS.
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
