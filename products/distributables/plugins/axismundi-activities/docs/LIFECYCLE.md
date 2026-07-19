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
