# Axismundi Media Library — Federated & Shared Media (design)

> Status: **Forward design (Phase 5–7). Nothing here ships in v0.0.21.** This is the
> contract the shared-folder, collection, and federation work must satisfy. Existing
> phase docs fold in only the load-bearing invariants and cross-reference here.
> Contract-style, not prose — decisions, not narration.

## 1. Three kinds of media presence

Do not conflate these. The identity is always a **canonical object URI**; the local
WordPress attachment ID is an implementation pointer, never the cross-instance
identifier.

```
Local Attachment        a real local upload; this instance owns the binary + record
Remote Object           a canonical URI owned by another actor/instance (not ours)
Local Replica           a locally-cached binary/metadata of a Remote Object; its
                        identity stays the remote canonical URI, never a new one
```

`edit_binary`, `edit_origin_metadata`, `delete_origin` are **false** on a replica;
`refresh_replica`, `remove_from_container`, `insert_where_allowed` may be true.

## 2. Media Folder = one entity, two access modes

Personal and shared folders are the **same `MediaFolder` entity**, differing only by
membership. No separate class.

```
access_mode: private | shared        (shared = has members beyond the owner)
```

**Invariant (locked, precise):** every **local** Attachment belongs to **exactly one**
MediaFolder **on its owning instance's canonical record**. Shared folders are not an
exception. A replica on another instance is a *folder item*, **not** a second
*folder location* — so the one-folder rule holds at the canonical-owner level while
the same object is physically mirrored on member instances. `Unfiled` is a real
per-user system folder (`system_unfiled`) so `folder_id` is always NOT NULL.

Three subjects stay distinct (never merged): **Attachment owner** (owns the media) /
**Folder owner** (owns/manages the folder) / **Uploader** (who performed the upload).
Placing media in a shared folder never transfers Attachment ownership.

## 3. Folder vs Collection — a relation-type field

Same container storage may back both; one field separates them:

```
container item relation:
  location   → MediaFolder      (the item's actual home; owner-only movement)
  bookmark   → Collection       (a Save/pin; origin unaffected; many containers ok)
```

- **Folder**: owned Attachments' single location; OS-directory affordance.
- **Collection**: URI-first bookmark/curation; local + remote mixed; preview cache;
  removing an item never touches the origin. **Save = `Add` activity, not `Like`.**
  `Like` is a reaction (no target container, no reuse intent); `Add` places an object
  into a target collection with sort/note/`license_at_save`.

### 3.1 A folder listing contains its child folders

The OS-directory affordance above is the whole reason folders exist here, and a directory
that cannot show its subdirectories is not one. A federated listing therefore contains
**both** the folder's child folders and its direct media. Without this a remote peer can
reach a folder's URI and go nowhere: a parent holding a thousand items across eight
children reads as an empty folder, because the count is of *direct* members and nothing
points at the children.

```
folder listing = visible child folders + direct media members
totalItems     = the same, counted
```

Nothing recurses. A child appears as a **reference** — its identity and how to open it —
never with its own items inlined, so one listing is one folder and depth costs one request
per level, as a drive does.

**Children are gated exactly like roots.** A child is listed only if it would federate on
its own (`axismundi_media_folder_federation_allowed()`): public or unlisted, ungated,
attributed to a public Actor. An `internal`, private, or password-gated child is absent
from the listing and absent from `totalItems` — a name is a disclosure, and a hidden folder
whose existence is advertised is not hidden. This reuses the visibility cascade rather than
inventing a second gate, so a parent turning private removes its children from listings in
the same pass that withdraws their identities (DATA-MODEL §3.0).

Ordering is **children first, then media** — the drive convention, and forced anyway:
folder order is `_ax_media_folder_added_at`, which a child folder does not have. See the
Object Projections representation contract for the serialized shape.

## 4. Membership & roles (actor-URI keyed)

Membership is keyed on **actor URI** (local user ID is an optimization pointer), so
remote actors extend the same model.

```
folder_id · principal_type(local_user|remote_actor) · local_user_id? · actor_uri
· role · status(pending|active|left|removed) · invited_by · accepted_at
```

Roles (not the vague owner/manager/guest):

```
owner        delete folder, transfer, manage members/roles, all settings
manager      members view, some settings, upload/move/remove items, sensitivity
             review — NOT delete/transfer
contributor  view, upload own, edit/remove OWN items, reference others
viewer       view, open original, Save/insert where rights allow — no write
```

Per-folder settings separate **visibility** from **member powers**
(`public` visibility with `member_upload=true, viewer_upload=false` must be possible):

```
visibility: private | members | unlisted | protected | public
allow_member_uploads · allow_member_add_references · allow_member_invites
```

**Membership activities:** `Invite`(→`Accept`/`Reject`)→active; `Leave` / remove →
ended. Roles need an extension attribute (`axm:role`) — AS2 alone can't express them.
**Phase-1 shortcut:** seed already-accepted memberships via a dev-only WP-CLI/PHP
seed (no Invite/Accept UI) so the membership + capability model is validated without
the federation protocol.

## 5. Leave / remove / delete → return, never destroy

```
member leaves / is removed / membership expires:
  their OWNED attachments in that folder → their own system Unfiled (folder_id)
  saved references → removed or moved to personal collection
  audit log entry written
folder deleted:
  every item → each owner's Unfiled   (delete folder ≠ delete attachments)
remove-from-shared-folder:
  → owner's Unfiled   (distinct from "delete")
```

Owner ID / object URI / file URL / permalink / rights / used-in are all preserved
through any of these. Forced removal may offer the admin: return-to-unfiled (default)
/ transfer-to-owner-first / cancel — never silently seize another's files.

## 6. Sensitive = state + authority (data shape locked now)

`_ax_media_sensitive` boolean is retained as a **read-only effective/compat value**
that serializers and UI consume; it is **computed** from an authority record that
decides who may change it:

```
_ax_media_sensitive_state:  none | self_marked | automated_flagged
                            | moderator_marked | confirmed
_ax_media_sensitive_set_by · _ax_media_sensitive_set_at · _ax_media_sensitive_reason
_ax_media_sensitive_locked  (derived: moderator_marked/confirmed ⇒ locked)
```

Rules: `self_marked` — user may clear. `automated_flagged` — user may appeal, **not**
self-clear (→ pending moderation). `moderator_marked`/`confirmed` — user cannot clear.
Capabilities: `mark_own_media_sensitive` · `moderate_media_sensitivity` ·
`override_media_sensitivity`. Feed/serializer read the effective value only.

### 6.1 A remote declaration is an observation, not an authority state

**The origin's `sensitive` value never enters the state enum above.** That enum answers
*who may clear this*, and every one of its values names a decision **we** made. "The origin
said so" is not our decision — it is an observed fact about someone else's object, and
folding it in would make `set_by` a lie and break the clearing rules.

It already has a home: `wp_ax_remote_objects.is_sensitive` is **nullable on purpose** —
`true` / `false` / `NULL` = the origin did not report one. Never conflate `NULL` with
`false`, the same tri-state rule Actors applies to remote policy flags.

```
remote_declared   observed  wp_ax_remote_objects.is_sensitive   true | false | NULL
local_authority   decided   the state machine above
effective         derived   remote_declared OR local_authority
```

- A remote `true` is **never clearable locally**. Un-flagging another instance's content is
  not ours to do; a viewer who disagrees is asking the origin, not us.
- A remote `false` or `NULL` **may be overridden to sensitive locally**, through the normal
  authority record. The override records *our* decision and does not claim to be the
  origin's.
- Absence of a remote object row means no declaration, not a `false` one.

### 6.2 Sensitivity is scoped

One flag per object is not enough: a viewer may want to hide something only for themselves,
and a moderator may want to blanket a whole container without touching each item. So an
authority record carries a **scope**, and `effective` is the OR across every scope that
applies to the current viewer and surface:

```
site        everyone on this instance
user        one viewer's personal hide; never visible to others
folder      blanket within one folder, local or remote
collection  blanket within one collection view
```

**Scopes narrow only.** A scope may add sensitivity; none may remove it. This is the same
narrow-only rule folder visibility already follows (DATA-MODEL §3.1), and it is what keeps
`effective` computable without ordering the scopes against each other.

Storage is an annotation keyed by object + scope + scope ref, **never a column on the
object cache** — an override is our moderation state, and mixing it into a cached remote
payload would corrupt the record of what the origin actually sent.

### 6.3 Blur is not privacy for remote media

The local click-to-reveal overlay (0.0.21) blurs a file we already own and serve; the only
thing at stake is what the eye sees. For **remote** media the same overlay is a lie: an
`<img src>` at the origin has already downloaded the image and already told the origin
server the viewer's IP, referrer, and that this person is looking at this item.

So for anything whose `effective` sensitivity is true and whose bytes are remote:

```
before reveal   no request to the origin at all — local placeholder only
on reveal       fetch the remote preview, or a local proxy under privacy mode
```

Withholding the request, not blurring the result, is the control.

## 7. Used-in ⊋ federated `attachment` projection

`Used in` is the **reverse index of the ActivityStreams `attachment` / `image` /
`icon` (and `schema:associatedMedia`) relations** — NOT a `post_parent` replacement,
NOT "file URL appears in HTML". But the **internal Media Relation index is a superset
of** the federated projection: it keeps local, decorative, and non-federated uses the
serializer filters out.

```
subject object --predicate--> media object URI      (predicate: as:attachment|as:image|as:icon|…)
internal index      = all real references (local + private + decorative)
federated attachment output = filtered by public / rights / policy
```

Editing authors relations from WordPress blocks/meta via
`Attachment_Relation_Provider`s (block content, featured image, gallery/cover/file/
audio/video, post meta, CPTs). **JSON-LD serialization and the used-in index are
produced from the same normalized object model** (don't re-parse JSON-LD into DB).
Remote relations are upserted from received AS objects' `attachment` (verified — the
remote object must actually declare it, not merely embed a file URL); `Update` resyncs,
`Delete`/`Tombstone` deactivates. Entry keys prefer the media **object URI**; a
file-URL-only reference is stored at lower `identity_quality` and aliased later.

Attachment page shows three **distinct** relation groups: **Location** (current
folder) / **Used in** (attachment/image/icon reverse index) / **Saved in** (others'
collections/shared folders).

## 8. S2S shared folder = per-instance projection + replica

Each member instance holds a **local projection of the folder + a binary replica** of
items it did not originate. To the user it reads as one folder where a file "moved";
across servers it is `Add object to shared folder` + replication.

```
UI meaning        Move (leaves my Unfiled → shared folder)
federation meaning Add object to shared folder (target = folder URI)
storage meaning    remote replication / local mirror (NOT a new object)
```

Prefer **`replica`/`local mirror`** over "cache" for shared-folder items (kept until
membership ends), reserving "cache" for transient Follow/timeline previews. One remote
object URI → one local binary → many folder-item relations (dedup).

## 9. Remote Attachment Replica (shadow attachment) — gated

A `post_type=attachment, post_status=ax_remote_replica` shadow is created **only** for
S2S shared-folder replicas, **never** for Follow/timeline media. All must hold:

```
verified Add  ∧  target is a shared folder  ∧  actor is an active member
∧  actor role allows upload  ∧  MIME/size/rights/sensitivity pass
∧  replica policy allows shadow attachment
```

Meta: `_ax_media_origin=remote` · `_ax_remote_object_uri` · `_ax_remote_actor_uri` ·
`_ax_remote_folder_uri` · `_ax_replica_binary_path` · `_ax_replica_level` ·
`_ax_replica_read_only=1` · `_ax_canonical_owner_uri`. Excluded from default
attachment/`wp.media` queries; shown only in the shared-folder context. Serialization
and used-in use the **canonical remote URI**, never the local shadow ID. Local caption/
note/alt live in a **projection override**, never overwriting origin metadata.

## 10. Reception paths & lifetimes (do not blur)

| Context | DB record | Binary | Kept until |
|---|---|---|---|
| Follow / timeline | remote-media row | transient preview | cache policy |
| Save / Collection | collection item + remote row | preview/display | Save removed |
| Shared folder | folder item + (gated) replica attachment | replica | membership ends |
| Import / Copy | **new local Attachment** | local original | user deletes |

`Remove from folder ≠ Delete object`. `Import/Copy` is the only path that mints a new
local object URI and requires an explicit rights check.

## 11. Cache/replica storage & levels

Levels: `metadata-only → preview → display → original` (default for remote shared
items = metadata + preview; `original` only with explicit rights). Store under an
explicit path (`/uploads/axismundi-media-cache/{host-hash}/{object-hash}/`), serve via
a plugin-controlled resolver URL (`/cache/media/{cache-id}`) so cache URLs are not
leaked as immutable originals. Never mix replicas into the normal uploads tree as if
owned. **Cache ≠ Import**: a replica is never redistributed as an owned file.

## 12. WP↔WP compatibility contract

Not standard-AP interop — a **plugin extension protocol over ActivityPub**. Full
interop only between instances running this plugin at a compatible protocol version.

- **Canonical identity**: object URI + folder URI + actor URI are the cross-instance
  keys; local attachment IDs differ per instance and are never compared. Keep these
  URIs immutable; separate identity URI from the mutable display permalink.
- **Extension context**: standard AS attributes as-is; plugin data under a versioned
  namespace (`axm:` / `https://…/ns/media`) — `containerKind`, `replicaPolicy`,
  `memberPolicy`, `role`, `protocolVersion`, `capabilities`.
- **Activities handled**: `Create/Add`, `Update`, `Remove`, `Delete/Tombstone`,
  `Invite/Accept/Reject`, `Leave` — with the three-way distinction of §5 enforced.
- **Discovery**: capability set (e.g. `/.well-known/axismundi-media` or an actor
  extension) → negotiate; degrade gracefully:

```
Level 1  WP + this plugin, compatible version → full (folders, membership, replica, sensitivity lock)
Level 2  generic ActivityPub server → standard media objects + Collection/Add/Like only
Level 3  other platforms → adapter / FEP proposal
```

- **Trap**: domain/URI changes break relations — treat canonical URIs as durable, and
  never trust a received `attachment` unless the remote object actually declares it.

## 13. Phase mapping

```
Phase 3  Used-in Media Relation index (superset model; local first)
Phase 5  MediaContainer (folders + collections) + membership; dev-seed shared folders
Phase 6  Storage Browser (local replica files are a distinct, read-only projection)
Phase 7  Federation: replica, shadow attachment, Invite/Accept, capability discovery
```
