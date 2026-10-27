# Actor Body and Detail Storage

2026-10-27. Actors schema 39.0, following Folder/Object identifier separation.

## Read Model

`ax_actors` now owns the local/remote Actor index and its primary key `id`.
UUID, canonical URI/hash, origin, status and profile summary live on that row.
The shared `ax_identities` table is removed, not kept as a view or second authority.

Compact reads retain the existing column vocabulary: `actor_type`,
`preferred_username`, `display_name`, `summary`, attachment references, profile URL,
language and published timestamp. `host`, `icon_uri` and `image_uri` are indexed or
materialized summary facts. `host` is the canonical URI authority, including its
port, not a substitute for the acct/WebFinger address ledger. Local images retain
their attachment references. Remote image URIs are observations, not a fetch authorization;
existing asset-cache and SSRF policy still govern fetching and display.

URI lookup and delivery candidate lookup read the body directly. The compact Actor
getters do not fetch or decode the original JSON. Some older list SQL still performs
a compatibility self-join; neither side contains the old JSON payload.

`ax_actormeta` owns detail/evidence values, keyed by `(actor_id, meta_key)`:

- `remote_payload`: the exact previously stored JSON snapshot, including unknown
  properties. This is not a claim to preserve the original HTTP transport bytes.
- `legacy_identity_updated_at`: the former parent-row timestamp. Existing profile
  timestamps remain on the body; no historical timestamp is silently discarded.

The metadata FK cascades on physical Actor deletion. Tombstoning remains a status
update, so its detail evidence is retained. Names/translations, verified profile
fields, endpoints, keys, addresses and access grants keep their normalized tables;
this change does not flatten their provenance/verification columns into arbitrary JSON.
No JSContact Card data or JMAP capability allocation is moved into Actors.

## Compatibility

Existing Actor IDs, UUIDs, URIs and child-table references keep their values.
`identity_id` remains a nullable unique compatibility alias on the body while old
SQL consumers are migrated. `id` is the sole auto-increment primary key. Creation
writes the alias in the same transaction before granting access or committing.
Normal hydration rejects a mismatched/null alias. This alias is a service invariant,
not an independent identity or a MySQL CHECK constraint.

The deprecated `axismundi_actors_identities_table()` name resolves to `ax_actors`
after cutover. Non-Actor allocation remains rejected. `object_kind` is generated as
the constant `actor`, not an extensible parent-model discriminator.

## Migration

The historical schema installer completes pre-39 upgrades first. The new body is
built on a migration-owned staging table and every original Actor anchor/profile
column is compared before cutover. Payload snapshots are compared byte-for-byte
against metadata. The body then replaces the profile table with an atomic rename;
verified legacy parent/profile tables are removed. An interrupted rename can resume.

Allocation preserves the old registry's AUTO_INCREMENT high-water mark, not just
MAX(live Actor ID), so a deleted historical Actor ID is not reused for a new Account.
An advisory lock serializes migration workers. **Deployment convention: pause writes
for the upgrade.** Concurrent old-code writers during DDL cutover have not been tested;
the advisory lock alone does not coordinate every external plugin writer.

Back up before upgrading. File rollback alone is not a database rollback. This run
was local wp-env only; no package or staging deployment was produced. The tested
upgrade baseline was schema 38.0. The older v6 endpoint simulation that modified live
tables was replaced by a modern-schema endpoint preservation assertion; a full v6
installation upgrade has not been revalidated in this slice.

## Evidence

- Actor count: 1,080; no ID/alias mismatches after cutover.
- Principal rows: 1,038, unchanged after exact review-fixture cleanup.
- Metadata: 274 remote snapshots and 1,080 legacy timestamps.
- OP rows: 2,085, unchanged by body migration.
- Pre-body SQL backup comparison: all 1,080 ordered `id`, `uuid`, `canonical_uri`,
  `canonical_uri_hash` and `origin` rows match exactly. SHA-256:
  `89acb8e5ad1003a4d5a2cbbd74f1a0c37113e6cf4454a4d174d4a614ea05b0eb`.
  Reproduce with `AX_ACTOR_URI_BASELINE=<trusted-pre-body-dump.sql>` and
  `wp eval-file wp-content/plugins/axismundi-actors/tests/audit-actor-uri-preservation.php`.
  The audit imports only the old identity table into a connection-local temporary table;
  it does not restore the backup into application tables.
- `tests/audit-actor-body.php`: 18 checks, including one-query compact lookup,
  JSON detail preservation, allocator high-water mark, replay and detail cleanup.
- `tests/audit-actor-body-mutations.php`: `AX_ACTOR_BODY_MUTATION=allocator` and
  `payload` are both caught by the focused audit.
- Repository audit: 21 checks, including exact principal preservation; vocabulary:
  32; principal fixture regression: 20/20.
- Remote discovery audit: 15 checks; OP identifier separation: 22; OP registry: 17.

All commands use `wp eval-file wp-content/plugins/<plugin>/tests/<audit>.php`.
Actor collection/membership storage and removal of the compatibility ID alias remain
separate work; neither User folders nor OP Objects are reintroduced as Actor children.
The historically named `ax_identity_relations` remains Actor-scoped alias/move evidence;
it is not a cross-domain Object/Collection identity parent.
