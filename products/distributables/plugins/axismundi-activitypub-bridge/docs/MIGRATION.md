# Legacy ActivityPub migration contract

> Status: **0.0.7 dry scan shipped. Import and purge are disabled.**

## Ownership

The Bridge owns migration because it is the only package allowed to understand both the
official ActivityPub plugin's CPT/postmeta model and Axismundi's URI-keyed repositories.
The scanner reads stored data only. It performs no remote discovery or refresh.

## Independent decisions

Every source receives two decisions:

- **Import**: `importable`, `duplicate`, `snapshot_only`, `deferred`,
  `transport_pending`, or `failed`.
- **Purge**: `purgeable`, `runtime_required`, `deferred`, or `blocked`.

An importable or duplicate row is not necessarily safe to purge.

| Official source | Axismundi destination | Current purge rule |
|---|---|---|
| `ap_actor` | Actors remote Actor repository | `runtime_required` until official signature verification can resolve Axismundi public keys |
| `ap_post` | Object Projections remote object repository | conditionally `purgeable` after URI/payload verification |
| `ap_inbox` | Activities inbound ledger | conditionally `purgeable` after replay and recipient verification |
| `ap_actor` + `_activitypub_following` | Activities relation state | `snapshot_only` unless Inbox replay reconstructs the accepted Follow |
| `ap_outbox` | no authoritative import | pending rows blocked; delivered transport history has a separate purge scope |
| `activitypub_status` | Activities lifecycle baseline | blocked until an existing federated object cannot emit a second Create |
| extra fields/comments | future verified-link and Reply features | deferred |
| official key options/usermeta | official signing transport | always `runtime_required` while Bridge uses official key custody |

## Frozen safety rules

1. Use repository APIs only; never raw-write Axismundi tables.
2. Preserve remote canonical URIs and source payloads. Local recipient mapping is calculated
   from official user IDs; payload strings are never rewritten.
3. Replay Inbox history before reconciling follower snapshots. Never invent a remote-authored
   Follow Activity solely from current-state postmeta.
4. Existing `activitypub_status=federated` objects require a lifecycle baseline before the
   marker can be removed, otherwise a later edit may emit a second Create.
5. Import, verification, and purge are separate explicit operations. Purge is scoped by
   dataset and fails closed on blocked, failed, ambiguous, or truncated results.
6. Official private/public key material is never rendered, copied into migration reports,
   or deleted while the official plugin remains the signing implementation.
