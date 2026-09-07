# Contacts: find the Card somebody kept

## User Request Log

- Inspect the existing Contacts implementation, tests and product documents before choosing work.
- Exercise the substantial Card editor and draft/save route in real wp-env; preserve recent work.
- Compare Contacts with Theme, Actors, Activities/Object Projections, Calendar, GeoData and Map.
- Implement a bounded, high-confidence product slice; ask about genuinely ambiguous product choices.
- Keep the canonical JSContact document and single-writer contracts intact.
- Review follow-up: close the foreign edit-URL name disclosure with the same acting-owner
  and current-user gate as detail, and add the missing editor regression case.
- Future expansion must be staged: directory/list and labels, then contact detail/editor,
  then JSContact/AS2/JMAP integrations as separate capabilities.

## Diagnosis and selected route

The working tree was clean at the start. WordPress 7.1 is running at localhost:8884.
The existing editor creates a record, edits its whole JSContact document, saves through
`/axismundi-contacts/v1/cards/{id}/draft`, and opens a separate contact detail screen.
The editor already supports structured names, organizations, endpoints, addresses,
localizations, anniversaries and arbitrary retained JSON properties. It is not a skeleton.

The next bounded product need is finding a saved Card. The directory currently has no
search and reads at most 200 rows, with no way to reach the rest. The sidebar count
includes the owner's profile, although that Card is excluded from the visible list.
The existing normalized endpoint index is already maintained by the canonical save path.

Selected: an Actor-scoped saved-contact directory with local search, pagination and
counts that describe the visible population. Search covers the displayed name and the
existing email, phone and URL projections; it does not promise full-document search.
Phone matching is a discovery aid, never identity evidence or a merge command.

## Ownership comparison

| Product | Current responsibility and implication for Contacts |
| --- | --- |
| Theme | Block appearance and page composition. Private directory queries and actions belong in Contacts; no theme change is needed. |
| Actors | Identity, acting-Actor selection and manager authorization. The current `includes/jscontact.php` delegates the Card to Contacts and contributes no names or anniversaries. Contacts must recheck the manager gate. |
| Activities | Activity and relationship state. Saving or finding a Card must not imply a follow, invitation or other social action. |
| Object Projections | ActivityStreams representations, public routes and remote observations. Private contact search does not query the public object/feed graph. |
| Calendar | Schedules, occurrences, recurrence and calendar resources. Its current public-calendar contributor is a separate projection seam; interpreting private anniversaries as scheduled reminders needs another product decision. |
| GeoData | Geographic vocabulary, location indexes and privacy-aware coordinates. Country choices are already consumed by the editor. A Card address does not create a site Place or geotag. |
| Map | Renders GeoData features and tracks. A saved contact must not silently become a map feature. |

The early `AXISMUNDI-PLACE-AND-CONTACTS.md` and Actor profile plan are historical inputs,
not a complete description of today's runtime. Later code owns Cards directly by Actor,
files one Card into several same-owner groups, and keeps a separate profile binding.
This slice follows those implemented contracts without rewriting earlier decisions.

## Plan before implementation

Read: Contacts `cards.php`, `schema.php`, `address-books.php`, `admin.php`, `card-detail.php`,
`card-editor.php`, the editor runtime, `rest-draft.php`, lookup/profile/projection code and
existing audits; the two product plans above; neighboring plugin entry points and
Actors/Calendar JSContact seams. These establish the read model, ownership and writer fences.

Create `includes/directory.php` for authorized read queries and directory presentation;
load it from the plugin entry point. Update `admin.php` to use the new directory and
matching sidebar counts, plus scoped admin CSS. Add a focused wp-env audit for actual
query results, isolation, pagination, matching and rendering. Preserve existing storage
helpers because other callers use their whole-Card counts, including profiles.
Carry search/page context through `card-detail.php` and `card-editor.php` navigation.

Dependency assumptions: existing tables and derived indexes suffice; WordPress owns
request parsing, query preparation and native form/link semantics. No schema migration,
new dependency or additional Card writer is required. Read queries recheck the current
user's authority over the requested Actor and reject foreign groups.

Applicable gates: G1 validator; G2 unchanged baseline; G6 visual QA, G8 native semantics,
G11/G12 ownership invariants and G21 theme/plugin boundary as relevant checks. This is
not a component-matrix promotion or packaged release, so G3-G5 and component/infrastructure
artifact gates do not trigger styleguide publication or a release-version change.

Validation: existing Contacts audit; new directory audit through wp-env; PHP lint;
`npm test`; `git diff --check`; live search, empty result, clear, paging and detail navigation,
including narrow viewport and console checks. Fixtures remove only their own data.

Non-goals: editor rewrite, new contact fields, full-document/localized-name search,
import/export protocols, merge/sync, public sharing changes, follow/message/invite actions,
birthday schedules, Place materialization, maps, theme/baseline changes and deployment.

Risks: search must escape SQL wildcard input, endpoint matches must not multiply rows,
profile exclusion must happen before counting/pagination, same-owner groups must not become
an authorization shortcut, and deleted/reordered results must not strand an invalid page.
Contains searches scan the current owner's projections; a specialized full-text index is
a later scaling decision, not a reason to scan or mutate the canonical JSON here.

The task explicitly authorizes implementing a bounded slice after diagnosis; no additional
plan approval is needed for this scope.

## Verification

Implemented locally; no release package, commit or deployment was requested.

The live workflow found three additional defects within the selected retrieval surface:

- The detail screen read any supplied Card id without checking its owner. It now requires
  both the acting Actor's ownership and the current user's Contacts permission, and gives
  the same visible response for missing and inaccessible Cards.
- A structured-only name appeared in the list and editor but became `(no name)` in detail.
  Detail now reads the same derived display-name column, without modifying Card JSON.
- Directory asset URLs pointed at nonexistent `includes/assets/` files. They now resolve
  from the plugin entry point; both the stylesheet and script paths are covered by the audit.

Validation results:

- Existing `tests/audit-contacts.php`: **335 checks, 0 failed**, before and after the
  directory/detail changes. Its existing WP-CLI redirect warnings and duplicate Object
  Projections block-registration notice remain; no assertion failed.
- New `tests/audit-directory.php`: **34 checks, 0 failed** after the review follow-up below. Uses 205 numbered Cards plus
  unfiled, structured-name and profile fixtures. Covers full pagination, owner isolation
  (including another administrator and anonymous access), foreign groups, profile exclusion,
  Unicode/name/email/phone/URL matching, multiple matches without duplicate rows, literal
  SQL wildcard/quote input, empty and out-of-range pages, non-mutating reads, form/navigation
  output, detail/editor access and names, the full edit-URL dispatch, and actual registered asset URLs.
- `npm test`: **1.000 PASS on axes A-G**. Generator-only line-ending churn was restored.
- PHP lint on all changed PHP files and `git diff --check`: pass.
- Live wp-env: created a normal contact through the existing editor, saved and reopened it,
  found it by email, opened detail, entered the editor and returned to the same search.
  A disposable 51-Card group showed 50 rows on page one and the final row on page two;
  a new search reset page two, no-match text appeared, and Clear search retained the group.
- Visual QA at 390×844 and 1280×900: scoped styles load, mobile stacks the directory,
  desktop uses the sidebar grid, and neither viewport has horizontal overflow.
  Browser error log is empty; the pre-existing PWA Workbox scope warning remains.
- Removed the 51-Card UI fixture group, the Card created through the editor, and the
  temporary fixture script. The original three saved contacts remain; the separate
  profile is unchanged. Browser viewport overrides were reset.

Changed files:

- `includes/directory.php` (created): authorized local retrieval, counts, paging and search UI.
- `includes/admin.php` (edited): directory integration, correct counts and asset URLs.
- `includes/card-detail.php` (edited): owner gate, consistent names and return context.
- `includes/card-editor.php` (edited): return context and PHP screen authorization; editor/save runtime preserved.
- `assets/contacts.css` (edited): scoped responsive search/paging layout.
- `axismundi-contacts.php` (edited): load the directory module.
- `tests/audit-directory.php` (created): focused executable regression coverage.
- This document (created): request log, diagnosis, boundaries, scope and evidence.

The private directory is the selected product foundation: keep a Card, find it again,
read its endpoints and return to the same context. Cross-product actions remain later
decisions. Search deliberately covers display names and existing endpoint projections;
localized names, organization affiliations, private note text and arbitrary JSON fields
are retained but are not new searchable fields in this slice. No ownership, canonical
document, writer, public-sharing, Calendar, GeoData, Map or Theme contract was changed.

## Review follow-up: edit-screen authorization

The review identified a separate rendering path missed by the detail tests: the editor's
JavaScript bootstrap checked permission, but its PHP heading only checked existence.
Knowing a foreign Card id therefore disclosed its private name through `action=edit`.

Plan: retain the two-argument editor-screen API, resolve the acting Actor inside that
screen, and require both matching Card ownership and the existing current-user Contacts
gate before reading the document or rendering the name/mount. Return the existing missing
Card markup on every refusal. Extend `audit-directory.php` with the real admin dispatch,
direct foreign/missing/anonymous cases and an authorized editor case. The fixtures activate
their own Actors so the acting-Actor route runs as it does in wp-admin.

Scope is `card-editor.php`, the directory audit and this record. No change to the REST
writer, schema, publication policy, assets or editor controls is needed. G1/G2 and the
ownership invariants remain applicable; verify with both Contacts audits, PHP lint,
`npm test` and `git diff --check`. The key regression risk is accidentally blocking the
owner's ordinary or profile Card editor, covered by the existing audit and positive case.

Result: the new regression cases failed before the fix (**34 checks, 3 failed**) and pass
after it (**34 checks, 0 failed**). The failing cases were the direct foreign editor,
the complete foreign edit-URL dispatch and anonymous rendering with an owning group id.
The authorized owner still sees the name and editor mount. The existing Contacts suite
also passes **335/335**, PHP lint and `git diff --check` pass, and `npm test` remains
**1.000 on A-G**. Existing CLI redirect/block-registration notices remain unchanged.
Only fixture-owned data was created and removed. Existing uncommitted work is preserved.

## Staged brief for future Contacts work

Use Google Contacts as a workflow reference within each stage. First inspect the existing
Axismundi implementation and real wp-env behavior; propose a bounded slice with acceptance
checks before changing it. A complete transplant is not the implementation brief.

1. **Directory/list and labels.** Refine finding, selecting and filing saved Cards using
   the existing same-owner membership model. Inspect current group capabilities before
   choosing label creation/renaming, filing/unfiling or bulk operations. Acceptance must
   cover owner isolation, one Card in several labels without copies, preservation of Cards
   when a label is removed, and search/pagination context through the chosen workflow.
2. **Contact detail/editor.** Refine reading and changing the existing Card document with
   its established editor and draft route. Diagnose the actual UX gap before adding fields.
   Acceptance must cover authorized rendering, saved/unsaved/error behavior, revision
   conflicts and lossless retention of localizations, unknown properties and provenance.
3. **Protocol capabilities, separately scoped.** Treat JSContact interchange, AS2
   identity/relationship/projection integration and JMAP client access as independent
   capabilities with individual briefs. JSContact must retain Card identity and document
   structure; AS2 must preserve explicit public/private and social-action boundaries;
   JMAP must adapt the existing ownership/store/write contracts. No protocol becomes a
   second Card store or turns saving a contact into a follow or publication.
