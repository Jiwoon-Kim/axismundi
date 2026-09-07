# Activity storage model

> `wp_ax_activities`, `wp_ax_activity_relations`, and `wp_ax_quote_authorizations` are
> implemented and share **one** schema version — currently **DB v6**. There is no per-table
> version: `install()` verifies every table and records the single version only when all of
> them pass, so a partial migration leaves the version behind and is retried. v5 added
> `instrument` to the ledger; v6 added the QuoteAuthorization table. A change to one table
> still advances the version for all.

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
instrument_uri        TEXT NULL
instrument_uri_hash   CHAR(64) NULL
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

### `instrument` (DB v5)

Nullable `instrument_uri` / `instrument_uri_hash`, indexed on the hash. `instrument` is a
general ActivityStreams member, not a Quote-specific alias: for `QuoteRequest`, `object_uri`
is the quoted Object and `instrument_uri` is the independent Object that quotes it.
`target_uri` is not reused because its meaning remains collection destination/origin for
Add, Remove, and Move. Hash lookup verifies the full instrument URI.

The member is normalized whenever present and no type requires it, so the column stays
general. FEP-044f sends the quoting Object embedded rather than as a bare URI; normalization
reduces it to its `id` while `payload_json` keeps the original untouched. A source event
whose replay resolves to a different instrument is a conflict, not the same Activity.

Install verifies both columns and the index **before** storing the version. A site that
recorded v5 while dbDelta failed to add the column would never retry, and every
QuoteRequest would silently lose its instrument.

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

## 4. `wp_ax_quote_authorizations` (DB v6)

Quote consent state belongs to Activities and is separate from the observed fact that one
Object quotes another. The table is:

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
standing_key                 CHAR(64) NULL UNIQUE
created_at                   DATETIME NOT NULL
revoked_at                   DATETIME NULL
updated_at                   DATETIME NOT NULL
```

The canonical local identity is `/?ax_quote_authorization={uuid}`. It is a query URI rather
than a path so that consent resolves without a rewrite rule: proving a quote was authorized
must not depend on permalink state. Activities mints and stores that identity; Object
Projections owns its dereferenceable JSON-LD representation and the route.

The row remains after revocation so a previously issued authorization URI never changes
meaning or gets reassigned. `status` moves `active → revoked` and never back: a withdrawn
consent is not re-granted by replaying its original request, because that request already
holds its decision. A new grant needs a new QuoteRequest, which mints a new identity.

Both uniqueness rules are enforced by the database, not by a read-then-write check:

- **One QuoteRequest issues at most one authorization.** Unique `request_activity_uri_hash`.
  A re-delivered request returns the decision already made rather than minting a second
  identity for the same consent.
- **One standing authorization per quoting/quoted/author triple.** `standing_key` is the
  hash of that triple, held only while the authorization stands and cleared on revocation,
  under a unique index. A composite `(triple, status)` index cannot express this: it would
  also forbid the second honest revocation. Nulling the key instead lets revoked rows for
  the same triple accumulate, because SQL treats each NULL as distinct. A different request
  for the same triple therefore returns the standing authorization instead of issuing a
  second one.

Identity resolution is exact. A candidate URI must match this site's origin **and path**,
carry exactly the one `ax_quote_authorization` argument, and have no credentials or
fragment. Matching the host alone would accept `/anything/?ax_quote_authorization={uuid}`,
which is a URI this site never issued; accepting extra arguments would let one
authorization be referenced under unboundedly many spellings. Lookups use URI hashes plus
exact URI verification, so a foreign URI carrying a known UUID resolves nothing.

Revocation is a conditional transition on `status = 'active'`, so exactly one caller
withdraws an authorization and only that caller's `axismundi_act_quote_authorization_revoked`
fires. A caller that loses the race receives the current row without announcing a withdrawal
it did not perform — Delete forwarding hangs on that hook, and forwarding twice is a
protocol error.

Revocation says nothing about whether the quote Object still exists. That is the
projection's observation, not this store's decision (SPEC §19).
