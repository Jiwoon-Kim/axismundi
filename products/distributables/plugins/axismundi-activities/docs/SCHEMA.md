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

### Planned Quote increment

The FEP-044f increment adds nullable `instrument_uri` and `instrument_uri_hash` columns
with an index on the hash. `instrument` is a general ActivityStreams member, not a Quote-
specific alias: for `QuoteRequest`, `object_uri` is the quoted Object and `instrument_uri`
is the independent Object that quotes it. `target_uri` must not be reused because its
meaning remains collection destination/origin for Add, Remove, and Move. Hash lookup must
continue to verify the full instrument URI.

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

## 4. Planned `wp_ax_quote_authorizations`

Quote consent state belongs to Activities and is separate from the observed fact that one
Object quotes another. The planned table is:

```text
id                           BIGINT UNSIGNED PK
local_uuid                   CHAR(36) NOT NULL UNIQUE
authorization_uri            TEXT NOT NULL
authorization_uri_hash       CHAR(64) NOT NULL UNIQUE
request_activity_uri         TEXT NOT NULL
request_activity_uri_hash    CHAR(64) NOT NULL
quoted_object_uri            TEXT NOT NULL
quoted_object_uri_hash       CHAR(64) NOT NULL
quoting_object_uri           TEXT NOT NULL
quoting_object_uri_hash      CHAR(64) NOT NULL
requester_actor_uri          TEXT NOT NULL
requester_actor_uri_hash     CHAR(64) NOT NULL
author_actor_uri             TEXT NOT NULL
author_actor_uri_hash        CHAR(64) NOT NULL
status                       active | revoked
created_at                   DATETIME NOT NULL
revoked_at                   DATETIME NULL
updated_at                   DATETIME NOT NULL
```

The canonical local identity is `/?ax_quote_authorization={uuid}`. Activities mints and
stores that identity; Object Projections owns its dereferenceable JSON-LD representation.
The row remains after revocation so a previously issued authorization URI never changes
meaning or gets reassigned. Required lookups use URI hashes plus exact URI verification.
At minimum, one QuoteRequest may issue at most one authorization, and one active
authorization may exist for a quoting/quoted Object pair and author.
