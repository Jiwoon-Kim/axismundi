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
