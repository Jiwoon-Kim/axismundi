# Legacy ActivityPub migration contract

> Status: **0.0.12 import, relation provenance, and WebFinger interop shipped. Purge remains disabled until 0.0.13.**

## Ownership

The Bridge owns migration because it is the only package allowed to understand both the
official ActivityPub plugin's CPT/postmeta model and Axismundi's URI-keyed repositories.
The scanner reads stored data only. Import uses existing repository APIs and performs no remote
discovery or refresh. It writes only missing supported Axismundi rows and never changes an
existing cache from an older official snapshot.

## Independent decisions

Every source receives two decisions:

- **Import**: `importable`, `snapshot_importable`, `duplicate`, `deferred`,
  `transport_pending`, or `failed`.
- **Purge**: `purgeable`, `runtime_required`, `deferred`, or `blocked`.

An importable or duplicate row is not necessarily safe to purge.

| Official source | Axismundi destination | Current purge rule |
|---|---|---|
| `ap_actor` | Actors remote Actor repository | `runtime_required` until official signature verification can resolve Axismundi public keys |
| `ap_post` | Object Projections remote object repository | conditionally `purgeable` after URI/payload verification |
| `ap_inbox` | Activities inbound ledger | conditionally `purgeable` after replay and recipient verification |
| `ap_actor` + `_activitypub_following` | accepted inbound follower snapshot | imported with provenance after Inbox replay; `ap_actor` remains runtime-required |
| `ap_actor` + `_activitypub_followed_by` | accepted outbound following snapshot | imported with provenance; no synthetic Follow/Accept |
| `ap_actor` + `_activitypub_followed_by_pending` | pending outbound following snapshot | imported as `legacy_pending`; never retransmitted |
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

## Import scope (0.0.9)

The explicit administrator action requires typing `IMPORT`. A complete, non-truncated dry
preflight runs first, then the Bridge processes Actors, Objects, and Inbox Activities in that
order. Each write is immediately read back and verified by canonical URI plus the strongest
available type or payload-hash assertion. A failed row remains visible in the result and can be
retried safely. Partial success is intentional: repositories own their own transactions and a
repeat import converges without duplicate identities.

After Inbox replay, current-state follower/following snapshots are imported through the
Activities provenance API. Snapshot state never overwrites Activity evidence. Transport Outbox
rows, lifecycle markers, extra fields, comments, and signing keys remain analysis-only. Source
deletes are always zero in this release.

## Short-prefix rename (wp.org review, 2026-11-21)

The wp.org review (ID `P0TDX404938HGN`) rejected the short `ax` prefix. Every name the plugin
registers globally moved to `axismundi_activitypub_bridge_`:

| Kind | Before | After |
|---|---|---|
| Tables | `ax_ap_deliveries`, `ax_ap_delivery_recipients`, `ax_collection_controls`, `ax_featured_receipts` | `axismundi_activitypub_bridge_` + `deliveries`, `delivery_recipients`, `collection_controls`, `featured_receipts` |
| Options | `ax_activitypub_bridge_*` (3), `ax_bridge_*_schema` (2), `ax_bridge_shared_inbox_public` | same suffix under the new prefix |
| Settings group | `ax_bridge_disclosure` | `axismundi_activitypub_bridge_disclosure` |
| Nonces, form fields, DB lock | `ax_bridge_*`, `ax-control-` | `axismundi_activitypub_bridge_*`, `axismundi-bridge-control-` |

WP_Error codes (`ax_bridge_*`, `ax_mod_*`) were left as they are: they are not global names.

The pre-0.0.16 delivery migration (`ax_ap_delivery` CPT, `_ax_ap_*` meta, and the fork-only
`_activitypub_external_*` Outbox rows) was removed rather than renamed. Its source names are data
written by unreleased builds and cannot change, a fresh install cannot hold that data, and the
`ax_activitypub_bridge_delivery_migration` flag shows it already ran on every existing install.

**Existing installs.** The shipped plugin no longer knows the old names, so it starts on empty
tables and default settings until the data is moved. Right after installing the renamed build:

```txt
wp eval-file scripts/migrate-short-prefix.php          dry run, report only
wp eval-file scripts/migrate-short-prefix.php apply    move
```

The script is not in the ZIP. Tables are renamed in place, so delivery row ids and the cron events
that carry them stay valid. An empty table the renamed plugin created first is dropped; a new table
that already holds rows is left alone and reported, never merged silently. Options move only where
the new name is absent. Multisite: once per site with `--url`.

Measured on wp-env: 19,191 deliveries and 212 recipient rows moved, a second run reports nothing
to move, and a forced conflict leaves both tables unchanged with a warning. The old
`ax_bridge_shared_inbox_public` no longer governs Object Projections' shared Inbox GET; the new
name does (`'0'` → `ax_bridge_shared_private`, `'1'` → 200).
