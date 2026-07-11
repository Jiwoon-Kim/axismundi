# Axismundi Media Library — Compatibility & Lifecycle

> Status: **Design draft (pre-code).** Governs how the plugin coexists with core
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
post_parent = 0        (regardless of upload path — media modal OR block editor)
_ax_media_owner_id     = uploader
_ax_media_visibility   = public   (see SPEC.md §4 defaults)
```

Rationale: path-dependent behavior (parent kept in the editor, dropped in the
library) would make the *same file* behave differently by where it was uploaded.
Uniform `post_parent = 0` + a **used-in relation** record (index lands Phase 3)
keeps "where is this used" stronger than core, without the single-parent model.

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

Independent mode requires attachment pages enabled (ROUTING.md §1.2). The plugin
**stores the prior option value** on enabling and **restores it** when
Independent mode is disabled or the plugin deactivates. Stated in the activation
UI; never a silent override.

## 5. Deactivation contract

```
- Data: NOT mutated. No parent restore, no status change, no meta deletion.
- Features: visibility guards, archives, custom permalinks, feeds → cease.
- Behavior: reverts to core WordPress Attachment handling.
- wp_attachment_pages_enabled: restored to the stored prior value.
- Notice: boundary warning shown; count of private/protected Attachments
  reported so the admin understands what stops being guarded.
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
