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

## 범위 밖 — 논의 기록 (2026-11-02)

**이 절은 구현 범위가 아니다.** 선행 작업이 끝나기 전에는 결정으로 집행하지 않는다.
§3 이 이미 답한 것(폴더=단일 location, 컬렉션=다중 bookmark, `Save = Add`, `license_at_save`,
preview cache)은 다시 정하지 않는다. 아래는 그 위에 새로 드러난 것만 적는다.

### 1. Actor 됨은 라이브러리가 아니라 보드에 있다

모델을 "Openverse + Pinterest" 로 읽으면 자리가 정해진다 -- Openverse 는 호스팅이 아니라 색인이고
라이선스·창작자 귀속이 1급 데이터이며, Pinterest 는 **보드가 큐레이션 단위이고 팔로우 대상**이다.

Actor 인지 Collection 인지는 세 질문으로 가른다. 레미 커뮤니티가 Group-프록시로 성립한 이유가
셋 다 예였기 때문이다.

```txt
팔로워가 있는가 / inbox 로 받는가 / 말하는가(Announce)
  보드 · 공유폴더       예      → Group actor 가 될 만한 것
  미디어라이브러리      아니오   → 인스턴스의 색인.  Collection
```

셋 다 아니면 Actor 됨이 사는 것 없이 inbox·outbox·followers·키·핸들·WebFinger·상태 수명주기·
tombstone 을 다 떠안는다. 라이브러리의 `attributedTo` 가 site scope Application actor 를 가리키는
것은 그대로 맞다(`actor_scope = 'site'` 가 스키마에 있다. 기본 생성 여부는 미확인).

> 미디어라이브러리 플러그인은 현재 **어떤 actor 도 등록하지 않는다**(2026-11-02 실측).

### 2. 보드는 저자 불일치를 허용하는 첫 curated collection 이다

Pinterest 의 저장은 **남의 항목을 내 보드에 담는 것**이다. §3 의 `Save = Add` 가 그 말이고,
Person `featured` 와 갈리는 지점이 여기다.

```txt
featured   member_authorship: 'self-only'   자기 객체만 (ax_featured_author 게이트)
보드        member_authorship: 'any'         남의 객체를 담는 게 본질
```

`member_authorship` 공통 계약 필드를 미룬 것은 옳았다 -- **그것을 실제로 필요로 하는 두 번째
사용자가 여기서 처음 보인다.** featured 하나뿐일 때 올렸으면 미래를 예상한 확장이었을 것이다.
→ `actors/docs/MODERATORS-PRECEDENTS.md`, `actors/docs/CURATED-COLLECTION-PRECEDENTS.md`

### 3. Channel 프로필의 attributedTo 는 타입 배열이다

레미의 호환 코드에서 읽은 것이다(PeerTube 를 직접 읽은 것이 아니다).

```rust
pub enum AttributedTo {
  Lemmy(PersonOrGroupModerators),      // moderators 컬렉션 URL 하나
  Peertube(Vec<AttributedToPeertube>), // {kind: Person|Group, id} 배열
}
```

PeerTube 는 `attributedTo` 에 계정(Person)과 채널(Group)을 **함께** 싣는다. 그러므로
"배열로 둘 다 표현" 이라는 초기 직관은 **Channel 프로필에서는 맞다** -- Community 기준으로
"어느 생태계의 형태도 아니다" 라고 적었던 것은 Community 한정이다.
→ `actors/docs/DECISION-GROUP-FEDERATION-PROFILE.md`

P2P(WebTorrent)는 **페이로드 전달**이라 AP 모델을 바꾸지 않는다. 다만 PeerTube 객체는 `url` 에
magnet 과 HTTP 를 배열로 싣는다 -- Misskey 의 `embedded url = scalar` 함정과 같은 부류의
인접 위험이다.

### 4. 핫링크가 기본, "later increment" 는 전량 캐싱이 아니다

현재 상태는 의도된 핫링크다.

```txt
object-blocks.php:934      "Remote media is hot-linked on purpose: binary caching is a later increment."
remote-collections.php:7   "no shadow attachment or binary cache exists"
admin.php:376              객체 프로브는 메타데이터만 -- 핫링크도 다운로드도 안 한다
```

**오너 판단: 전량 push + 바이너리 캐싱은 지속 가능하지 않다.** 그러므로 저 주석의 "later
increment" 를 전량 캐싱으로 구현하면 안 된다. 적어두지 않으면 주석만 보고 그렇게 구현된다.

릴레이 비유는 프로토콜 용어로 정확하다 -- AP 릴레이는 activity 를 전달하지 페이로드를 전달하지
않고 미디어를 캐시하지도 않는다. 색인은 **참조를 중계하고 바이트를 옮기지 않는다.**

순수 핫링크의 비용은 대역폭이 아니다.

```txt
프라이버시   뷰어 요청이 원본으로 가므로 원본이 "누가 무엇을 보는지" 를 알게 된다
가용성       원본이 죽으면 보드가 깨진 이미지로 찬다.  큐레이션 제품에서는 타임라인보다 치명적
가변성       원본이 그 URL 의 바이트를 바꿀 수 있다.  무결성 결속이 없다
차단         referrer 핫링크 차단은 조용히 실패한다
```

Openverse 자신의 답이 중간이다 -- **원본 자산은 핫링크, 썸네일만 캐시.** 저장은 항목 수에
비례하고 바이트 크기에 비례하지 않으며, 전체 저작물의 호스트가 되지 않고, 원본이 죽어도 보드는
읽히고, **대부분의 조회가 일어나는 지점(보드 둘러보기)에서 원본에 전화하지 않는다.** 마지막이
가장 강한 논거이고 저장 용량 이야기가 아니다.

우리에게 렌디션 어휘는 있으나(virtual 렌디션 수용) 그건 우리 미디어 기준이라 **원격 썸네일
계층은 신규**다. 재사용은 어휘 수준이지 구현 수준이 아니다.

### 선행 작업 (이것들 전에는 범위 밖)

```txt
라이선스 메타데이터   미디어라이브러리가 제대로 다룰 수 있어야 한다
                      색인이 제품이면 media-rights creator 해석이 뒤에 있을 자리가 아니다
FEP 선행              해당 FEP 조사·채택 결정이 먼저
타입별 캐시 정책       Note 의 attachment 는 바이너리 캐시 안 함
                      image / video 만 썸네일 캐시
라이선스별 연합 제한   라이선스가 있으면 연합을 제한하는 정책이 필요하다
미결                  썸네일이 파생물이면 캐시 계층이 **항목의 라이선스**를 따라야 하는가
                      (전역 설정이 아니라)
```
