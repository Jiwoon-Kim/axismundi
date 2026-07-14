# Axismundi Actors — Phases

> Status: **Living implementation plan. Phases 0–4 plus local discovery shipped.** Entry · build ·
> acceptance · non-goals per phase. Cross-refs:
> SPEC, DATA-MODEL, ROUTING, SECURITY, PROJECTIONS.

## Phase 0 — Docs & scaffold *(shipped in 0.0.1)*

**Build:** the six docs (SPEC, DATA-MODEL, ROUTING, SECURITY, PROJECTIONS,
PHASES) locking the `actor_uri` / identity format and the projection registry
contract; plugin scaffold (main file header, ABSPATH guard, version constant, no
functional code yet).
**Acceptance:** frozen after the design-review pass — the identity-registry split;
identifiers `local_user_id` / `identity.id` (= actor PK) / `identity.uuid`
(immutable anchor) → `actor_uri` = **`/actors/{uuid}`** (plain fallback
`/?ax_actor={uuid}`) / `profile_url` (`/@handle/`); the actor row as a 1:1
specialization keyed by `identity_id` (no separate `actor.id`); `uuid` immutable
while local `canonical_uri` is a rebuildable cache; the `Axismundi_Actor` value
object and the projection registry signature. No schema or route is implemented.
**Non-goals:** any table creation or routing code.

## Phase 1 — Repository *(shipped in 0.0.2)*

**Entry:** Phase 0.
**Build:** `wp_ax_identities` + `wp_ax_actors` via dbDelta + schema-version option;
`identity_id` as the actor PK with a **logical** FK only (no physical `FOREIGN KEY`
/ `CASCADE` — it would fight the tombstone contract); integrity enforced by the
repository (identity exists + `object_kind='actor'`, wrapped in a transaction);
create / `get_by_uuid` / `get_by_uri` / `get_for_user` / `ensure_for_user`; immutable
UUID + rebuildable `canonical_uri`; always-seed the site actor (idempotent) +
conditional site-owner Person seed; `deleted_user` → identity `tombstone`.
**Acceptance:** UUID and `actor_uri` unchanged after a handle change (and unchanged
across a simulated domain move); a second actor cannot be created for the same user
(unique `local_user_id`); a **local** handle collision is blocked while the **same
remote handle is allowed on multiple remote actors**; CLI activation succeeds with
**no** Person seed (only the site actor); user delete tombstones (never deletes); an
audit finds no actor row without its identity; reactivation creates no duplicate
seed; deactivate/reactivate preserves rows.
**Non-goals:** any public page; projections; admin UI.

## Phase 2 — Actor profile page *(shipped in 0.0.3)*

**Entry:** Phase 1.
**Build:** `/actors/{uuid}` canonical identity endpoint (+ `/?ax_actor={uuid}` plain
fallback) + `/@{username}/` alias (rewrite + plain fallback, reserved-handle guard);
a plugin block template; actor
header (live-read name/avatar/bio/type badge) + projection navigation region.
**Acceptance:** `internal`/`disabled`/`tombstone` actors 404 for public viewers
(owner/`manage_options` preview only); a `public` actor **with a registered handle**
renders; the identity URI is unchanged across a handle registration; works with
pretty permalinks off.
**Non-goals:** JSON-LD; sub-routes under the handle.

**Implementation note:** Phase 2 renders only the actor header. The projection
navigation region becomes visible when Phase 3 ships its registry; Phase 2 does
not invent placeholder projection links or query another plugin's domain.

## Phase 2.1 — Handle immutability & deferred registration *(shipped in 0.0.4)*

**Entry:** Phase 2. A correction Phase 2 surfaced: a handle must be **stable once
set** (like a Mastodon acct), and it should be chosen at the user's explicit
activation, not silently minted from `user_nicename` at creation.
**Build:** schema v2 — `preferred_username` nullable + `handle_locked_at`;
`ensure_for_user()` creates a **handle-less** internal actor; `register_handle()`
(one-time, normalizes + reserved/dup checks + stamps `handle_locked_at`, refuses when
locked) replaces the old mutable `set_handle()`; `handle_candidates()` (nicename /
nickname, never `user_login`); the public gate additionally requires a locked handle;
one-off upgrade backfill locks existing handled actors.
**Acceptance:** a fresh Person is handle-less; the first `register_handle` locks it
and a second is refused; UUID / `actor_uri` are unchanged; a `public` actor with no
registered handle is not publicly viewable (owner/admin preview only); a taken handle
is rejected. Actor activation stays separate from WordPress "Anyone can register".
**Non-goals:** the activation UI (Settings/profile, Phase 4); any handle-change path
(future admin recovery + alias/`Move`).

## Phase 3 — Projection registry *(shipped in 0.0.5; built-in projection removed in 0.0.6)*

**Entry:** Phase 2.
**Build:** `axismundi_actors_register_projection()` + the
`axismundi_actors_register_projections` hook; ordering / visibility / count
resolution; duplicate-id replacement with a `_doing_it_wrong` notice; callback
isolation; the dynamic navigation block.
**Acceptance:** a plugin can add a projection purely via the public API (no Actors
edit); ordering follows `priority`; a hidden / empty-URL projection is omitted; a
throwing callback is isolated; deactivating a plugin removes its projection; an
`internal` actor exposes none to anon (owner preview may).
**Correction (0.0.6):** Actors ships **no** built-in projection. The profile's
primary surface is an **activity feed** owned by Axismundi Activities; the earlier
built-in `posts` projection was premature (activity-first, and a post-list decision
is not Actors' to own) and was removed. Articles / Notes / Media are each registered
by their own plugins; the core-post projection is `articles`, not `posts`.
**Non-goals:** the activity feed itself (Axismundi Activities); Articles/Media/Notes
projections (owned by those plugins).

## Phase 4 — Actor activation & profile management

**Entry:** Phase 3. **Design prerequisites (now locked):** the handle alias-history
contract (DATA-MODEL §7), the handle ≠ profile-name separation (ROUTING §0.1), and
the profile-presentation schema (DATA-MODEL §8). Users need a surface to *make and
manage* an actor before public rendering matters.

**Phase 4a — management screens + activation wizard.**
- `wp-admin/profile.php`: an "Actor profile" summary panel via `show_user_profile` /
  `edit_user_profile` — inactive shows *Activate actor profile*; active shows
  `@handle`, status, the public link, and *Manage*.
- `wp-admin/users.php`: an **Actor** column (`Not activated / Internal / Public /
  Tombstone`) with Manage / View; activating **another** user's actor needs a
  capability.
- A dedicated `add_users_page()` screen for the **activation wizard**: choose a handle
  (candidates seeded from `user_nicename` / nickname, never `user_login`; live
  normalize + reserved/dup check + `@handle` preview) → confirm profile → visibility
  (`internal` / `public`) → confirm "the handle cannot be changed after activation" →
  `register_handle()` + status. **No forced login-time landing** — activation is an
  explicit, opt-in act from `profile.php` / `users.php`.
- Site-actor type (`Application` / `Organization`); the `ax_actors_site_owner_user_id`
  setting.

**Phase 4b — avatar & header (two columns, not a table).** Add
`avatar_attachment_id` / `header_attachment_id` to `wp_ax_actors` (fixed 0..1 slots —
no `wp_ax_actor_media` table); a core Media-picker in the management screen; render
them in the public profile header; resolution per DATA-MODEL §8.1 (avatar falls back
to `get_avatar_url`, header to none). Validate attachment + image MIME + `edit_post`
on save; null the ref on `delete_attachment`.

**Phase 4c — Actor avatar → WordPress avatar.** Optionally mirror the Actor avatar
into WordPress via the `get_avatar_data` filter (default on for local Person) so
comments / admin show it; the header stays Actor-only.

**Phase 4d — multilingual profile (`wp_ax_actor_texts`). Shipped in DB v4.** `name` / `summary` /
`content` per language (AS `nameMap` / `summaryMap` / `contentMap`); `default_language`
defaults to the **site language** with the user's profile language offered as a
secondary tab (never auto-applied); resolution + BCP-47 normalization per §8.2.

**Acceptance:** publishing is explicit and per-actor; no bulk publish of existing
users; the handle-change prohibition and `@handle` ≠ author-URL are visible in the UI;
email never appears in any actor screen or link; avatar/header and translations are
Actor-owned while name/bio still read live from `WP_User`.
**Non-goals:** managers table; bulk tools; vCard export (a later small feature); any
federation table (DB v5+, DATA-MODEL §9).

## DB version roadmap & implementation order

The schema grows one version at a time; the version option is recorded only after the
new tables/columns/indexes are verified (DATA-MODEL §6, §9). Current = **DB v7**
(identity + actor + avatar/header + multilingual + address + instance + endpoint
ledgers). Next:

```
DB v5  wp_ax_actor_addresses (handle routing + history)        — shipped; WebFinger acct policy fail-closed (§9.9)
WebFinger endpoint  /.well-known/webfinger + local acct: rows  — shipped; subdirectory multisite explicitly OFF (tested)
Local NodeInfo      /.well-known/nodeinfo + NodeInfo 2.1        — shipped, NO table (WP options + live counts)
Remote discovery    safe acct → WebFinger → Actor snapshot     — shipped; synchronous primitive, no refresh scheduler
Remote admin        Users > Remote Actors lookup/cache inspector — shipped; manage_options + nonce
DB v6  wp_ax_instances (host software/version/policy ledger)   — shipped; per-host NodeInfo cache (NOT on actor rows); moderation is a separate layer (§9.10)
DB v7  wp_ax_actor_endpoints                                    — shipped; inbox/outbox/followers/following/featured/sharedInbox
DB v8  follower/discovery policy axes                           — next; NULL-aware lock/discoverable/indexable/collection visibility
DB v9  wp_ax_actor_keys + fetch_state + identity_relations      — keyring, remote cache, alsoKnownAs/movedTo
DB v10 wp_ax_actor_managers                                     — only when Group/Service/Org actors ship
```

Follow / Accept / Like / Announce / shared-folder membership start **after** this, in
a **separate Activity / social-relation store and Media Library** — not in Actors
(DATA-MODEL §9.7). Multisite `site-local` / `network-local` / `remote` stays a runtime
determination (§9.8).

## Phase 5 — Media integration (cross-plugin)

**Entry:** Phase 4 + Media Library Phase 5 readiness.
**Build:** Media Library registers `media` (and `folders`) projections; Collection
and Shared Folder owner/member keys are implemented on `actor_uri` (with
`local_user_id` as the nullable optimization pointer), using `ensure_for_user()`.
**Acceptance:** a collection/shared-folder membership survives a username change
(keyed on `actor_uri`, not the handle); an `internal` actor is a valid membership
key while its profile stays 404.
**Non-goals:** federation of memberships (Axismundi Federation, later).

## Out of scope for this plugin (separate packages)

Activity ledger, Like/Undo, actor activity page → **Axismundi Activities**.
Note/Question CPT + projection → **Axismundi Notes**. Local JSON-LD transformer,
inbox/outbox processing, HTTP signatures, background remote refresh/cache policy,
and delivery → **Axismundi Federation**. Actors owns only the bounded synchronous
discovery primitive needed to populate its own remote identity repository.
