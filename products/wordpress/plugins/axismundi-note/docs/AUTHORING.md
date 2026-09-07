# Note authoring model

Status: **limited block editor and Media Library attachment picker implemented;
context/reply block pending.** This reverses the increment 3 Classic Editor choice;
see "Rework" below.

## Jetpack Social Notes — reference only

Jetpack Social Notes is used as a **UX precedent only**. Its storage and federation
contracts are **not** adopted.

Pinned references (do not vendor the source into this repository):

- Feature docs: <https://jetpack.com/support/jetpack-social/sharing-social-notes/>
- `class-note.php` @ tag `9.0.3`:
  <https://github.com/Automattic/jetpack-social-plugin/blob/9.0.3/src/class-note.php>
- Analysis-basis commit: `e74c49f1e67432906e3e8f9c8e7275449347aeb3`
- Current release: Jetpack Social 9.0.3 (requires WordPress 6.9+).

### Adopt (UX)

- Title-less notes; the list table shows a body excerpt + date as a substitute title.
- A restricted block editor rather than the full post editor.
- A single + archive browsing UX.
- Native comment support.
- A dedicated Reply-To / Context block rather than free-form metadata entry.

### Do NOT adopt

- A general public CPT that is itself the canonical federated object. Axismundi keeps the
  private `ax_note` CPT with the fail-closed `?ax_note={uuid}` canonical id
  (see FEDERATION-ROUTE.md).
- Featured image as the note media model. Axismundi media stays in the `attachment`
  relationship, never a note body/featured-image model.
- Jetpack-connection / Publicize-centric lifecycle.
- A storage model without audience, tombstone, `contentMap`, or authorization — Axismundi
  keeps the increment 3 envelope.

## Axismundi Note authoring (target)

The Gutenberg mention completer already exists (built for Article). Reusing the block
editor avoids building a separate Classic/TinyMCE completer (the deferred #3b), which is
the decisive reason to move Note off the Classic Editor.

```
No title (list uses excerpt + date)
Restricted block palette:
  - core/paragraph
  - axismundi/context (or reply) block
  - embed / link blocks as needed
No media blocks — media is managed through the attachment field, not the body
Envelope fields (visibility / language / inReplyTo / context / quote target /
Who can quote / sensitive / CW / mentions)
```

New Notes default to `public` visibility and `anyone` automatic Quote approval. The
Federation panel edits the quote target URI and displays ledger-derived Quote status;
status is read-only and never becomes approval evidence by itself.
The default is persisted as authored state only when a new Note envelope is created.
Legacy rows without authored-policy evidence remain unset and therefore deny approval;
an upgrade must never turn absence into consent.

Block palette is restricted via `allowed_block_types_all` scoped to `ax_note`.

## Rework of committed increment 3

Increment 3 shipped a Classic Editor with a meta box. Moving to the limited block editor
requires:

- Remove the `use_block_editor_for_post_type` = false filter for `ax_note`.
- Add an `allowed_block_types_all` restriction for `ax_note`.
- Decide the envelope UI surface (see pending decisions).
- A new `axismundi/context` (reply/context) block.
- Reuse the existing block-editor mention completer (no new TinyMCE work).

The increment 3 storage substrate, envelope schema, and fail-closed save API are
unchanged; only the authoring surface changes.

## Resolved decisions (2026-07-19)

1. **Title — nullable, hidden by default.** The schema and transformer accept an authored
   title; the default UI hides it (an opt-in "Add title" comes later). The transformer
   emits `name` + `nameMap` **only when a title was authored**. `post_title` (WP native)
   holds the authored title; a Note with none stays title-less. The admin list shows an
   excerpt + date as a display-only substitute title — **never written to `post_title`** —
   so an authored title is always distinguishable from an admin fallback. A title does not
   make a Note an Article.
2. **Envelope UI — React document panel.** A `PluginDocumentSettingPanel` gathers every
   document-level field (audience, language, reply/context, quote policy, sensitive/CW,
   attachments, mentions, optional title). The editor exposes **one structured REST field**,
   `axismundi_note_envelope`, and the server `axismundi_note_save_envelope()` validates and
   stores it **atomically**. React is only the editing surface; validation and authority
   stay in the PHP envelope layer. The increment 3 meta box is removed once the panel
   reaches feature parity (two long-lived authoring surfaces would drift).
3. **Sequencing — authoring pivot first (increment 3.6), then 4a, then 4b.** Finalizing the
   authoring/envelope shape first avoids reworking the 4a transformer and the 4b view-model
   and template against a later-changing envelope (optional title, structured attachments,
   explicit recipients, reply/context, sensitivity, language snapshot). Nothing is released
   yet, so the transition cost is lowest now. The 3.6 pivot opens **no** public route or
   federation lifecycle — it finalizes the write/save contract only; 4a and 4b then read the
   same envelope as JSON-LD and HTML.

## Attachments (locked contract)

Attachments are **not** a new `wp_ax_notes` column. The React panel's attachment picker
reads and writes the Media Library's `wp_ax_media_relations` (subject post id model); Media
Library keeps ownership of the attachment relationship, rendition policy, alt text, and
sensitivity. The picker writes only the `axismundi-note-picker` provider rows and preserves
every other provider. It is available only in Media Library Independent mode; otherwise
the control and attachment key are omitted so existing rows remain untouched. Increment
3.6 adds no envelope schema column for media.

### Attachment picker UI (pending enhancement)

The 3.6b picker works but its selected-state presentation is minimal. A later UI pass
adopts the visual pattern from the official ActivityPub media-attachments PR as a **UX
reference only** (do not vendor): <https://github.com/Automattic/wordpress-activitypub/pull/2138>.

Adopt: a compact row per **selected** attachment (thumbnail or type icon for audio /
video / file, title, an `#order` badge, a remove control), a clear selected border, and
separated empty / loading states.

Do **not** adopt: regex-scanning the body for attachment candidates, an auto mode that
merges featured image / `post_parent` / body images, listing all selected *and* unselected
media in the sidebar, or an image-only assumption. The PR's review problem — reconciling
body data with a separate sidebar selection state — does not apply here: `wp_ax_media_relations`
is authoritative and the panel edits relations directly.

Note layout:

```
Attachments
├─ selected-only compact list
│  ├─ drag handle + up/down buttons (keyboard-accessible reorder)
│  ├─ thumbnail / type icon
│  ├─ title
│  ├─ sensitive / CW indicator (read-only; Media Library owns it)
│  ├─ #order
│  └─ remove
└─ Select media  (the standard WordPress Media Library modal browses unselected media)
```

`#order` is **federation-significant**, not cosmetic: it is the order the attachments are
emitted in the AS `attachment[]` array, so reorder must be a real, keyboard-accessible
operation (drag-and-drop alone is insufficient). With a 50-item cap, the sidebar lists
only the selected set; unselected browsing stays in the Media Library modal.

This UI pass is paired with the still-outstanding **editor runtime verification** of the
attachment picker (blocked only by the lack of a login session during automated review).

## Increment 3.6 scope (authoring pivot)

- Remove the `use_block_editor_for_post_type` = false filter; restrict the palette with
  `allowed_block_types_all` scoped to `ax_note` (core/paragraph + an `axismundi/context`
  reply/context block + embed/link; no media blocks).
- New `axismundi/context` block for `inReplyTo` / `context`.
- React `PluginDocumentSettingPanel` over a single `axismundi_note_envelope` REST field;
  server-side `axismundi_note_save_envelope()` atomic validate + store.
- Reuse the existing block-editor mention completer; add the explicit-recipient list for
  Mentioned-only.
- Attachment picker → Media Library relations.
- Nullable title accepted by schema + transformer; UI hidden by default.
- Remove the increment 3 meta box after parity.
- 4a transformer revised to emit `name` / `nameMap` for an authored title.

## Editor tooling (locked)

The repository has **no JS build pipeline** (no `package.json`, no `wp-scripts`); the
existing Article mention completer is plain runtime JS. Increment 3.6 keeps this
convention — a `wp-scripts` build would be a larger change than 3.6 itself and is
revisited (per plugin) only when UI complexity genuinely demands it.

- No JSX, no bundler, no `node_modules`. Only public `wp.*` globals
  (`wp.element.createElement`, `wp.plugins`, `wp.editor`/`wp.editPost`, `wp.components`,
  `wp.blocks`, `wp.data`, `wp.apiFetch`).
- One responsibility per file; no single mega-script:

```
assets/editor/
├─ envelope-panel.js     PluginDocumentSettingPanel over axismundi_note_envelope
├─ context-block.js      axismundi/context block registration
├─ mention-completer.js  reused block-editor Actor mention completer
└─ editor-helpers.js     shared apiFetch / normalization
```

- PHP declares every script dependency (`wp-element`, `wp-plugins`, `wp-editor`,
  `wp-components`, `wp-data`, `wp-api-fetch`, …) at enqueue time.
- All envelope validation and authority stay server-side in `axismundi_note_save_envelope()`;
  the REST field is the only write path. React is presentation only.
