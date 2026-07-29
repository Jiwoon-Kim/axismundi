# Axismundi Forum Architecture

> Status: **F0 shipped; F1 local-Topic baseline implemented.**
> Plugin: `axismundi-forum` (v0.2.0 — `ax_forum` + managed-Group binding,
> `ax_topic` Page projection, and contextual Forum entries).
> Review target: Axismundi boundaries, Lemmy interoperability, and familiar
> WordPress forum UX. This document is intentionally written before code so it can
> be independently reviewed.

## 1. Purpose

Axismundi Forum adds a federated community surface without creating a second
identity, activity, object, or reply system.

```text
Existing substrate
├─ Actors                 identity and profile projection
├─ Activities             activity ledger and social relations
├─ Object Projections     local/remote view models and rendering
└─ Note                   Note, Question, Quote, lifecycle, inReplyTo replies

Forum adds
├─ community / board context
├─ Group-Actor binding
├─ Page-based topics
├─ admission and moderation state
├─ Group distribution semantics
└─ Forum templates, blocks, and Forum → Topic → Reply UX
```

The interoperability target is a Lemmy-style community. bbPress is a reference
for hierarchy and actions; BuddyPress is a reference for Group context. Neither is
a data-model or rendering dependency.

## 2. Locked boundaries

### 2.1 Actors owns identity, not Forum state

A Forum is associated with one local ActivityStreams `Group` Actor but is not the
Actor row itself.

```text
Forum entity
  1:1 selected binding
Group Actor
```

An authorized user creates the managed Group through Axismundi Actors first.
Forum selects and binds that Group; it does not silently create, rename, publish,
or tombstone an Actor as a side effect of a Forum save or deletion.

Actors remains responsible for immutable URI/handle policy, Group owner/manager/
editor authority, Actor lifecycle, profile data, endpoints, and discovery policy.
Forum consumes Actors APIs and never writes Actor tables directly.

### 2.2 Visibility is not one enum

```text
Actor lifecycle:         internal | public | disabled | tombstone
Follow approval:         automatic | manually approve
Collection disclosure:   public | followers | private
Object audience:         public | unlisted | followers | mentioned | direct
```

A Group that approves new followers remains a publicly dereferenceable Actor.
Followers-only is an individual Topic/Reply audience decision, never an Actor
status. Forum reuses the existing Actor policy axes:
`manually_approves_followers`, `discoverable`, `indexable`, and
`follow_collections_visibility`.

### 2.3 Similar-looking relations are distinct

```text
Person follows Group      = subscription and federated delivery interest
Person is Forum member    = participation/admission state
Person moderates Forum    = Forum authority
Person is banned from Forum = scoped Forum restriction
```

Activities owns Follow and other delivery-facing social relations. Forum owns
membership, posting policy, moderation, and Forum-scoped restrictions. Actors owns
only the managed-Actor administrator kernel. Do not create one ambiguous generic
relation table for all four concepts.

`Follow` is necessary but not sufficient for Forum participation. It records the
federated subscription/request and its `pending` or `accepted` outcome; a Forum
membership projection records whether that outcome grants the Actor permission to
submit content in one Forum. This distinction is what lets a local WordPress Actor
join a remote Lemmy Group without inventing a local Forum, while a remote Lemmy
Actor can join one local managed Group and be admitted by that Forum's policy.

### 2.4 Forum stores context, never duplicate bodies

```text
Object projection = authored or cached remote object
Forum Entry       = one object's Forum membership, admission, moderation, display
```

This permits a remote object to remain visible on its author profile or in another
Group while being removed from one Forum.

### 2.5 Forum creates no Reply CPT

Replies reuse existing `Note` + `inReplyTo` behavior.

```text
Topic Page
└─ Reply Note
   └─ Reply Note
```

Forum contributes contextual admission and rendering only. It must not fork a
second comment storage or thread graph.

### 2.6 Actor-to-Community interoperability is the primary acceptance path

The first meaningful interoperability test is not a local Topic form. It is an
Actor joining a community and then publishing as that Actor.

```text
Local WordPress Person Actor -> remote Lemmy Group
  resolve/cache Group Actor
  -> Follow from the selected local Actor
  -> observe remote pending/Accept/Reject state
  -> create an outbound Topic bound to that remote Group (not a local Forum)
  -> Create(Page) attributedTo that Person, with Group context/audience and Group in cc

Remote Lemmy Person Actor -> local managed Group + Forum
  inbound Follow to Group
  -> Activities records the delivery relation
  -> Forum projects membership according to the Forum policy
  -> inbound Create(Page or Note) addressed/contextualized to Group
  -> Forum admits an Entry and Group Announce distributes it
```

The local Group's `ax_forum` binding is only a presentation and admission surface
for that local managed Group. A remote Lemmy Group remains an Actor owned by the
Actors registry; it must not be copied into a local `ax_forum` merely so a local
Actor can follow or post to it.

An outbound Topic stores a separate remote-Group binding on its local `ax_topic`
source. It is mutually exclusive with the local Forum Entry context: a local Forum
Topic has a local Group and Forum admission row; an outbound Topic has a cached
remote Group identity and no local `ax_forum` row. The remote binding is eligible
only while the author has an outbound Follow relation in `pending`, `accepted`, or
`legacy_pending` state. `rejected` and `undone` block delivery. Pending remains
eligible because remote community implementations do not consistently emit Accept
for an ordinary subscription; the outgoing Follow is the only portable evidence
available before a terminal response.

The emitted Page carries `context` and `audience` set to the remote Group URI,
`to: Public`, and `cc: [ remote Group URI ]`. The cc address is deliberately part
of the transport contract: the Bridge derives remote inbox delivery from Activity
audience, whereas `context` alone is semantic metadata and does not route a Create.

## 3. Planned domain model

### 3.1 Forum

The initial local entity is an `ax_forum` CPT, providing authoring, revisions,
media, and Site Editor integration. Its Forum-UI content lives on the CPT; its
binding to one eligible local managed Group Actor lives in a dedicated join table,
not in post meta.

```text
ax_forum CPT           title, slug, description, rules,
                       posting/membership/moderation policy, default sort

wp_ax_forum_bindings   forum_post_id      UNIQUE
                       group_identity_id  UNIQUE   (the bound managed Group)
                       created_at, updated_at
                       active-only — unbind = row delete
```

The binding is a join table, not `ax_forum` post meta, because the F0 invariant "a
Forum cannot bind an already-bound Group" is a **1:1-both-ways** constraint only a
table can enforce at the DB layer: `UNIQUE(forum_post_id)` and
`UNIQUE(group_identity_id)` make a second bind fail atomically, where post meta has
no uniqueness. Unbinding follows the managers-table principle — the row is deleted,
and deleting a Forum or its binding never tombstones the Group Actor (§6-F0).

Actor profile fields are not a duplicate copy of Forum fields. Synchronization can
be designed later once ownership of name, summary, icon, and header is explicit.

### 3.2 Topic

A Forum Topic is a titled ActivityStreams `Page`, with a Forum-owned `ax_topic`
CPT as its intended local authoring container.

```text
Page
├─ attributedTo      Person Actor
├─ audience/context  Group Actor
├─ name              required topic title
├─ content           topic body
├─ attachment        normal media relationships
└─ commentsEnabled   projected Forum lock policy
```

Topics must not be folded into editorial `post` or short-form `ax_note`, which
have different authoring and lifecycle contracts.

### 3.3 Forum Entry

`wp_ax_forum_entries` is a contextual projection. F1 implements the Topic subset;
reply lineage and remote-activity columns remain deliberately unused until their
own phases. Its semantic boundary is:

```text
forum_post_id
group_actor_identity_id
object_uri + object_uri_hash
entry_type                  topic now; reply in F2
source_post_id              local Topic source now
parent_entry_id / root_entry_id  F2 reply lineage
submission_actor_identity_id
admission_state             visible | pending | rejected | quarantined
moderation_state            visible | removed | spam
locked_at / sticky_position
accepted_activity_uri / announced_activity_uri

UNIQUE(forum_id, object_uri_hash)
```

### 3.4 Delete differs from moderator removal

```text
Author Delete       → source object lifecycle becomes Tombstone
Moderator Remove    → Forum Entry is hidden in this Forum; source can remain active
```

Moderator removal must never call the source object's delete path merely to hide it
from one community.

### 3.5 Forum Membership

Membership is a Forum-owned projection of a Follow relation directed at the
Forum's bound local Group. It is intentionally not stored in Activities and is
never inferred from a random follow of a different Group.

```text
wp_ax_forum_memberships
  forum_post_id
  actor_identity_id
  follow_activity_uri
  membership_state       pending | accepted | rejected | undone
  created_at, updated_at

  PRIMARY KEY (forum_post_id, actor_identity_id)
  KEY (actor_identity_id, membership_state)
  KEY (forum_post_id, membership_state)
```

The row is an auditable admission projection, not an alternate social graph:
Activities remains authoritative for the Follow, Accept, Reject, and Undo
Activities. A changed Follow relation updates the matching membership row. The
first policy values are `open` (Forum auto-accepts an inbound Follow) and
`approval` (a Group manager accepts or rejects it). Later moderation may add
`banned`, but a ban is not introduced merely to implement joining.

## 4. Federation model

The Group distributes accepted content; it does not become the author.

```text
Person Actor
  → Create(Page or Note)

Group Actor
  → validates Forum context
  → creates or updates Forum Entry
  → Announce(Create) to Group followers
```

`Page.attributedTo` and `Note.attributedTo` remain the Person Actor. The Group owns
community context, admission, and distribution.

Initial Lemmy compatibility gates:

```text
A  Group Actor document plus inbox/outbox/followers/featured endpoints
B  Follow Group and automatic Accept for public Forums
C  receive and accept Create(Page) into a Forum
D  receive and accept Create(Note) replies into a Forum thread
E  Group Announce accepted objects to followers
F  Update/Delete, featured Add/Remove, and moderation propagation
```

Every gate needs node-to-node verification; outbound JSON alone is insufficient.

### Membership is a Forum projection, not an alias for Follow

For a local Forum, the first participant table is expected to be keyed by Forum
and Actor identity. It owns Forum-specific state such as `requested`, `accepted`,
`rejected`, and later `banned`; it may retain the source Follow/Accept Activity
URIs for auditability. Activities remains the authority for the Follow relation
itself and delivery state.

For a remote Group, the outbound Follow relation is initially sufficient to model
the local Actor's join request. Forum must consume its `pending`/`accepted` result
when deciding whether the local composer may submit to that remote community, but
must not fabricate a local membership row for somebody else's Forum.

### Canonical Group URI follows the shared Actor registry

Lemmy commonly exposes communities at `/c/{slug}` so its Community and Person URL
spaces do not collide. Axismundi does not have that split: Person, Group, Service,
and Organization Actors share one registry and one globally unique local handle
namespace.

```text
Canonical federation identity, every local Actor type
  /actors/{uuid}

Human profile hub, every local Actor type
  /@{handle}/

Forum board presentation resource
  /forum/{forum-slug}/
```

The Forum board permalink is not a second Group Actor identity. It links to the
Group profile and is free to use a Forum-oriented information architecture, while
the Group's WebFinger, Move/alias, follower, inbox, and outbox behavior remains in
the shared Actor routing system. Forum must not introduce `/c/{slug}` as a local
canonical URI or a competing Group handle namespace.

Inbound Lemmy Group IDs using `/c/{slug}` remain ordinary remote canonical URIs;
interoperability does not require Axismundi to mirror that route locally.

## 5. Presentation ownership

Forum owns its templates and Forum-specific blocks. The theme styles them but does
not own CPT/template identity or query semantics.

```text
Forum archive  → Forum list
Single Forum   → Group header + topic list + New Topic action
Single Topic   → Topic Page + actions/meta + existing Note reply thread
Group profile  → Overview | Topics | Activity | Members | About | Moderation
```

`Topics` and `Activity` remain separate: the former is a titled Page discussion
index, while the latter is the broader Group Announce/activity stream. Group header
work must extend existing Actor/account-header blocks rather than fork profile,
avatar, cover, or follow controls.

## 6. Delivery phases

### F0 — local binding contract

Prerequisite in Actors (DB v11, pulled forward per DATA-MODEL §9.6.1):

```text
managed Group creation (actor_scope='managed', actor_type='Group', local_user_id NULL)
manager relation kernel:
  wp_ax_actor_managers(identity_id, user_id, role owner|manager|editor,
                       created_at, updated_at,
                       PRIMARY KEY (identity_id, user_id), KEY (user_id, role))
  active relations only — revoke = row delete, no granted_by/revoked_at
managed-Actor capability API: managed_actor_can_manage(), group_managers(), list_manageable_groups()
routing authority gates recognize the 'managed' scope (preview/manage)
```

F0 manager invariants (locked):

```text
every managed Group always has ≥1 owner
an owner may not remove or demote the last owner
owner-transfer UI is out of F0 scope
grant/revoke history is a later Forum moderation/admin audit event, not table columns
```

Forum scope:

```text
ax_forum CPT
select and validate a previously created managed Group Actor
one-to-one Forum ↔ Group binding
plugin-owned archive and single templates
no Topic, Reply, Follow, Announce, or federation behavior
```

F0 acceptance criteria:

- Group Actors can exist without a Forum.
- A Forum cannot bind a Person, remote Actor, or already-bound Group.
- Unbinding/deleting a Forum never silently tombstones the Group Actor.
- Forum checks owner/manager authority through Actors APIs, not copied post meta.

### F1 — local Topics and Forum Entry

```text
implemented: ax_topic CPT and public Page projection after admission
implemented: Forum Entry table, immutable one-Forum Topic context, Topic query,
             Forum Topic List block, and single Topic template
implemented: contextual cleanup (Topic delete removes its entry; Forum delete removes
             entries but not source Topics or the managed Group)
implemented: manager-owned local bootstrap policy (`open` for any Topic editor, or
             `managers` for a bound Group manager only), plus manager-only Topic
             pinning and reply locking; locked Topics project `commentsEnabled: false`
implemented: inbound remote membership projection (`pending|accepted|rejected|undone`)
             plus public, membership-aware remote Page admission. The bootstrap
             `open|managers` policy remains local-only.

pending: moderation state/UI, replies, Group Announce, and outbound remote Topic
         editor UI. The API-level outbound remote Topic composer and delivery
         contract are implemented; a user-facing remote-Group picker/composer is not.
```

### F2 — Actor-to-Community membership and admission

```text
Activities Follow/Accept/Reject remains the delivery relation source of truth
implemented first: Forum membership projection for remote Actors joining local
                   managed Groups; automatic/manual membership policy and manager
                   Accept/Reject decisions
implemented: membership-aware inbound Create(Page) admission into a local Forum
node-to-node acceptance: remote Lemmy Actor joins a WordPress Forum and submits a
                          Topic after membership is accepted
```

### F3 — outbound local Actor to remote Group

```text
implemented: select the local public Person author through the creation API
implemented: resolve/cache a remote Lemmy Group and Follow it from that Actor
implemented: bind an outbound Topic to the remote Group only while Follow is
             pending/accepted/legacy_pending
implemented: deliver Create(Page) with Person attributedTo, Group audience/context,
             and Group cc; the Bridge resolves the Group inbox from that cc address
pending: remote-Group picker and Topic composer UI, plus a real Lemmy node-to-node
         delivery test with HTTP signatures
```

### F4 — replies, distribution, moderation, featured, and voting

```text
existing Note + inReplyTo attached to Forum Entry
reply admission checks and topic/thread rendering
Group Announce accepted Page/Note objects to followers
pending/rejected/removed/spam/quarantine
moderation audit trail
commentsEnabled projection for locked Topics
featured collection Add/Remove
Like / Undo Like, then Dislike compatibility
```

## 7. Explicit non-goals for F0/F1

- multi-question forms;
- a WordPress comment or Reply CPT;
- private/invite-only Groups and non-standard remote membership workflows;
- ranking, reputation, or recommendation algorithms;
- copied Lemmy, bbPress, or BuddyPress storage/UI code;
- multisite/global Actor consolidation;
- automatic Forum-to-Actor profile synchronization.

## 8. Cross-review checklist

An independent reviewer should challenge these before F2 implementation begins:

1. Should Forum only select a pre-created Group, or may it request creation through
   an explicit Actors-owned action/modal?
2. Is initial one Forum per Group correct, or should a Group later expose several
   boards/categories?
3. Does the proposed Forum permalink remain clearly distinct from the Group Actor
   profile hub without becoming a competing identity URL?
4. Does `ax_topic` require an envelope table immediately, or can Object Projections
   safely provide a Page lifecycle boundary without one?
5. Does an accepted Follow grant membership automatically for each local Forum
   policy, or only create a request that a manager accepts?
6. Which relation remains Activities-owned versus Forum-owned: Follow, membership,
   moderator, ban, mute, and block?
7. How does the composer select a public local Person Actor, preserve that Actor as
   `attributedTo`, and prove that the target is a remote Group rather than a Person?
8. What minimum Group payload/endpoints and delivery result are required for the
   first bidirectional Lemmy test?
9. Do current Note visibility/thread gates stay fail-closed for Forum replies and
   remote Forum Entries?

## 9. Sources

- Current Axismundi Actors, Activities, Note, and Object Projection contracts.
- Lemmy and Lemmy UI, for interoperability validation rather than code copying.
- bbPress and BuddyPress, for information architecture and interaction conventions.
