---
rule_id: data-layer.persistence
domain: data-layer
topic: authority-reconciliation
field_cluster: write-substrate
wp_min: "verification-needed"
wp_recommended: ""
status: evolving
language: js
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core/
    section: "@wordpress/core-data — saveEditedEntityRecord, saveEntityRecord, autosave"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/
    section: "@wordpress/editor — savePost, save flow, lock semantics"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/rest-api/reference/posts/
    section: "REST API — POST/PUT/DELETE entity mutations"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/advanced-administration/wordpress/revisions/
    section: "WordPress revisions — temporal authority snapshots"
    captured: 2026-05-09
related:
  - data-layer.entity-resolution            # read substrate counterpart; this chunk is the write/reconciliation substrate
  - block-authoring.block-json.bindings     # writable bindings flow through this layer
  - block.dynamic-rendering                 # rendered output reads persisted state; persistence updates what next render sees
  - (planned) interactivity.runtime-state   # interactivity orchestrates including reconciliation lifecycle
  - (planned) data-layer.entity-types       # per-entity-type lifecycle (post draft/publish/scheduled affects persistence)
  - (planned) plugin-dev.rest-permission    # capability enforcement at persistence boundary
---

# RULE — persistence — authority reconciliation protocol

## WHEN

Asking what happens between a user mutation in the editor and
the moment that mutation becomes durable / visible to other
clients / canonical in the database. Use this chunk to
understand:

- Why "Save" in WordPress is not a single transaction (extending
  entity-resolution's 6-stage pipeline into full reconciliation
  ontology).
- The semantic difference between autosave, revision, draft,
  scheduled, and published states — they are not different
  "save targets" but different **authority reconciliation
  modes**.
- How conflict resolution happens (or fails) when multiple
  clients edit the same entity.
- Why optimistic UI is not optimization but structural
  necessity — the editor cannot synchronously wait for
  persistence.
- When and where capability checks actually happen (NOT at edit
  time; at persistence time).
- The relationship between in-memory edit buffer (entity-
  resolution chunk's "edited record" layer) and the durability
  pipeline this chunk documents.

This chunk completes the **read/write data-layer substrate**:
- entity-resolution: read substrate (subscriptions, resolvers,
  entity store).
- **persistence: write substrate** (this chunk — reconciliation,
  conflict, durability).

After this, Phase 7 has its full state substrate; interactivity
can enter as the **reactive orchestration grammar layer** on
top.

## SHAPE

### 1. Save pipeline — full reconciliation lifecycle

Extending entity-resolution's 6-stage save pipeline with
reconciliation specifics:

```
1. User edit (block content, attribute, meta, etc.)
   ↓
2. editEntityRecord(...) dispatch
   → edit buffer accumulation (in-memory only)
   ↓
3. Save trigger (manual button / autosave timer / programmatic)
   ↓
4. saveEditedEntityRecord(...) dispatch
   → optimistic UI update (often)
   → REST request initiation (POST/PUT)
   ↓
5. Server-side processing
   → capability check (current_user_can on edit_post / etc.)
   → REST permission_callback evaluation
   → content sanitization / kses filters
   → meta sanitization / register_meta validation
   → pre-save filters (content_save_pre, etc.)
   → DB write (wp_update_post / wp_insert_post / update_post_meta)
   → revision creation (if applicable — wp_save_post_revision)
   → post-save hooks (save_post action)
   → response serialization
   ↓
6. Network transport (response)
   ↓
7. Client-side reconciliation
   → success: edit buffer cleared, persisted layer updated,
              selectors re-emit, subscribers re-render
   → failure: edit buffer preserved, error surfaced in UI,
              user retry path activated
   → conflict: server-version-newer detection (if implemented),
               manual resolution path
```

**12+ stages** crossing edit / dispatch / network / capability /
sanitization / DB / revision / response / store / UI. Each stage
has failure modes. Save is a **lifecycle**, not an event.

### 2. Three concurrent authority layers

Gutenberg simultaneously operates three authority layers with
distinct roles:

| layer | content | persistence | scope | source-of-truth for |
|---|---|---|---|---|
| **persisted (canonical)** | last server-confirmed state | DB | global (all clients) | other clients, frontend renders, REST consumers |
| **edited (draft buffer)** | client-local edits not yet saved | in-memory + autosave | this client only | editor UI of this session |
| **transient (UI/runtime)** | selection, focus, animation, undo history | in-memory | this client only | UI affordances |

These layers are **not stages of a pipeline** — they are
**concurrent state spaces** with different durability, scope,
and authority semantics. Persistence is the act of reconciling
the edited layer INTO the persisted layer.

### 3. Autosave and revisions — temporal authority snapshots

Autosave and revisions are NOT "backup features." They are
**temporal authority snapshots** with distinct semantics:

| mechanism | trigger | scope | visibility |
|---|---|---|---|
| **edit buffer** | every edit | client-local memory | editor UI only |
| **autosave** | timer / blur (typically every 60s in editor) | per-user, server-side post | recovery only (NOT canonical) |
| **revision** | explicit save | per-post, server-side | history navigation, rollback |
| **published canonical** | save-to-publish flow | the POST itself | authoritative everywhere |

Autosave and revisions create **branching authority paths** that
the user can navigate / restore from. Other clients see only the
canonical published state; the autosave / revision graph is
client-private (autosave) or post-private (revisions visible
through the UI).

This is a **temporal authority graph**, not just a save log.

### 4. Conflict scenarios — competing authority negotiation

Multiple paths can produce conflict:

```
scenario A — Server-newer:
   Client edits at T1
   Another client / process saves at T2 (T2 > T1)
   This client saves at T3
   → server-version is from T2; client's edit buffer is from T1
   → conflict: client overwrites or merges?
   ⚠ verification-needed: WP's default behavior
     (likely overwrites with warning; sophisticated handling
     requires custom UI)

scenario B — Network failure:
   Save dispatched
   Network fails mid-request
   Server may or may not have committed
   → client cannot determine state without re-reading
   → edit buffer preserved; user retries; risk of double-write

scenario C — Capability change:
   User loses edit capability mid-session
   Save attempt rejected by REST permission_callback
   → UI shows error; edit buffer preserved
   → user cannot recover except by saving manually elsewhere

scenario D — Validation rejection:
   Edit produces value rejected by register_meta callback
   Save partially succeeds (other fields) or wholly rejects
   → reconciliation must surface which fields succeeded
```

Conflict resolution is **structural, not exceptional** — any
async-edit system encounters these. Default WordPress behavior
favors simplicity over sophistication; complex resolution is
plugin/theme territory.

### 5. Capability enforcement boundaries — when permissions are checked

Capability checks happen at **specific boundaries**, not
continuously:

| boundary | check | failure result |
|---|---|---|
| edit time (client) | none enforced; UI may hide controls based on hints | UI hint only, NOT authoritative |
| dispatch time (client) | none enforced | dispatch proceeds optimistically |
| REST request reception | `permission_callback` of route | 403 response |
| pre-save processing | `current_user_can()` calls in save handlers | varies (filter / abort / partial) |
| meta save | `auth_callback` of register_meta | meta rejected silently or surfaced |
| revision creation | revision capability hooks | revision skipped / error |

Edit-time capability is a **UI hint**, NOT enforcement.
Authoritative enforcement happens server-side at persistence
boundaries. Implication: relying on hidden UI controls for
security is wrong; server-side checks are the only authority.

### 6. Undo / redo — local temporal authority graph

Editor undo/redo operates on the **edit buffer** layer ONLY:

- Each `editEntityRecord` dispatch is tracked in the editor's
  undo stack.
- Undo reverts the edit buffer to a prior state.
- Save flushes whatever is currently in the edit buffer; undo
  operations BEFORE save are reflected in the saved state.
- Undo operations do NOT cross the persistence boundary —
  cannot undo a saved change via editor undo.

This makes undo/redo a **local temporal authority graph** atop
the edit buffer. Once changes persist, undo's authority ends;
recovery falls back to revisions (server-side temporal graph).

### What this is NOT

- NOT real-time collaboration. Multi-client editing requires
  conflict resolution; Gutenberg's default is last-write-wins.
- NOT transactional in the database sense. Multi-entity saves
  are not atomic across entities.
- NOT push-based change notification. Other clients learn of
  changes by re-fetching or auto-refresh, not by server push.
- NOT immune to network failure. Optimistic UI may show success
  even when persistence ultimately fails.
- NOT a queue or undo log on the server. Server only stores
  current state + revisions; no "operation log" to replay.

## REQUIRES

- @wordpress/core-data + @wordpress/editor packages
  (block editor environment).
- REST endpoints with permission_callback configured.
- For each entity type: WordPress capability registration
  (e.g., `edit_post`, `edit_others_posts`).
- For meta: register_meta with `show_in_rest: true` AND
  appropriate auth_callback / sanitize_callback.
- Network availability for actual persistence.
- ⚠ Specific autosave timing, retry policies, conflict detection
  mechanisms — verification-needed.

## INVARIANTS

### 1. Save is NOT a terminal event — it is authority synchronization

The most load-bearing invariant of this chunk:

> "Save" is NOT a transaction completing.
> "Save" is a **multi-stage authority synchronization attempt**
> spanning client buffer / network / server processing / DB /
> revision graph / response / client reconciliation.

Reading "save" as a single event misses that:
- Optimistic UI may show success before server confirms.
- Server may partially commit (some fields, not others).
- Network failure can leave state ambiguous.
- Revisions create branches in temporal authority.
- Other clients learn of changes asynchronously (or not at all
  until they refetch).

The UI affordance "Save button" hides the complexity of authority
synchronization underneath.

### 2. Persistence is authority reconciliation protocol, not storage operation

Phrasing matters:

| framing | implications |
|---|---|
| "Save = storage operation" | atomic, terminal, deterministic, single-actor |
| **"Save = authority reconciliation"** | multi-stage, multi-actor, async, may fail / partial / conflict |

Gutenberg's persistence is the second framing. It involves:
- Reconciling client edit buffer with server canonical state.
- Negotiating capability (server gates).
- Sanitizing and validating (filter pipeline).
- Snapshot creation (revision).
- Reconciliation back to client.

Each step is a reconciliation point where authority can be
challenged, modified, or rejected. The protocol is the SUM of
these reconciliation points.

### 3. Three concurrent authority layers operate simultaneously

Gutenberg runs three authority layers in parallel:
- **Persisted** (DB canonical, global scope, slow to change)
- **Edited** (client-local draft buffer, single-session scope,
  fast to change)
- **Transient** (UI/runtime state, single-session scope,
  ephemeral)

These are NOT pipeline stages or hierarchical layers — they are
**concurrent state spaces**. At any moment, all three coexist;
their relationships are:
- Edited overlays persisted (when reading via
  getEditedEntityRecord).
- Persisted updates from save flushing edited.
- Transient is independent (selection, focus do NOT participate
  in persistence).

The editor is structurally a **multi-layer authority orchestrator**.

### 4. Edit graph exists independently of persistence

A user can:
- Make 50 edits without saving any.
- Discard all edits without persisting.
- Reload the page and lose unsaved edits.
- See edits reflected in the editor UI without server interaction.

This means the **edit graph is a first-class state space**, not
a "pending changes" buffer. Components reading via
`getEditedEntityRecord` see the edit graph; components reading
via `getEntityRecord` see persisted state. Both are valid views
of authority — different layers, different consumers.

This is fundamentally different from form-based CMS (PHP
WordPress admin pre-Gutenberg, traditional HTML forms) where:
- form fields exist transiently in DOM
- submit triggers immediate persistence
- no client-side edit graph independent of persistence

Gutenberg's edit graph independence is structural to its
reactive editor model.

### 5. Autosave + revisions are temporal authority snapshots, not backups

Autosave and revisions create **points in temporal authority
graph** rather than backup copies:

- Autosave: per-user-per-post snapshot for crash recovery,
  separate from canonical post state.
- Revisions: per-post historical snapshots accessible via
  history navigation.

Their semantics are NOT "save the same thing redundantly":
- Autosave is RECOVERY-only (visible in editor's "post recovery"
  flow, not on the published frontend).
- Revisions are HISTORY (navigable, restorable, comparable).

Both encode WordPress's deep cultural commitment to
**persistence-latency-tolerant authoring** — accepting that
authors think iteratively, mistakes happen, and editing is a
non-monotonic process.

### 6. Optimistic UI is structural, not optimization

When the editor displays "Saved" instantly while the actual
persistence completes asynchronously, this is NOT a UX
shortcut — it is **structural necessity**:

- Synchronous wait would freeze the editor for network
  duration.
- Editor must remain interactive during save.
- Other edits may be in-progress while a save is mid-flight.

Optimistic UI is the structural complement to async persistence.
Its risks (showing success before actual confirmation, masking
failure) are the structural cost. UI patterns (toast on
failure, "saving..." indicator, "unsaved changes" warning) are
mitigations within the optimistic model, not alternatives to
it.

### 7. Capability enforcement happens at persistence boundary, NOT at edit

UI controls may HIDE based on capability hints, but actual
enforcement is server-side at persistence:

- Edit-time UI hint: best-effort (not authoritative).
- REST permission_callback: authoritative enforcement.
- save_post hook capability checks: authoritative enforcement.
- Per-meta auth_callback: authoritative enforcement.

This separation has implications:
- Hidden UI controls do NOT secure data. A user with browser
  console access can dispatch any edit; only server rejects.
- Capability changes mid-session do not retroactively undo
  edits; they only block future persistence.
- Optimistic UI may show success that server then rejects on
  capability grounds — UI must surface the failure.

### 8. Conflict resolution is necessary, not exceptional

Multi-client editing, multi-tab editing, async sources, server-
side cron processes: any of these can mutate an entity while
another client edits. Conflict is **the normal case at scale**,
not an edge condition.

WordPress's default conflict handling is **last-write-wins**:
- The most recent save's state becomes canonical.
- Earlier changes from other sources are silently overwritten
  (unless plugins implement merge / detection).

This is a deliberate design simplification. Plugins / themes
implementing collaborative editing must layer their own
detection and resolution on top of core-data's primitives.

⚠ The exact mechanics (revision ID checking, ETag use,
WordPress core's level of conflict awareness) are
implementation-specific and have varied across WP versions.

### 9. Persistence completion ≠ user-visible commit

A successful save may NOT mean "visible to readers":
- Draft post: persisted but not on frontend.
- Scheduled post: persisted with future publish date; appears
  later.
- Pending review: persisted but visibility-restricted.
- Trashed: persisted but excluded from queries.
- Private: persisted with visibility restrictions.
- Password-protected: persisted with reader-side gating.

The post lifecycle has its own state machine ON TOP of
persistence. "Save" persists the entity; what readers see is
governed by post status + visibility + scheduled time +
capability — independent dimensions.

This makes persistence **necessary but not sufficient** for
publication. The publication boundary is downstream of
persistence in the post lifecycle.

### 10. Gutenberg = delayed-authority synchronization runtime

The Phase 7 capstone framing this chunk completes:

> Gutenberg is not merely a reactive editor.
> It is a **delayed-authority synchronization runtime** —
> a system that operates three concurrent authority layers
> (persisted / edited / transient), reconciles them via async
> protocols, and exposes the reconciliation to consumers
> through reactive subscriptions.

This framing distinguishes Gutenberg from:
- Form-based CMS (synchronous form-submit-then-persist).
- Pure local-state UI (React app with no persistence concern).
- Real-time collaborative tools (push-based shared state).

Gutenberg sits in a specific niche: **single-author async
editing with multi-stage reconciliation, snapshot history,
and optimistic UI atop deferred persistence**. This niche has
deep WordPress historical roots (autosave / revisions /
drafts / preview) that the Gutenberg data-layer formalizes
as ontology.

## VERIFICATION NEEDED

`status: evolving` — persistence specifics evolve with each
WP version. Items requiring verification:

- Exact autosave timing (interval, blur trigger conditions,
  per-entity-type variations).
- Revision creation policy (every save? threshold-based? per
  entity type?).
- Server-side conflict detection: does WP core check revision
  IDs / timestamps before overwriting?
- ETag / If-Match header support in core-data REST integration.
- Optimistic UI behavior per editor surface — what shows
  immediately vs after confirmation?
- Multi-entity save atomicity (saving post + multiple meta:
  partial-success semantics).
- Failure recovery — automatic retry policies, exponential
  backoff, abort conditions.
- Browser tab synchronization — do multiple tabs editing the
  same entity see each other's edits?
- Page-reload behavior — what edits survive (autosave only)
  vs are lost.
- Plugin extension points for custom conflict resolution.
- Capability hint freshness — when does the editor learn that
  the user lost a capability mid-session?
- Trash / delete reconciliation — soft-delete vs hard-delete
  semantics in core-data.
- post lifecycle state machine — exact transitions on save
  for each from-to combination.

For practical decisions: prefer empirical observation
(network tab + Redux DevTools) over inferred behavior.

## ANTIPATTERNS

- ❌ Treating "Save" as instantaneous in user mental model.
  Async reconciliation has failure modes; UI must communicate
  pending / saving / saved / error states explicitly.
- ❌ Hiding UI controls instead of capability enforcement.
  UI hide = hint only; server REST permission is authoritative.
  Sensitive operations must check server-side regardless of UI.
- ❌ Assuming the edit buffer survives page reload. By default
  it does not; only autosave provides recovery.
- ❌ Using `apiFetch` to mutate entities directly. Bypasses
  edit buffer + optimistic UI + conflict-aware reconciliation
  + subscription propagation.
- ❌ Treating multi-entity saves as atomic. Partial success is
  possible; UI / business logic must handle inconsistent
  intermediate states.
- ❌ Assuming the most recent save necessarily reflects user
  intent. Last-write-wins means concurrent edits silently
  overwrite; conflict-aware UI requires plugin work.
- ❌ Putting business logic in client-side validation only.
  Server validates again at persistence; client validation is
  UX hint, not enforcement.
- ❌ Conflating publication status with persistence success.
  Save-as-draft persists but doesn't publish; UI must convey
  the difference.
- ❌ Storing edits to local storage as a "fix" for buffer-
  loss-on-reload. Fragile, complicates conflict, may store
  stale data; use autosave instead, which is server-side and
  WP-aware.
- ❌ Hooking into save_post for client-visible feedback.
  save_post is server-side; client learns of completion via
  REST response, not via PHP hook firing.
- ❌ Treating revisions as backups for arbitrary recovery.
  Revisions are entity-historical snapshots; not all entity
  types create revisions, retention is configurable.

## RELATED

- `data-layer.entity-resolution` — read substrate counterpart.
  This chunk is the write/reconciliation substrate. Together
  they form the complete data-layer authority graph (read +
  write).
- `block-authoring.block-json.bindings` — writable bindings
  flow through this layer's persistence pipeline. The
  `set_value_callback` of a writable source is invoked during
  save reconciliation.
- `block.dynamic-rendering` — rendered output reads persisted
  state via PHP. Persistence updates what the next render sees;
  the render-vs-persistence timing affects how edits appear
  on the frontend.
- (planned) `interactivity.runtime-state` — interactivity's
  reactive orchestration grammar must respect persistence
  semantics (optimistic vs confirmed, conflict, capability).
  Reactive UI cannot ignore the reconciliation lifecycle.
- (planned) `data-layer.entity-types` — per-entity-type
  persistence semantics (post lifecycle states, template hierarchy
  interaction, user updates capability gates) deserve dedicated
  treatment beyond this substrate chunk.
- (planned) `plugin-dev.rest-permission` — permission_callback
  patterns for custom REST endpoints; the authoritative gate
  for persistence access.
- (planned) `plugin-dev.register-meta` — meta sanitization /
  validation / auth callbacks at persistence boundary.

## META

**Phase 7 third major chunk; data-layer substrate complete:**

```
data-layer substrate (now complete):
   data-layer.entity-resolution  → read substrate (subscriptions, resolvers, store)
   data-layer.persistence        → write substrate (reconciliation, conflict, durability)
   ↓
   data-layer is now SEALED for substrate purposes.
   Per-entity-type behaviors (entity-types) are downstream
   specialization, not substrate.
```

**Phase 7 substrate triangle now in place:**

```
   bindings              → attribute attachment surface
   data-layer.entity-res → read substrate
   data-layer.persistence → write substrate
   ↓
   (NEXT) interactivity   → reactive orchestration grammar
                            (built atop the substrate)
```

**KB-level framing extension (Phase 7 capstone):**

> Gutenberg's compiler architecture (Phases 1-6) handled
> visual realization through schema-driven static / compile-time
> authority.
>
> Gutenberg's stateful runtime (Phase 7) handles authority
> orchestration through asynchronous reconciliation between
> three concurrent authority layers (persisted / edited /
> transient).
>
> Together: Gutenberg is a **two-axis system** — schema-driven
> visual compiler + delayed-authority synchronization runtime.

**Cultural / historical observation:**

WordPress's persistence-latency-tolerant philosophy
(autosave / revisions / drafts / preview / scheduled
publishing / undo) is foundational, not incidental. Gutenberg's
data-layer formalizes this culture into runtime ontology:

- Autosave = "your work is safer than your discipline"
- Revisions = "history matters, not just current state"
- Drafts = "publication is a separate decision from persistence"
- Preview = "you can see before others see"
- Scheduled = "you can persist now, publish later"
- Undo = "mistakes are recoverable, locally and reversibly"

These were UX features in classic WordPress. In Gutenberg
data-layer they are **ontology**: explicit authority layers
with explicit reconciliation protocols.

**DSL extension applied:**

VERIFICATION NEEDED + META sections, per the runtime /
implementation-derived applicability rule. Persistence is
implementation-heavy (network protocols, server-side hooks,
revision policies, conflict semantics) — verification-needed
is structural to this layer's epistemic character.

**Status `evolving`** — persistence APIs are mature but
specific behaviors evolve (autosave intervals, revision
policies, REST integration improvements per WP version).

**Anticipated next chunks:**

1. **`interactivity.{first chunk}`** — Phase 7 substrate is now
   complete; interactivity can enter as the reactive
   orchestration grammar layer with concrete substrate
   underneath. Recommended first chunks:
   - `interactivity.directive-protocol` (data-wp-* directive
     grammar — the syntactic surface)
   - `interactivity.runtime-state` (store / context / actions
     ontology — the state model)
   - `interactivity.hydration` (server-initial → client-mount
     boundary)

2. **`data-layer.entity-types`** — per-entity-type
   specialization. Could be deferred until needed; substrate
   is now complete enough for interactivity entry.

3. **`block.dynamic-rendering` retro patch** — connect PHP
   entity reads to JS data-layer entity reads + persistence
   pipeline. Light retro work, high coherence payoff.

Recommended sequence: (1) interactivity entry — substrate
preparation is the explicit goal achieved; entering interactivity
now realizes that preparation. (2) Retro patches as bounded
contexts mature. (3) Per-type entity chunks on demand.
