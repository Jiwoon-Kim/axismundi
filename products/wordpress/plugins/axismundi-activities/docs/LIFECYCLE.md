# Activity lifecycle

## 1. Immutable event, derived effectiveness

A successfully recorded Activity remains in the ledger. `effective_status` is only a query
acceleration for whether a valid Undo currently neutralizes it. Pending, accepted, rejected,
and delivery-failed are deliberately absent from Activity lifecycle:

- Follow pending/accepted/rejected belongs to relation state.
- HTTP delivery pending/failed belongs to Federation's delivery queue.
- Notification unread/dismissed belongs to Notifications.

Undo is itself an Activity whose `object_uri` points to the original Activity URI. Undoing an
Undo may restore the original Activity's effective state when the domain transition is valid.

Local Object products submit a complete finalized Object snapshot at a committed authoring
boundary. The first snapshot, or the first after Delete, records Create. A snapshot that differs
from the immediately preceding Create/Update records Update; an identical callback returns that
existing Activity. Create and Update embed the immutable Object snapshot so deduplication follows
representation semantics, including ordered attachments, while a later return to an older state
still becomes a new event.

## 2. Delete, Gone, and Tombstone

`Delete` is an Activity and does not erase prior events. Remote object cache state remains an
Object Projections concern:

- cache expiry removes a rebuildable observation, not its canonical URI references;
- HTTP 410 without an AS document is `gone`, not a fabricated Tombstone;
- only an actual `type: Tombstone` document is stored as a Tombstone observation.

A local Delete contains only the canonical Object URI and reuses the latest committed lifecycle
audience. It therefore remains deliverable after the source has become private, invalid, or a
Tombstone, without embedding withdrawn content. A repeated Delete returns the existing event;
republishing after it starts a new Create generation.

## 3. Hooks

Recorded/transition hooks are post-commit facts. Consumers must be idempotent because a crash
between commit and consumer work may lead to replay. Notifications, leases, and delivery use
their own unique keys rather than assuming a hook runs exactly once.

## 4. Why the Object snapshot stays, and where its history belongs

Section 1 embeds the Object snapshot in Create and Update so deduplication follows
representation semantics. A recurring proposal is to drop it -- record `object` as a bare
identifier and recompose the document on demand -- so that successive Updates stop storing
near-identical bodies. The reasoning below is why that is not the shape, recorded because the
measurements behind it are not reproducible from the code.

### Measured scale, 2026-10-18

The duplication is real and small. The ledger held 28,737 rows and 16.3 MiB of
`payload_json`, averaging 580 bytes with a 2,939-byte maximum:

| direction | rows | avg | total |
|---|---|---|---|
| inbound | 86 | 310 B | 26.1 KiB |
| local | 5,108 | 294 B | 1.4 MiB |
| outbound | 23,543 | 643 B | 14.8 MiB |

Of sampled inbound payload bytes, the embedded Object accounted for **1%** -- most inbound
Activities are Follow, Accept, Like or Undo, whose `object` is a URI string rather than a
document. So the proposal targets 0.2% of stored bytes, on the one side that cannot be
regenerated, while 91% sits in outbound rows that can.

The growth lever elsewhere is larger and unguarded: the Bridge delivery spool held 16,632
rows, 8.0 MiB of payload and 1.7 MiB of recipient lists, with **no retention policy at all**
(15,040 `failed`, 1,568 `queued`, 24 `delivered`). Wire bytes are therefore already archived
independently of the ledger, and pruning that spool is a bigger and separate concern.

### Recomposition cannot serve Update

For `Create`, recomposing from the current source is approximately faithful. For `Update` it
is wrong by definition: an Update asserts that the Object became *this*, so recomposing every
historical Update from the current source renders them all as the final state. The published
Outbox serves the stored snapshot verbatim -- `axismundi_act_public_payload()` returns the
payload minus `bto`/`bcc` -- so that would publish a history in which every edit looks like
the last one.

If `object` ever becomes an identifier, recomposition must happen at **emit** time, in Object
Projections, so the wire format is unchanged; doing it at storage time changes what an
already-published URL serves.

WordPress revisions are not the store for the missing states. Site revision policy varies, and
revisions additionally record intermediate saves the author never published, so publishing from
them would expose unsent text.

### FEP-bad1 inverts the goal

[FEP-bad1](https://w3id.org/fep/bad1) defines a `history` property
(`https://w3id.org/fep/bad1/history`) and states that the `history` MUST be an
`OrderedCollection`. Its members are **Activities** -- "all activities which target the object
as `object`" -- which are the Create and Update rows this ledger already stores.

So FEP-bad1 publishes the snapshots rather than deduplicating them. The duplication is the
edit history. Recording `object` as a bare identifier would make the feature unimplementable,
because every entry would render the current state.

Our position is better than the reference implementation's for this feature, as a consequence
of section 1 and section 2. The official WordPress plugin's own tracking issue records two
limits it cannot avoid: its outbox purges rows after 180 days, and its Delete removes the
object's outbox rows, so a tombstoned post has no history -- the one case the FEP's example
covers. This ledger purges nothing and keeps Delete as an Activity with derived
`effective_status`, so a complete history including the tombstone case is available.

### Open: whose history is it

FEP-bad1 filters membership to activities "where the `actor` matches the `attributedTo`
actor", described as those "performed by the author". An Update performed by a Group
moderator, a site administrator, or any delegated editor is therefore **not** a member. The
published history would then report fewer changes than occurred, with the Object's `updated`
moving without a corresponding entry, and a reader cannot detect the omission.

Three options, undecided:

1. implement the FEP's membership as written and document that `history` is the author's
   history rather than the Object's complete one;
2. publish every Update regardless of actor, marked `NON-STANDARD` with the reason;
3. publish the conformant collection while the ledger retains the complete chain internally.

Option 3 matches the authority/projection split used elsewhere: the ledger already records
every Update with its own `actor`, so the attribution mismatch is resolved by not publishing
those entries rather than by modelling them. The gap is worth raising upstream -- FEP-bad1 is
a draft, and a history that hides third-party edits is a design defect rather than our
misreading.

FEP-bad1 has no privacy section. Each Update row carries its own `audience_json`, so a
published history must be filtered **per entry** by the same public-membership rule as the
Outbox, not by the Object's current visibility. Otherwise a revision that was once addressed
privately leaks.

As a collection contract descriptor:

```txt
ap-history   member_models: Activity[]
             presentation: paged-members
             ordering: immutable-cursor(feed_sort_at, id)
             total_capability: unavailable
             total_reason: read-time-filter        (the Outbox's filter)
             total_disclosure: { public: never, owner: always }
```

### Standing decision

Keep the embedded snapshot. If storage pressure becomes real, the order of levers is:
compress the snapshot column; cold-store old rows; prune rebuildable projections; add a
delivery spool retention policy. Thinning the authority is not on that list.
