# Social relationship state

## 1. Follow state machine

```text
Follow recorded            -> pending
Accept(object=Follow URI)   -> accepted
Reject(object=Follow URI)   -> rejected
Undo(object=Follow URI)     -> undone
```

Each transition is caused by a separately retained Activity. The Follow payload is never
rewritten. Duplicate delivery of the same Activity URI is idempotent. A transition must
verify that the transition Actor is authorized for the referenced relation; transport-level
signature verification is Federation's responsibility and is additional to this domain check.

Accept or Reject may arrive before its Follow. The immutable transition is retained and the
relation is reconciled when the referenced Follow arrives. Only the followed Actor may Accept
or Reject. An unauthorized transition rolls back with its Activity row.

## 2. Block

Block is directional (`subject` blocks `object`). Undo(Block) changes the materialized
relation state to undone while preserving both Activity rows. Block does not imply remote
delivery succeeded and must not be used as a delivery status.

## 3. Direction

- `outbound`: subject is local and object is non-local.
- `inbound`: subject is non-local and object is local.
- `local`: both Actors are local.

Remote-remote relations are not materialized merely because they appeared in an unrelated
payload. Locality is resolved through Actors when available; canonical Actor URIs remain the
stored identity.

## 4. Administrator inspection

`Tools > Activity Log` is a read-only ledger inspector for administrators. It shows recent
Activities, immutable payload JSON, and current materialized relations. It performs no fetch,
delivery, state transition, or notification action.
