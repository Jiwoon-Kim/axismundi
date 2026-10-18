# ActivityStreams Collection Model

**Status:** working decision, 2026-10-18. This records the contract descriptor
axes for every collection this project publishes, which pins cite a
specification and which record a project choice, and the measurements the
decisions rest on. It is the reference for the collection contract registry; a
task brief should point here rather than restate it.

A collection's hard question is not its JSON shape. It is **who decides
membership, where that decision is stored, and whether a count of it exists.**
Those are three independent facts, and conflating them has already produced a
published document that asserts a membership nobody read.

## 1. The axes

```txt
member_models         Activity[] | Object[] | mixed
member_types          optional set of AS2 types every member must satisfy
collection_type       Collection | OrderedCollection (root; page type follows)
presentation          metadata-only | count-only | bounded-members | paged-members
ordering              none | immutable-cursor(feed_sort_at,id) | source-defined
membership_source     side-effect:<activity> | explicit:Add/Remove
membership_authority  ledger-index | relation-row | membership-row
append_policy         owner-only | publicly-appendable | closed
total_capability      exact | unavailable
total_reason          required when unavailable; see below
total_disclosure      a map per requester class, never one value
```

`member_models` has no `Actor[]`. In ActivityStreams, `Person`,
`Organization`, `Service`, `Group` and `Application` are subtypes of `Object`,
so a separate actor member model duplicates the type system inside the
membership axis. Actor-ness is expressed by `member_types`, which exists
because it is checkable: `axismundi_actors_kinds()` already returns exactly
those five.

`total_disclosure` must be a map. One value cannot express `followers`, which
counts exactly, is withheld from the public by policy, and is always visible to
its owner. Collapsing it makes a correct private response look like a defect.

```txt
total_disclosure:
  public: never | policy-gated | always
  owner:  never | policy-gated | always
```

`total_reason` records why a count is unavailable, because every current case
is a consequence of a design choice rather than a property of the collection:

| reason | meaning | case |
|---|---|---|
| `read-time-filter` | a filter decides membership after the read | `ap-outbox` |
| `bounded-scan` | the reader does not see the whole set | `replies` |
| `membership-not-stored` | no index answers the membership question | `ap-inbox` |
| `member-level-visibility` | members carry their own visibility, so an exact count may disagree with the rendered page | explicit collections |

`total_capability` is not derivable from `presentation`, and `member_models`
does not predict it. `likes` and `shares` hold Activities and count exactly,
because `axismundi_act_get_vote_count()` is a `COUNT(DISTINCT actor_uri_hash)`
over an indexed ledger predicate. Do not encode "Activity means no total".

## 2. Where membership lives

Three homes, and the axis is shape and lifetime rather than volume.

| `membership_authority` | Use it when | Cases |
|---|---|---|
| `ledger-index` | membership is itself an Activity the ledger already stores and indexes | outbox, likes, shares, liked |
| `relation-row` | a standing relation between identities with a state machine | followers, following, blocks |
| `membership-row` | a curated assignment whose current state is not replayable from its evidence | moderators, and the future actor media collection |

Curated membership rows live in **one Activities-owned table**, not in each
product. ActivityPub gives `Add` and `Remove` identical side effects in the
outbox and the inbox, so a single store makes C2S and S2S converge by
construction instead of by two handlers kept in sync, and it creates the path
that does not exist today: a remote Actor adding to a collection of ours.
Products write by submitting the Activity and read through the registry's
reader binding; they do not keep their own authority.

This also closes a boundary leak. `wp_ax_forum_memberships` currently carries
two different kinds of fact: `membership_state` is a **projection** of the
Follow and Accept relation, derived by
`axismundi_forum_sync_membership_from_follow()`, while `membership_role` is the
**authority** for the moderator role. Only the role moves. Group membership
stays relation-derived and Forum keeps projecting it, which leaves Forum
holding no authority of its own — and that split is why 341 Add and Remove
evidence rows could coexist with zero moderator rows, since nothing enforced
the pair across a plugin boundary.

The table keys one row per pair, as the relations table does, so a re-`Add`
after a `Remove` returns the existing row rather than duplicating it. Each row
records both the proposal and the grant, because a curated collection may be
appended to by someone other than its owner: who proposed it, the owner's `Add`
that granted membership, and the `Remove` that revoked it. Membership origin —
the owner's own addition, something shared to them, or a third party's
contribution — is a stored column rather than an inference.

The first product built on that table is the Actor-owned media collection, and
its scope is decided:

```txt
member_types:  [Document, Collection]   subtypes included
member_id:     a stable absolute URI is required; a dereference that fails,
               because a remote server is briefly unavailable, never removes
               existing membership
totalItems:    direct members only
```

`[Document]` alone covers the whole Media Library, because the attachment
mapping recorded in Object Projections' `CORE-WP-OBJECT-SOURCES.md` sends
`image/*` to `Image`, `video/*` to `Video`, `audio/*` to `Audio` and every other
MIME type to `Document` -- all of which are `Document` or its subtypes in the
vocabulary snapshot. So MIME decides the member's type and that path never
produces an AS2 `Page`, which is why `Page` needs neither including nor
excluding here; it remains undecided only for WordPress `post_type=page`.

`Collection` is in the list purely for nesting, so a Document collection can
hold an image-only sub-collection built the same way. `OrderedCollection` comes
along as a subtype.

No exclusion list is recorded. Excluding `CollectionPage` was considered and
dropped: ActivityStreams does not forbid a page as a member, nothing this
project emits produces one, and the per-descriptor authorization callback can
refuse an inbound oddity -- so the rule would be maintenance without a measured
need. Using pages purely as a traversal unit is a product habit here, not a
specification requirement.

Deferred rather than decided: whether cycles are permitted at all and what
limits a recursive read takes, with ActivityPub B.5 on recursive objects and
7.1's MUST on limiting layers of indirection as the starting references; and
how a member URI links to an internal identity, which belongs to the unified
Object registry contract rather than being settled by nesting alone. Counting
direct members only follows from `COUNT(*)` over direct rows and is also what
keeps a parent's total stable while its children change.

Two seams are required and must not be hardcoded. The first exception in 7.8,
that the target is not owned by this server, is answered by asking the registry
whether this is a collection we own and what its policy is. The second, "at the
receiving server's discretion", is a per-descriptor authorization callback,
because the rules are product-specific: Forum's invariants that only an
accepted member may hold the moderator role and that a managed-Group manager is
always a moderator belong to Forum, not to the table. Activities already calls
Object Projections through `function_exists()` guards in several places, so
reading the registry on the write path follows the established dependency
pattern rather than introducing a new one.

Membership that is itself an Activity must **not** be copied into a second
table. Doing so adds a consistency obligation and buys nothing, because the
ledger is already indexed on both `actor_uri_hash` and `object_uri_hash`.

`followers` and `following` are ledger-*derived* but materialized, because
`Follow` then `Accept` is a two-Activity state machine that is not viable to
re-derive per read. `initiating_activity_uri` and `state_activity_uri` are the
join back to the ledger, so the ledger remains the basis and the relation row
is the materialized state.

**A measured counterexample fixes the `membership-row` case.** The ledger holds
209 `Add` and 103 `Remove` rows targeting Forum moderator collections, while
`wp_ax_forum_memberships` holds 122 accepted members and **zero** rows with
`membership_role = 'moderator'`. The current set is therefore not derivable
from the Add and Remove activities, and a descriptor bound to them would
publish 209. `membership_source` and `membership_authority` are independent
axes, and this is the proof.

`member_models` and the authority are also independent. `liked` is an Object
collection served from the ledger: ActivityPub 5.5 defines it as "a list of
every object from all of the actor's Like activities", so the members are
Objects while the membership rule is a query over Like Activities. The same
rows read from the other end are `likes`:

| | owner | members | key | question answered |
|---|---|---|---|---|
| `likes` | an Object | Activity | `object_uri_hash` | who liked this |
| `liked` | an Actor | Object | `actor_uri_hash` | what did this Actor like |

The member type follows the question the collection answers, not whatever
produced the membership.

## 3. Implicit and explicit collections

An **implicit** collection's identity is a function of its owner's URI and a
role. It exists because the owner exists and needs no row. Every ActivityPub
section 5 collection is implicit, as are `replies`, `usedIn` and
`emojiReactions`.

Identity and membership are independent axes. `ap-moderators` is **implicit in
identity** — its URI is derived from its Group, so it has no identity row — and
**curated in membership**, written by `Add` and `Remove`. So "implicit" does
not imply a side-effect membership source, and the curated membership table
cannot assume every collection it serves has an identity row.

An **explicit** collection is a created object with its own identity, owner and
lifetime: a curated list, an actor's media collection, or a remote collection
this site is a member of. It needs an identity row, and `wp_ax_identities`
already admits one — it carries `object_kind` and `origin`, with the
actor-specific facts in a separate joined table. The structure is the taxonomy
model one level up:

| WordPress taxonomy | Axismundi |
|---|---|
| `wp_terms` — a name exists | `wp_ax_identities` — a URI names something we own |
| `wp_term_taxonomy` — kind, parent, **count** | `wp_ax_actors` — the actor-specific half |
| `wp_term_relationships` — membership | per-feature membership |

Take the three-way split and **not** the counter cache.
`wp_term_taxonomy.count` is WordPress's own precedent for a stored total and a
well-known drift source needing `wp_update_term_count_now()`. A `COUNT(*)` over
an indexed membership relation cannot drift, and at this project's scale it is
free. Publishing a number nobody read is the defect this whole model exists to
prevent.

A folder is **not** a collection. A folder belongs to a WordPress user and
organizes local uploads; the Media Library must keep working with no Actors and
no Object Projections. An actor media collection is an Actor-owned Object
collection holding local attachments, remote media Objects, shared items and
third-party contributions by URI, so it depends on Actors and Object
Projections. The folder implementation contributes only precedent: UUID
identity separated from the mutable display permalink, registration through the
Actors registry rather than SQL, local-first degradation, and a fail-closed
federation predicate that is anonymous and cache-safe so the answer never
varies by viewer. Existing folder identities and routes are **not** promoted to
collections.

Because members are URIs rather than local post IDs, WordPress taxonomy cannot
represent an explicit collection at all: `wp_term_relationships` keys on
`object_id`, and a remote media Object has none.

## 4. Writing: Add and Remove

ActivityPub gives `Add` and `Remove` the same side effect in the outbox (6.6,
6.7) and the inbox (7.8, 7.9), so one handler serves both and the gate is the
`target` ownership check. The second listed exception, "at the receiving
server's discretion", is where an append policy legitimately lives.

Add and Remove apply **only** where membership has no other source. For every
implicit collection the source is a side effect — `Follow` plus `Accept` for
followers and following, `Like` for liked and likes, `Announce` for shares,
publication for outbox and inbox, `inReplyTo` for replies — so none of them
accepts an Add or a Remove. An inbound `Add` targeting this site's `followers`
URI is refused under the discretion clause, not under the ownership clause: the
target *is* ours, and the refusal is because followers membership belongs to
the Follow and Accept machine. An Add that bypassed it would produce a follower
with no Follow, no Accept and no evidence row. The refusal is declared per
descriptor so a collection added later cannot inherit a permissive default.

Companion rule: **`Undo` reverses a side-effect collection, `Remove` reverses a
curated one.** A follower leaving sends `Undo{Follow}`, never `Remove`.

## 5. Descriptors

| descriptor | members | source | authority | total | reason |
|---|---|---|---|---|---|
| `ap-outbox` | Activity[] | side-effect:publication | ledger-index | unavailable | `read-time-filter` |
| `ap-inbox` | Activity[] | side-effect:receipt | — | unavailable | `membership-not-stored` |
| `ap-followers` | Object[], actor kinds | side-effect:Follow+Accept | relation-row | exact | — |
| `ap-following` | Object[], **no type constraint** | side-effect:Follow+Accept | relation-row | exact | — |
| `ap-liked` | Object[] | side-effect:Like | ledger-index | see below | — |
| `ap-likes` | Activity[] | side-effect:Like | ledger-index | exact | — |
| `ap-shares` | Activity[] | side-effect:Announce | ledger-index | exact | — |
| `replies` | Object[] | side-effect:inReplyTo | ledger-index | unavailable | `bounded-scan` |
| `usedIn` | Object[] | stored relation | membership-row | exact | — |
| `ap-moderators` | Object[], actor kinds | **explicit:Add/Remove** | **membership-row** | exact | — |

`ap-outbox` has an indexed predicate — `actor_uri_hash`, `direction` and
`effective_status`, covered by `feed_public_cursor`. What blocks the count is
`axismundi_act_public_outbox_payload` deciding membership after the read, so
`unavailable` here is a design consequence and not a property of the
collection. Record the reason, never the bare verdict.

`ap-inbox` has no recipient index at all. `inbox_targets()` resolves the
recipient set at write time and `record_inbox()` discards it after an
`empty()` check, and every ledger index is keyed on the **sending** Actor.

`ap-following` takes no `member_types`. A `Follow`'s `object` has a range of
`Object` or `Link`, and the Notes say the actor "is interested in any activity
performed by or on the object", so a non-actor target is contemplated and a
Collection may be followed. Pin the Accept requirement instead of a type: a
target that could send an `Accept` necessarily had an inbox and a key, so
actor-ness is a consequence rather than a declaration, and a followed
Collection separates out as not-accepted rather than being refused as a type
violation. `ap-followers` may carry the constraint, because its members are
those who sent the Follow and an Activity's `actor` is an actor by the activity
model.

`ap-liked` must declare `unavailable` until a cursor reader exists. Its only
reader, `axismundi_act_get_liked_object_uris()`, carries both defects already
removed from the Outbox, so it must not be bound as-is. See section 7.

`ap-moderators` counts its membership rows — **never the ledger's Add and
Remove activities**, which are evidence rather than current state. Until the
curated collection table exists it reads `wp_ax_forum_memberships` with
`group_identity_id`, `membership_state = 'accepted'` and
`membership_role = 'moderator'`; afterwards the role lives in the
Activities-owned table and Forum keeps only its relation-derived
`membership_state` projection. Forum's invariants move into the descriptor's
authorization callback rather than being reimplemented: only an accepted member
may hold the role, a managed-Group manager is always a moderator and cannot be
demoted through this path, and each change writes the membership row and its Add
or Remove evidence together under a stable `source_event_key`.

ActivityPub 5.6 Public Addressing is **not** a collection and gets no
descriptor. It is a shared predicate several descriptors depend on.

## 6. Specification versus project choice

Every pin must say which it is. Borrowing the wrong authority has already
happened once in a task brief, citing 5.3 for a rule 5.3 does not contain.

| Rule | Which | Where |
|---|---|---|
| An OrderedCollection MUST be presented consistently in reverse chronological order | spec MUST | 5 intro; its Note is why `feed_sort_at` is immutable |
| outbox and inbox MUST be an OrderedCollection | spec MUST | 5.1, 5.2 |
| followers, following, liked, likes, shares MUST be an OrderedCollection or a Collection, and MAY be filtered on privileges | spec MUST / MAY | 5.3-5.5, 5.7, 5.8 |
| The inbox MUST de-duplicate by `id` | spec MUST | 5.2; satisfied at write by `UNIQUE activity_uri_hash`, not by a read filter |
| `following` membership is Accept-conditioned; Reject MUST NOT add | **spec** | 6.5, 7.6, 7.7 |
| `followers` accepted-only | **ours** | ActivityPub states no rule for adding to followers at all |
| Members are Activities for outbox, inbox, likes, shares; Objects for liked | spec prose | 5.1-5.2, 5.5, 5.7-5.8; there are **no examples** in any of these sections |
| followers and following hold actor-typed Objects | **ours** | implied by 5.3 and 5.4, never stated |
| Publishing `likes` at all | **ours** | 7.10 adds the activity "if this collection is present" |
| Anything about `totalItems` | **ours** | section 5 says nothing about it, so every `total_*` axis is this project's contract and its pins are the only evidence |
| Add and Remove MUST carry `target` on S2S delivery | spec MUST | 7; already enforced at `repository.php:1140` |
| Refusing an Add at the receiver's discretion | spec-sanctioned | 6.6, 6.7, 7.8, 7.9 |
| A Follow may never be answered, and a permanently pending row is valid | spec | 7.5 permits sending no Reject and says the requester "could be left in an intermediate state"; do not clean up on a timeout |
| Removing an unreachable follower is permitted | spec | 7.5 Note, "left to the discretion of the delivering server" |

## 7. Measurements, 2026-10-18

All taken against the running wp-env site.

```txt
relations                937 rows: follow/accepted 855, follow/pending 59,
                         block/active 22, follow/undone 1
re-follow after Reject   pending -> rejected -> pending -> accepted,
                         rows=1 at every step; the subject appears in
                         followers only after the Accept
forum memberships        accepted/member 122 across 121 groups; moderator 0
ledger Add/Remove        341 rows: Add/outbound 209, Remove/outbound 103,
                         Remove/local 29; public Add 97, Remove 47
/moderators              HTTP 404
Group actor document     attributedTo publishes that 404 URL on every public
                         community Group (FEP-1b12)
one actor's Outbox page  3 of 3 members are Add or Remove, all with that target
delivery spool           0 Add/Remove rows; nothing was ever delivered here
vocabulary snapshot      followers, following, liked, likes, shares, outbox and
                         inbox are all ABSENT from the 63 registered properties
registry audits          audit-collection-registry 6, audit-collection-contracts
                         12, audit-actor-outbox 16, none failed
```

The re-follow result matters because the data has never exercised it: there are
zero `rejected` rows. `UNIQUE KEY relation_identity` means one row per pair, and
`start_relation()`'s live guard only short-circuits on `pending` or `accepted`,
so a `rejected` row falls through and returns to `pending`. Reject is not
terminal. Pin it.

## 8. Known defects, not yet fixed

1. ~~`/moderators` answers 404.~~ **Corrected 2026-10-19: it does not.** A live
   public community Group's moderator collection answers 200 with its
   `OrderedCollection`, an honest `totalItems` and its members, and the
   `attributedTo` on every public Group actor document points at a route that
   works. The URL measured as 404 belonged to a fixture Group whose identity row
   is absent, where 404 is the correct answer. The original entry generalized a
   single dead sample, which is the error this document is otherwise written to
   prevent.

   Migrating moderators to the curated membership table is still right, on the
   two reasons that survive: Forum holds an authority rather than a projection,
   and the media collection needs that table. Note the migration's baseline —
   this collection currently publishes `totalItems` from
   `wp_ax_forum_memberships`, and after the migration the number's source
   changes while its value must not.
2. **`axismundi_act_get_liked_object_uris()` truncates and mis-sorts.**
   `min(200, $limit)` with a default of 100 silently drops members from an
   Actor who liked more, and `ORDER BY id DESC` is not reverse chronological for
   a mixed set because an inbound Like takes its `id` at receipt rather than at
   publication. `liked` is published nowhere today, so this is latent — but
   binding `ap-liked` to this reader would ship it.
3. **The flat-reader pattern recurs**: a clamped limit plus `ORDER BY id DESC`
   on a reader that can reach a published surface. Removed from the Outbox
   (cap) and from follower expansion (500, with the earliest followers dropped
   first). Still present in `axismundi_act_get_liked_object_uris()` (200) and
   `axismundi_act_relation_actor_list()` (500). `axismundi_act_get_by_actor()`
   and `axismundi_act_get_recent()` are unexamined. A repository-wide sweep is
   warranted, fixing only what reaches a published surface and recording the
   rest.
4. **The delivery spool has no retention policy** — 16,632 rows, 8.0 MiB of
   payload and 1.7 MiB of recipient lists, of which 15,040 are `failed`. 7.5's
   Note is the ActivityPub-side argument for pruning unreachable followers; the
   spool is the storage-side one.

## 9. Open questions

- **FEP-1b12 `attributedTo`.** It advertises the moderator collection through
  `attributedTo`, which is not what `attributedTo` means in ActivityStreams.
  Serving the collection removes the dangling reference either way; whether to
  keep advertising it this way is an ontology decision and changes a published
  actor document.
- **The relations column rename.** `subject_actor_uri` and `object_actor_uri`
  encode an assumption the specification does not make. Decided to do, in four
  steps and in this order: give the read API a named shape so column names stop
  being a public contract; widen resolution through `wp_ax_identities` so a
  non-actor target resolves and the member type constraint moves to the
  descriptor; rename the columns with explicit `ALTER TABLE ... CHANGE`, since
  `dbDelta` cannot rename, rebuilding `relation_identity`, `object_state` and
  `subject_state`; then rename the readers as canonical plus alias across two
  commits. Widening the table before the registry declares member types would
  leave the constraint enforced in neither place.
- **`member-level-visibility` policy.** For an explicit collection whose
  members carry their own visibility, either keep `exact` and declare that the
  count may exceed the rendered page, or count only visible members and move to
  a requester-specific total.
- **`ap-history`.** FEP-bad1 defines a `history` property whose members are
  Activities; it is recorded in the Activities plugin's `LIFECYCLE.md` section 4
  along with why the Object snapshot stays.
- **Explicit collections**, including the actor media collection, need an
  identity row and instance-level disclosure on top of the curated membership
  table decided in section 2. FEP-400e is the reference for owner approval and
  third-party proposal flow; its Delete and Move model is not adopted, because
  applying it to a pre-existing Object would read as a global deletion.
- **The curated membership table's own shape** is decided in principle and not
  in detail: whether it keys collections by URI hash or by `identity_id`, and
  whether `member_kind` is stored or resolved through `wp_ax_identities`, are
  open. Keying by `identity_id` requires every curated collection to have an
  identity row, which an implicit role collection such as moderators does not
  have today.

## Assembly implementation, 2026-10-19

`axismundi_op_assemble_collection()` accepts representation metadata separately
from membership returned by the owning reader. It applies `total_capability`
and the server-selected requester disclosure policy, requires an integer for
an exact disclosed total, and rejects enumeration for metadata-only or
count-only contracts. Routes remain responsible for authorization before
selecting a requester class or granting policy-gated disclosure.

The descriptor also fixes `collection_type`. Outbox and Inbox require
`OrderedCollection` under ActivityPub 5.1 and 5.2. Other current descriptors
choose that type as a project representation policy. A paged contract permits
only its matching page type; immutable cursor ordering cannot be registered
with an unordered root. The assembler rejects mismatches rather than silently
changing `items` to `orderedItems` or the reverse. These checks constrain the
declared representation, not the reader's actual sort order, which still needs
its own reader audit.

Outbox pages, followers/following, likes/shares, replies, usedIn, and Forum's
existing moderators route use this assembly boundary. The moderator reader
retains the current effective-role rule, including manager delegation; it does
not migrate curated membership storage. `member_types` is descriptor metadata
at this stage: source readers still enforce the existing actor resolution
constraints, and generic URI dereferencing/type validation is deferred.

The `ap-liked` contract now returns an explicit unavailable-reader error instead
of binding the capped legacy list. That internal list remains available to
existing callers. Neither an Inbox recipient reader nor a liked cursor reader
is introduced by the assembly slice.

Checks: `audit-collection-registry.php` exercises total and enumeration policy;
`audit-actor-outbox.php` compares the page JSON bytes against the pre-assembly
shape; `audit-forum-moderation.php` compares the moderator response to fixture
roles independently of the registry reader.

## Sources

- ActivityPub, W3C Recommendation 2018-01-23, sections 5, 6.5-6.7, 7.5-7.11,
  and Appendix B.
- Activity Streams 2.0 Core, section 4.6 Collection paging; Vocabulary, the
  `Follow` activity and the `object` property range.
- FEP-bad1 object history collection; FEP-400e publicly-appendable collections;
  FEP-1b12 group federation.
