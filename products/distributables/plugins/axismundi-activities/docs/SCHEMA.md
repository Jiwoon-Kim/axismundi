# Activity storage model

> `wp_ax_activities` and `wp_ax_activity_relations` are implemented in DB v4.

## 1. `wp_ax_activities`

```text
id                    BIGINT UNSIGNED PK
local_uuid            CHAR(36) NULL UNIQUE
activity_uri          TEXT NOT NULL
activity_uri_hash     CHAR(64) NOT NULL UNIQUE
activity_type         VARCHAR(32) NOT NULL
actor_uri             TEXT NOT NULL
actor_uri_hash        CHAR(64) NOT NULL
object_uri            TEXT NULL
object_uri_hash       CHAR(64) NULL
target_uri            TEXT NULL
target_uri_hash       CHAR(64) NULL
direction             inbound | outbound | local
effective_status      active | undone
audience_json         LONGTEXT NOT NULL
payload_json          LONGTEXT NOT NULL
payload_hash          CHAR(64) NOT NULL
published_at          DATETIME NULL
received_at           DATETIME NULL
created_at            DATETIME NOT NULL
updated_at            DATETIME NOT NULL
```

Required indexes: unique `activity_uri_hash`, unique nullable `local_uuid`, actor/object/
target hashes, and `(direction,effective_status)`. A nullable UUID is intentional: every
local row has one, while remote rows are uniquely identified by their Activity URI.

Payload and normalized audience are bounded to one MiB before mutation. URI hashes are
lookup accelerators only; every match is checked against the full URI. The repository is
InnoDB and records its DB version only after table, engine, and unique indexes are verified.

`object_uri` follows ActivityStreams semantics: a Follow points to an Actor, Like to an
Object, and Accept/Reject/Undo to another Activity. `target_uri` is used by collection
operations such as Add, Remove, and Move. Every supported concrete Activity requires an
object URI; Add, Remove, and Move additionally require a target URI.

## 2. `wp_ax_activity_relations`

```text
id                       BIGINT UNSIGNED PK
relation_type            follow | block
subject_actor_uri        TEXT NOT NULL
subject_actor_uri_hash   CHAR(64) NOT NULL
object_actor_uri         TEXT NOT NULL
object_actor_uri_hash    CHAR(64) NOT NULL
direction                inbound | outbound | local
state                    pending | legacy_pending | accepted | rejected | active | undone
initiating_activity_uri  TEXT NULL
state_activity_uri       TEXT NULL
evidence_type            activity | legacy_snapshot
evidence_ref             TEXT NULL
created_at               DATETIME NOT NULL
updated_at               DATETIME NOT NULL
```

The unique identity is `(relation_type, subject hash, object hash)` followed by exact URI
verification. `subject/object` supports local-local and future managed Actors; names such as
`local_actor_uri`/`remote_actor_uri` are forbidden because locality is not the relation's
identity. Direction is a local query convenience derived when the relation is written.

Followers and following are projections of accepted Follow rows. They are not duplicated
as authoritative collection tables.

Follow uses `pending|legacy_pending|accepted|rejected|undone`; Block uses `active|undone`.
`legacy_pending` is preserved for administrator visibility but is excluded from accepted
following projections and is never retransmitted. Snapshot rows have no initiating Activity;
they carry a bounded compatibility-source reference instead. A real relation-bearing Activity
atomically replaces snapshot provenance and can never be overwritten by a later snapshot.

Relation rows
are materialized in the same InnoDB transaction as their immutable Activity. The database
version is recorded only after both verified tables and their unique indexes exist.

## 3. Future logical collection membership

Phase 4 may add `wp_ax_activity_memberships` for `inbox|outbox` membership keyed by owner
Actor URI and Activity URI. It must not contain `notifications`: notification state belongs
to the Notifications plugin.
