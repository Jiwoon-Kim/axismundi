# Axismundi Media Library — Compatibility & Lifecycle

> Status: **Living specification. Phase 0 and Phase 1a are implemented.** Governs how the plugin coexists with core
> Attachment behavior and other plugins, and what activation / deactivation /
> uninstall may and may not do. See SPEC.md Invariant 5 and SECURITY.md §1.

## 1. Two relationship modes

```
● Core attached-to     (default on activation — behaves like stock WordPress)
○ Independent media    (opt-in)
```

**Activation alone changes nothing.** No data, `post_parent`, `post_status`, or
permalink is altered until the site owner opts into Independent mode. The plugin
is inert-by-default with respect to existing media.

## 2. Independent mode — new uploads

When Independent mode is ON, **new** Attachments get:

```
post_parent  = 0             (set BEFORE the INSERT via wp_insert_attachment_data)
post_author  = core-selected author  (= current owner; no owner meta)
visibility   = public        (via legacy fallback; not stamped at upload)
```

Ownership is `post_author` (DATA-MODEL.md §2.0); a later ownership transfer changes
`post_author`. `post_parent = 0` is set atomically before the insert so no earlier
`add_attachment` callback observes a stale parent.

Rationale: path-dependent behavior (parent kept in the editor, dropped in the
library) would make the *same file* behave differently by where it was uploaded.
So: uniform `post_parent = 0`. **Used-in tracking (the relation index) is Phase 3,
not Phase 1** — Phase 1 only stops binding new uploads to a parent; it records no
usage relations yet.

**Legacy Attachments** (uploaded before the plugin, or any without `_ax_media_*`
meta) are read via fallback and left untouched: owner = `post_author`, visibility
= legacy-public, `listed`/`searchable` = true (SECURITY.md §2.2). Independent mode
does not mutate their parent or policy meta, but it does apply the common query
canonical and legacy-public access guards while active. Parent removal and
explicit policy migration remain separate operations below.

## 3. Existing media — migration (NOT in 0.1.0)

Removing `post_parent` from **existing** Attachments is the most destructive,
least-reversible operation (themes/galleries/other plugins may rely on parent).

0.1.0 ships **scan + preview only**:

- Count and list the Attachments and relations that *would* change.
- Preserve the original parent as a `legacy_parent` relation **before** any
  future removal (Phase 3 relation index).
- No bulk parent mutation executes in 0.1.0.

The actual removal is a separate, explicitly-confirmed, rollback-capable step in
a later release — never an activation or mode-toggle side effect. `legacy_parent`
(origin post) and `used_in` (current references) are kept distinct.

## 4. `wp_attachment_pages_enabled`

Independent mode requires attachment pages enabled. Ownership uses the
`prev` + `set` + `owned` guard in **ROUTING.md §1.2**: restore the prior value
**only if the current value is still the one we wrote** (never clobber a change
another admin/plugin made), and **re-acquire ownership on reactivation**. Stated
in the activation UI; never a silent override.

## 5. Deactivation contract

```
- Data: NOT mutated. No parent restore, no status change, no meta deletion.
- Features: visibility guards, archives, custom permalinks, feeds → cease.
- Behavior: reverts to core WordPress Attachment handling.
- wp_attachment_pages_enabled: restored to `prev` ONLY if we still own it
  (current == our `set`); otherwise left as-is (§4 / ROUTING.md §1.2).
- Notice: NO post-deactivation admin notice is possible (plugin code no longer
  runs). Boundary text + private/protected count are shown WHILE ACTIVE — on the
  settings / disable-Independent-mode screen, and optionally as a pre-deactivation
  confirm on the Plugins-screen Deactivate link (SECURITY.md §1).
```

Re-activation restores the previous policy interpretation from the retained meta.
Per SECURITY.md §1, `private` semantics are **not** guaranteed while deactivated —
this is an accurate feature boundary, not a regression.

## 6. Uninstall (separate from deactivation)

Uninstall is distinct from deactivate:

```
- Default: DO NOT delete _ax_media_* meta, term meta, or provisional tables.
  (Data preservation — a reinstall recovers the full policy state.)
- Optional: an explicit "Delete all Axismundi Media data on uninstall" toggle
  (default OFF) for users who want a clean removal.
- Provide a "restore legacy parents" tool (uses legacy_parent relations) for
  users who migrated and want to revert before/after uninstall.
```

## 7. Interoperability notes

- **Other plugins reading `post_parent`**: Independent mode's `post_parent = 0`
  can change gallery/attachment-navigation behavior in themes/plugins that assume
  a parent. Core mode (default) is unaffected. Documented as a mode consequence.
- **Media-heavy plugins (WooCommerce, form uploads, optimizers)**: their managed
  directories are treated as read-only by the (Phase 6) Storage Browser and are
  never re-registered/exposed — see SECURITY.md and the Storage contracts.
- **Multisite**: out of scope for v0.1 (per-site uploads paths and network policy
  differ); revisit before any multisite claim.
- **FileBird (and similar media-folder plugins)**: a separate folder system on its
  own tables (`wp_fbv` / `wp_fbv_attachment_folder`), gated on `upload_files`. When
  both are active the two folder systems are **independent** — Axismundi's
  `ax_media_folder` taxonomy and FileBird's tables don't read each other. Document
  the coexistence; don't fight over the media modal. A **FileBird → ax_media_folder
  importer** (map `wp_fbv` rows to owner-scoped terms, respecting single-relation
  and per-attachment `edit_post`) is a **future compatibility item**, not Phase 2.

### 7.1 FileBird as UX benchmark (Phase 2)

Borrow FileBird's **interaction** model (sidebar tree, breadcrumb, direct/recursive
counts, search/sort/drag-and-drop, right-click actions, remembered folder,
upload-into-folder, edit-panel folder change, edited-image folder inheritance,
`All media = -1` / `Unfiled = 0`) — see PHASES.md Phase 2. Do **not** borrow its
storage (custom tables) or its permission model (`upload_files`-only). Axismundi
uses the taxonomy and gates moves on each attachment's `edit_post`.
