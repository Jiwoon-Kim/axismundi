# Icon Button Runtime Prototype Audit — v3.4.3

> Bucket: D (theme interaction, icon font track)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (theme interaction layer), §3 (Bucket D), §5 (forbidden ancestor list — locked with `BEER-CSS-INTAKE.md` §1)
>
> First end-to-end test of the inline-SVG → Material Symbols conversion
> pattern established by `v3.4.2 ICON-SYSTEM-AUDIT.md`. Scope:
> `ax-icon-button` only (40 instances in `style-guide.html`), plus the
> four CSS hardening rules from `ICON-FONT-POLICY.md §Required CSS
> hardening` added to `lab/stylesheets/icons.css §1`.
>
> Authored at v3.4.3.

## TL;DR

```
40 ax-icon-button inline SVGs   → Material Symbols glyph spans
icons.css §1 base class         → +4 hardening CSS rules
style-guide.html size           186,659 B → 182,483 B (-4,176 B, -2.2%)
Total inline SVG in file        146 → 106  (40 removed)
ax-icon-button blocks with SVG  40 → 0    (all converted)
ax-icon-button blocks with .material-symbols-rounded  0 → 40
```

Scope deliberately stops at `ax-icon-button`. FAB / chrome / list / menu /
text-field icon conversions are queued for later releases per
`INLINE-SVG-INVENTORY.md §Conversion ordering` — they need independent
visual QA passes because their size contexts, motion states, and layout
constraints differ.

## What this release does

1. **Implements the 4 hardening CSS rules** in `lab/stylesheets/icons.css §1`
   (`user-select: none`, `-webkit-user-select: none`, `-webkit-user-drag: none`, `pointer-events: none`) per the contract in `modules/icon-system/docs/ICON-FONT-POLICY.md`. The rules were specified in v3.4.2 but not added to CSS until they could be tested against a real production-scale component sample — that is, this release.
2. **Converts all 40 `ax-icon-button` instances** in `style-guide.html` from inline `<svg>` to `<span class="material-symbols-rounded">`. Per-button conversion preserves the parent button's class list, type, and `aria-label`; only the inner SVG element is replaced.
3. **Locks the mapping** between aria-labels and Material Symbols ligature names as an explicit 30-entry table (below). The mapping is the audit trail for what was done; if a glyph ever needs to change, the corresponding aria-label is the lookup key.

## What this release does NOT do

- Does not touch `ax-fab`, `ax-button` leading icons, `chip`, `search-bar`, `ax-list`, `ax-menu`, `text-field`, `ax-checkbox`, or `ax-progress`. Those have separate conversion windows and visual QA requirements.
- Does not modify the canonical `ax-icon-button` CSS (`components.css §G` or wherever icon button is defined). The conversion is purely an HTML-level replacement; the existing CSS rules continue to position whatever child element sits inside `.ax-icon-button`.
- Does not add inline-SVG fallback for chrome glyphs. Per `ICON-FONT-POLICY.md §Fallback policy`, this is optional and reserved for surfaces where the icon's absence would make the control's purpose unrecoverable. `ax-icon-button` already has `aria-label` on the parent, so a font-loading failure degrades the visual but not the function.
- Does not delete or modify the inventory in `INLINE-SVG-INVENTORY.md`. The inventory snapshot remains the pre-conversion baseline; after this release the inventory is partial-history rather than current-state. A v3.4.x follow-up can re-run the inventory script to capture a fresh count.

## Mapping table (aria-label → Material Symbols glyph)

30 distinct aria-labels covered all 40 button instances. The mapping is
the v3.4.3 source of truth for what each button's glyph represents.

| Aria-label | Material Symbols name | Count |
|---|---|---:|
| 이전 달 | `chevron_left` | 1 |
| 다음 달 | `chevron_right` | 1 |
| Add | `add` | 2 |
| Favorite | `favorite` | 2 |
| Like | `favorite` | 2 |
| Share | `share` | 4 |
| More | `more_vert` | 3 |
| Bookmark | `bookmark` | 1 |
| Star | `star` | 1 |
| Pin | `push_pin` | 1 |
| Bold | `format_bold` | 1 |
| Italic | `format_italic` | 1 |
| Underline | `format_underlined` | 1 |
| Edit | `edit` | 1 |
| Copy | `content_copy` | 1 |
| Delete | `delete` | 1 |
| Comment | `chat_bubble` | 1 |
| Repost | `repeat` | 1 |
| Notifications (unread) | `notifications` | 1 |
| Notifications (3) | `notifications` | 1 |
| Messages | `mail` | 1 |
| Messages (12) | `mail` | 1 |
| Messages (99 plus) | `mail` | 1 |
| Back | `arrow_back` | 2 |
| Search | `search` | 1 |
| Menu | `menu` | 1 |
| Settings | `settings` | 1 |
| Clear | `close` | 1 |
| Close | `close` | 2 |
| Voice search | `mic` | 1 |
| **TOTAL** | | **40** |

### Notes on the mapping

- **`favorite` for both "Favorite" and "Like"**: Material Symbols has a single heart glyph; semantic distinction lives in the `aria-label`, not the visual.
- **`more_vert` for "More"**: chosen over `more_horiz` because the original SVG path had a vertical stack (`<circle cx="12" cy="5">` → `<circle cx="12" cy="12">` → `<circle cx="12" cy="19">`). M3 spec uses `more_vert` for the canonical "overflow menu" pattern.
- **`mail` for all "Messages*" variants**: Material Symbols has `mail`, `mark_email_unread`, `markunread_mailbox`. Chose `mail` as the baseline and let the badge counter overlay (the "(12)", "(99 plus)" part of the aria-label) carry the unread-count semantics. A future release could swap to `mark_email_unread` for the unread-count variants if visual distinction becomes important.
- **`notifications` for both "(unread)" and "(3)"**: same logic as Messages — the parent button's aria-label discloses the count, and a separate badge component overlays. Material Symbols also offers `notifications_active` and `notifications_unread` for differentiation; not used in this baseline pass.
- **`close` for both "Clear" and "Close"**: same M3 X-glyph serves both UX roles. "Clear" is the text-field clear button; "Close" is the dialog/modal close. The aria-label disambiguates for assistive tech.
- **Korean preserved on aria-label**: `이전 달` / `다음 달` remain as Korean in the button's `aria-label`; the glyph itself is the universal `chevron_left` / `chevron_right`. The Korean-first authoring respected per `ICON-PICKER-UX.md §Korean-first authoring`.

## Hardening CSS rules added

Added to `lab/stylesheets/icons.css §1` (`.material-symbols-rounded` base class), with a 25-line rationale comment block referencing `modules/icon-system/docs/ICON-FONT-POLICY.md`:

```css
user-select: none;
-webkit-user-select: none;
-webkit-user-drag: none;
pointer-events: none;
```

Each rule's role recapped (see `ICON-FONT-POLICY.md §Why each hardening rule` for full text):

| Rule | Prevents |
|---|---|
| `user-select: none` | Double-click selection of the ligature text |
| `-webkit-user-select: none` | Safari/WebKit equivalent of the above |
| `-webkit-user-drag: none` | Safari force-touch / iOS long-press drag-as-text |
| `pointer-events: none` | Glyph intercepting click / focus / hover events that belong to the parent button |

## Promotion criteria — five-criterion check

Per charter §7 (equivalent: passing the five criteria ↔ no forbidden failure modes).

### 1. Works without JS

Material Symbols icon font is purely a CSS+font-file mechanism — no JavaScript is involved in glyph rendering. If `lab/stylesheets/icons.css` loads and the `@font-face` declaration in `fonts.css` resolves, glyphs render. If JS is disabled, nothing changes.

If the font itself fails to load (slow network, font CDN down), the ligature text ("search", "menu", "settings") becomes visible as literal text inside the icon button. The button still functions — `aria-label` on the parent button preserves the accessible name; the click still navigates. This is an allowed failure mode per charter §7 ("visual enhancement missing").

**Status: PASS by construction.**

### 2. M3 state-layer compliance

Icon buttons use the existing Pattern A state layer defined in `components.css §0` and integrated via `icons.css §5`. The conversion does not touch state-layer machinery — `.ax-icon-button::before` still renders the hover/focus/pressed tint. The glyph's `pointer-events: none` ensures pointer events pass through to the parent's state-layer trigger.

A subtle interaction: in pre-v3.4.3 markup, the inline `<svg>` had `aria-hidden="true"` and was a child of the button; pointer events would also pass through to the parent (SVG default doesn't intercept). Post-v3.4.3, the `<span class="material-symbols-rounded">` has `pointer-events: none` from the hardening rule. The behavior is identical at the user-facing level; only the underlying mechanism shifts from "SVG is naturally not a click target" to "CSS rule makes the span not a click target".

**Status: PASS** (verified by construction; visual confirmation pending visual QA).

### 3. `prefers-reduced-motion: reduce` honored

Icon font glyphs are static rendering — no animation in the glyph itself. The "expressive state" rule in `icons.css §6` (FILL axis change on hover/active) is a CSS `font-variation-settings` transition; if the user has `Reduce motion` enabled, the M3 spec-recommended approach is to make the FILL change instantaneous. The `icons.css §6` rule does this via the `expressive-state` mixin pattern (see file).

Parent components (`ax-icon-button` state layer, ripple lab module) handle their own reduced-motion via `@media (prefers-reduced-motion: reduce)` blocks in `components.css §0` and `modules/ripple/lab-ripple.css`. The icon font does not add new motion.

**Status: PASS** (no new motion introduced by this conversion).

### 4. Keyboard-operable

Tab still focuses each `ax-icon-button`. `Enter` and `Space` still activate. Focus-visible state layer still renders (Pattern A; CSS-only). The conversion did not modify any keyboard handling — the parent `<button>` element is unchanged. The hardening rule `pointer-events: none` only affects pointer interactions, not keyboard.

**Status: PASS.**

### 5. No leak into `.prose` / `post_content` / federated surfaces

This was already the rule in v3.2.1 (prose.css §12 enforces `font-family: inherit` for `[class*="material-symbols"]` inside `.prose`). The conversion increased the number of Material Symbols glyphs in the canonical styleguide, but the styleguide pages are not federated content — they are baseline visual catalog pages. Federated post content remains separately governed: no automatic mass-conversion will ever happen in `post_content`; if a content block uses an icon (per `ICON-PICKER-UX.md`), the per-block-type federation policy decides whether to ship SVG fallback or omit.

Negative-test demonstrated already in `modules/icon-system/icon-system-pattern.html §Forbidden scope`.

**Status: PASS by existing prose.css §12 enforcement; no new attack surface introduced.**

### Verdict summary

| Criterion | Status | Blocker? |
|---|---|---|
| 1. Works without JS | PASS by construction | No |
| 2. M3 state-layer compliance | PASS by construction (visual confirm pending) | No |
| 3. `prefers-reduced-motion` honored | PASS (no new motion) | No |
| 4. Keyboard-operable | PASS (no keyboard machinery touched) | No |
| 5. No `.prose` / federation leak | PASS by existing prose.css §12 enforcement | No |

**Verdict: PASS on all five criteria.** Pending manual visual QA on
`style-guide.html`'s icon-button sections (see checklist below) before
extending the conversion pattern to the next inventory category in
v3.4.4+.

## Visual QA checklist

Walk the canonical `style-guide.html` and verify each item. Test in
both light and dark theme via the theme switcher chips at the top
of the page.

### Size + alignment

| # | Check | Pass / Fail / N/A |
|---|---|---|
| 1.1 | Each `.ax-icon-button` glyph appears at 24px nominal size, centered within the button's 40px (or variant-specific) clickable area | |
| 1.2 | No vertical offset — the glyph's optical center matches the button's geometric center | |
| 1.3 | Korean aria-label buttons (이전 달 / 다음 달) render the chevron glyph identically to English-label buttons | |
| 1.4 | All four button variants (standard, filled, tonal, outlined) render the same glyph at the same size, with only the chrome differing | |

### Weight + stroke

| # | Check | Result |
|---|---|---|
| 2.1 | Glyph stroke weight matches surrounding M3 body text weight (default `wght: 400`) | |
| 2.2 | Glyphs do not appear noticeably thinner or thicker than the pre-conversion SVGs | |
| 2.3 | Glyph at `opsz: 24` (default) reads as visually balanced at the 24px icon size | |

### Filled state (hover / active / expressive)

| # | Check | Result |
|---|---|---|
| 3.1 | Hover over a filled icon button (`is-filled`) — state layer tint visible (no glyph change) | |
| 3.2 | Pressed icon button — pressed state layer + ripple (if ripple module loaded) | |
| 3.3 | Where `icons.css §6` "expressive state" rules apply, FILL axis transitions on hover (e.g. heart filled on Favorite/Like hover) | |
| 3.4 | Expressive state transition obeys `prefers-reduced-motion: reduce` (snap instead of animate) | |

### Dark mode + GRAD

| # | Check | Result |
|---|---|---|
| 4.1 | Switch to dark theme — glyphs render correctly with adjusted GRAD via `--md-grade` cascade | |
| 4.2 | Glyph weight reads visually balanced in dark mode (GRAD compensation working) | |
| 4.3 | Theme switch (light ↔ dark) — glyphs transition smoothly with the rest of the theme |  |

### Hardening (4 manual tests against any icon button)

| # | Check | Result |
|---|---|---|
| 5.1 | Double-click on a glyph — no text selection occurs (`user-select: none`) | |
| 5.2 | Click-and-drag on a glyph — no drag-out as text (`-webkit-user-drag: none` + `draggable="false"`) | |
| 5.3 | Right-click on a glyph — context menu shows page-level options, not "Search Google for 'menu'" / "Copy text" | |
| 5.4 | Click on the glyph area inside a button — the click registers on the parent button (state layer flashes, ripple fires if installed) — not on the glyph itself (`pointer-events: none`) | |

### Disabled state

| # | Check | Result |
|---|---|---|
| 6.1 | Disabled icon button — glyph at correct disabled opacity (per components.css disabled-state rule) | |
| 6.2 | Disabled glyph not selectable, draggable, or click-active | |

### Accessibility

| # | Check | Result |
|---|---|---|
| 7.1 | Screen reader announces parent button's `aria-label` (e.g. "검색, 버튼" or "Search, button") — does NOT announce the ligature text "search" | |
| 7.2 | Keyboard Tab traverses every icon button in document order | |
| 7.3 | `Enter` and `Space` on a focused icon button trigger its click handler | |
| 7.4 | Focus ring is visible on each icon button on `:focus-visible` (keyboard focus, not mouse click) | |

Any **Fail** here becomes a v3.4.3.x patch or a blocker on the v3.4.4
follow-up conversion (chip / search-bar / button leading icon).

## Cross-references

- v3.4.2 module umbrella: `modules/icon-system/docs/ICON-SYSTEM-AUDIT.md`
- Icon font hardening contract: `modules/icon-system/docs/ICON-FONT-POLICY.md`
- SVG icon track: `modules/icon-system/docs/SVG-ICON-POLICY.md`
- Conversion inventory + ordering: `modules/icon-system/docs/INLINE-SVG-INVENTORY.md`
- Future picker UX: `modules/icon-system/docs/ICON-PICKER-UX.md`
- Charter clauses cited: `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1, §3, §5, §7
- Prose-side scope enforcement (pre-existing): `lab/stylesheets/prose.css §12`

## Change log

- **v3.4.3 — initial prototype.** 40 `ax-icon-button` instances
  converted; 4 hardening CSS rules added to `icons.css §1`. Five-criterion
  promotion check PASSES on all rows (construction-level; manual visual
  QA via the checklist above pending). Mapping table (30 distinct
  aria-labels → Material Symbols ligature names) recorded as audit trail.
