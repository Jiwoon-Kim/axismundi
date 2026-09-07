# Contacts: select, label, and return

## User Request Log

- Preserve the uncommitted directory search, complete pagination, counts and access fixes.
- Inspect the existing full editor, schemas, routes and neighboring product contracts first.
- Use the supplied Google Contacts list/detail pages for behavior and IA only; retain no personal reference data or assets.
- Complete a selectable list, label management and coherent detail/edit workflow within wp-admin.
- Preserve actor ownership, manager checks, the JSContact single writer, revisions, provenance and extensions.
- Keep private Cards out of public objects, activities, follows, search, Calendar, GeoData and Map.
- Stage JMAP/accounts and import/sync separately; no external service dependency.
- Run Contacts audits, regression tests, lint, diff checks, validator and desktop/mobile wp-env QA. No commit, packaging or release.

## Diagnosis before implementation

The existing directory work is present and retained. WordPress 7.1 runs at localhost:8884.
Live inspection opened a saved Card, entered the complete editor, and added an empty email
control without saving the existing record. The editor already provides structured names,
organizations, endpoints, addresses, localizations, raw JSContact and revision-aware Save/Done.
An otherwise empty detail currently displays only its title and Edit. The directory has an
unused Mine column and emphasizes a linked Actor URI over useful contact facts.

AddressBooks already implement private, same-owner many-to-many filing. Their default book
is an internal creation target; All contacts includes unfiled Cards and excludes the profile.
Only group creation is exposed. There is no list selection, filing UI, rename or label removal.
Labels will be the UI name for existing non-default AddressBooks, not a new entity or schema.

The supplied Google list was inspected for search, scanning columns, per-row checkboxes,
labels and edit affordances; detail for back/edit, readable facts and label management.
Adopt these interaction patterns. Communication shortcuts, recent interactions, import,
merge and trash are separate products and are deferred. No reference personal data is used.

Read scope: Contacts directory/admin/detail/editor, fields and editor assets, Cards/schema,
AddressBooks, draft permissions/save, JSContact projection and audit contracts; Actors'
JSContact ownership seam; Activities/Object Projections entry-point boundaries; Calendar's
profile contributor, GeoData and Map entry points. Repository JMAP search found design
references and membership vocabulary, but no implemented JMAP account/session runtime.
Actors owns identity and authorization; Contacts owns private Cards; the other modules own
public activity/projection, schedule and geographic contracts. None needs modification.

## Staged roadmap

1. Directory and labels: create/rename/remove labels, readable rows and same-owner filing.
2. Detail and editor: show labels and useful empty detail, keep the existing single editor
   and search/page return context. Further editor simplification requires separate UX work.
3. Selection/bulk: this slice selects only the displayed page and adds/removes one label
   per submission. Explicit counts and reset on navigation. Later: cross-page selection,
   recovery-backed deletion, export and merge, each with its own semantics and audit.
4. Accounts/import/sync: separately define JMAP session/account capabilities and lossless
   import reconciliation. No protocol adapter becomes a second Card writer or store.

## Implementation plan and gates

Create includes/labels.php for authorized metadata commands and shared form presentation;
assets/admin/directory.js for progressive checkbox selection; tests/audit-labels.php for
real storage/permission regressions. Modify admin.php (list/sidebar/assets), card-detail.php
(labels/empty state), contacts.css (scoped token consumers) and plugin bootstrap (module).
Retain directory.php queries, Card editor/save runtime and schema. Reuse existing Contacts
field tokens and WordPress controls; no global design-system or theme edits.

All commands bind a nonce to the acting owner and recheck current management. Validate the
entire bounded selection before writing. Use transactions and row locks for metadata changes,
check database failures, bump book revisions only on changes, and preserve Card bytes,
Card revision, provenance and indexes. Label rename/removal uses the displayed book revision
to reject stale forms. Removing a label only removes its memberships and container.
Return URLs are reconstructed from allowlisted local context, never arbitrary input URLs.

Applicable gates: G1 validator, G2 unchanged baseline, G6 responsive visual QA, G8 native
semantics, G11/G12 owner/privacy invariants and G21 plugin/theme boundary. No component
promotion or release gates apply. Validation: wp-env Contacts/directory/labels audits,
PHP lint, node --check, npm test and git diff --check; actual desktop/mobile filing and
detail/edit return, checkbox keyboard behavior, errors and fixture cleanup.

Risks: mixed-owner selections, Actor switching, profile/default book mutation, partial
writes, stale label forms, orphaned navigation after removal and mobile table width.
These determine regression coverage. User explicitly authorizes autonomous implementation
within this boundary, superseding the repository's default plan approval pause.

## Results

Implementation and verification pending.
