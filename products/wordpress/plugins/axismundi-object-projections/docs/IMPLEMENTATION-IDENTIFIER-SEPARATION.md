# Identifier Separation: Folder and Object

2026-10-27. Implements the first two stages of shared-registry retirement.
Actor body consolidation follows in Actors schema 39.0; see
[Actor Body](../../axismundi-actors/docs/IMPLEMENTATION-ACTOR-BODY.md).
Actor-owned curated collections remain separate future work.

## Ownership

| Domain | Authority | Identifier |
|---|---|---|
| User folder | Media Library taxonomy and term meta | Term ID |
| Object index | OP `ax_objects` | Own auto-increment `id`, UUID, exact canonical URI/hash |
| Question state | Activities `ax_activity_questions` | Own auto-increment key and exact Question URI/hash |
| Actor | Actors, temporarily `ax_identities` + `ax_actors` | Existing Actor ID, unchanged |

`ax_objects.identity_id` is physically renamed to `id`, preserving its values.
The returned PHP array retains `identity_id` as a compatibility alias for the
**Object** ID only. Remote cache `identity_id` also references this Object index,
not Actors. `attributed_identity_id` remains a nullable **Actor** accelerator;
the authoritative attribution is the recorded URI. No cross-domain ID equality
is assumed. Question source references point to the Activities row, not the OP PK.

## Migration

- OP schema version 16 copies historical Object ID, UUID, URI/hash, origin and
  status to its own table. Source metadata already held by OP is preserved.
- An anchor without source metadata receives explicit `legacy-registry/unbound`
  provenance. No source reader owns it; a public status does not expose it.
- Historical orphan OP metadata with no anchor remains unreadable. No replacement
  UUID or guessed source is invented.
- The copy verifies every anchor, rejects conflicts and rolls back before deleting
  anything. Actor-owned dependent rows block retirement rather than becoming orphans.
- Activities schema version 14 first copies Question URIs to its own state table.
  Votes keep their existing Question keys. OP is optional for Question processing.
  If the old Question table exists but Activities is unavailable, OP migration waits;
  deploy/activate both upgraded plugins before completing that migration.
- Actors retires folder anchors only after the old folder producer is absent,
  with dependent-row checks. The non-Actor allocator rejects every old kind.
- Media Library removes only the copied `_ax_media_folder_uuid` meta. Folder
  terms, ownership, hierarchy, visibility, password settings and feed settings remain.
- OP removes the two persisted AP folder rewrite rules, not unrelated routes.
  The old folder transformer and its query variables are removed. This URL retirement
  was explicitly approved by the owner. Atom and Media RSS are unchanged.

DDL is replay-safe but not transactionally reversible. Data copying and retirement
use an InnoDB transaction. Back up before upgrading; after successful retirement,
rolling back plugin files alone is not a database rollback.

## Local Measurement

Before: Actor 1,080, folder 215, Object 2,078 shared anchors.
After migration: only Actor 1,080 remains in `ax_identities`.
OP holds all 2,078 Object anchors plus seven pre-existing orphan metadata rows.
Fifty-six Object anchors had no OP metadata and remain explicitly unbound.
Principal relationships: 1,038, preserved.

Verification results: separation 22, registry 17, read API 55, term members 21,
hashtag policy 50, local folders 14, retired routes 4, Actor-only registry 9,
Actor axis 46, and principal fixture regression 20/20. Lookup and rollback
mutations are caught; reintroducing the retired folder API is caught too.
Question audit passed with OP enabled and skipped; its final enabled run has
16 checks including full OP-row snapshot preservation. The folder audit also
passed with only Media Library loaded, without Actors or OP.

The repeated regression exposed older Object fixture cleanup omissions. Scoped
cleanup was corrected in the registry, Question, post-create and audience audits.
Only exact fixtures created during this review were removed. The final registry,
Question, post-create and audience reruns each preserve all 2,085 OP rows.

## Verification Commands

Run with `wp eval-file wp-content/plugins/` followed by:

- `axismundi-object-projections/tests/audit-identifier-separation.php`
  checks migration preservation, conflict rollback, replay, unbound sources,
  independent numeric ID spaces and rejection of old allocation paths.
- `axismundi-object-projections/tests/audit-identifier-separation-mutations.php`
  with `AX_OP_ID_MUTATION=lookup` or `rollback` challenges those assertions in memory.
- `axismundi-media-library/tests/audit-folder-identity.php` now checks local User
  folders, public/private/password feed gates and absence of Actor side effects.
- `axismundi-object-projections/tests/audit-media-folder-routes.php`
- `axismundi-object-projections/tests/audit-object-registry.php`
- `axismundi-object-projections/tests/audit-object-read-api.php`
- `axismundi-object-projections/tests/audit-term-members.php`
- `axismundi-activities/tests/audit-questions.php`; also run with OP skipped to
  verify that Question state and votes do not depend on the projection plugin.
- `axismundi-actors/tests/audit-actor-axis.php`
- `axismundi-actors/tests/audit-principal-fixture-cleanup.php`

The old folder collection audit is retired with the removed product feature.
Fixture cleanup deletes Object IDs only from OP; it must never delete an Actor
merely because its numeric ID happens to equal an Object ID.

## Deferred

Move Actor identifier fields into `ax_actors` without renumbering. Build a separate
Actor collection/membership store. No principal rename, Note cut-off, C2S content
table, Contacts/JMAP redesign, packaging or deployment is part of this change.
