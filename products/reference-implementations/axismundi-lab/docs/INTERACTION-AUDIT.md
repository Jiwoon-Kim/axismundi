# Interaction Audit — v3.2.2

> Audit of the `benchmark-interactions.css/js` layer for promotion to `axismundi-prototype/`. Each interaction is evaluated against the five lab promotion criteria. Promoted assets become part of the official static prototype; held assets remain in lab with documented blockers.

## Methodology

Each interaction is evaluated against:

1. **Works without JS** (or graceful fallback)
2. **M3 state layer compliance** (hover/focus/pressed via color-mix)
3. **`prefers-reduced-motion: reduce` honored**
4. **Keyboard-operable**
5. **No leak into `.prose` / `post_content` / federated surfaces**

Pass all five → **PROMOTE**. Fail any → **HOLD** with blocker.

## Audit results

### Promoted (5 components)

| Component | CSS lines | JS lines | Promotion rationale |
|---|---|---|---|
| **Ripple** | ~30 (CSS keyframes + position vars) | ~30 (event handlers) | Pure visual effect; CSS-only path exists. CSS animation respects `prefers-reduced-motion` via existing CSS rule. JS adds position calculation only. M3 motion spec; harmless fallback. |
| **Anchored popover menu** | ~50 | ~80 | Native `<dialog>` available as fallback. Click outside + Esc to dismiss = keyboard-operable. Theme chrome only (menu items, not post content). Aligns with M3 menu spec. |
| **Search bar focus expansion** | ~85 | ~50 | CSS-only animation; JS only manages aria attributes. Standard `<input type="search">` works without JS. Width transition is purely visual. |
| **Slider value chip** | ~60 | ~10 | `<input type="range">` is the underlying control; chip is decorative overlay. M3 expressive slider spec. Visible value via `<output>` is the proper accessible pattern. |
| **Material You morph slider/carousel** | ~250 | ~600 | Scroll-snap based; works without JS for basic scroll. JS adds momentum + viewport calculation. M3 Material You carousel pattern (multi-browse, hero, uncontained layouts). The carousel that prototype already has in `components.css` is a subset of this one. **Decision: merge advanced features into existing `.ax-carousel`**. |

### Held in lab (4 components)

| Component | Blocker | Promotion path |
|---|---|---|
| **Date picker (benchmark)** | Design not finalized; visual QA pending; locale formatting incomplete; mobile fallback strategy unclear | `lab/forms-date-time.html` for iteration. Promote when keyboard nav + locale + mobile pass. |
| **Time picker (benchmark)** | Same as date picker (paired components) | Same as date picker |
| **Tooltip (benchmark JS)** | Native `title` attribute exists; benchmark version adds positioning but redundant for most theme uses; not yet a core need | Keep in lab; consider promoting only if a theme component genuinely needs it |
| **Modal (benchmark JS variants)** | Native `<dialog>` is the right WP pattern; benchmark adds custom open/close that may conflict with WP's dialog handling | Keep in lab; replace with `<dialog>` integration when needed |

### Refined / corrected during audit

| Issue | Decision |
|---|---|
| **Slider track color** (prototype vs benchmark disagreed) | **Adopt benchmark choice**: `var(--md-sys-color-secondary-container)` for inactive track. M3 spec says inactive track is a *container surface*, not foreground. Prototype's `on-secondary-container` (foreground) was higher-contrast but wrong M3 role. Promote with this correction. |

## Promotion plan (CSS)

```
benchmark-interactions.css (929 lines)
                            ↓
                ┌───────────┴───────────┐
                ↓                       ↓
        ax-prototype                ax-lab
        (5 promoted)              (4 held)

components.css additions:
- §interaction-ripple        (~30 lines)
- §interaction-popover       (~50 lines, may already partially exist)
- §interaction-search-expand (~85 lines)
- §interaction-slider-chip   (~60 lines)
- §interaction-carousel-morph (~250 lines, merge into existing §carousel)

slider track color: change line 3893 + line 3925 of components.css
  from on-secondary-container to secondary-container

prose.css: no changes (icon scope rule from v3.2.1 unaffected)
```

## Promotion plan (JS)

```
benchmark-interactions.js (1457 lines)
                            ↓
                ┌───────────┴───────────┐
                ↓                       ↓
        ax-prototype                ax-lab
        (5 promoted)              (4 held)

theme.js additions (or new interactions.js):
- enableRipple()                  (~30 lines)
- enableAnchoredPopover()         (~80 lines, possibly use Popover API)
- enableSearchExpansion()         (~50 lines)
- enableSliderValueChip()         (~30 lines)
- enableMaterialYouCarousel()     (~600 lines, the largest)

Held in lab/scripts/lab-interactions.js:
- enableDatePicker(), enableTimePicker()
- enableTooltips() (benchmark variant)
- enableModals() (benchmark variants)
```

## Korean / English typography audit

Per audit task: review how mixed-script typography is handled in `base.css` and `prose.css`.

### Findings

The current implementation is **architecturally correct** and requires no changes:

```css
/* base.css §2 — body level */
body {
  word-break: keep-all;        /* Korean breaks at word boundaries */
  overflow-wrap: anywhere;     /* Latin can break anywhere if needed */
}

/* base.css §7 — body line-height */
:root { --base-line-height-cjk: 1.6; }
body { line-height: var(--base-line-height-cjk); }
```

This combination handles three scenarios correctly:

1. **Pure Korean**: `keep-all` keeps Korean syllable runs together; breaks happen at word/space boundaries
2. **Pure Latin**: standard breaking still works; `overflow-wrap: anywhere` doesn't activate unless overflow detected
3. **Mixed-script**: Korean text wraps naturally; Latin words that exceed available width can break safely

`prose.css` does NOT add Korean-specific rules because the body-level inheritance covers all child elements. This is correct — adding overrides in prose would risk inconsistency between long-form content and other surfaces.

### Recommendation (informational, no code change)

Add a comment in `prose.css` clarifying that Korean/CJK handling is intentionally inherited from body, not duplicated. This will save future contributors from "fixing" a non-bug.

## Outcome

After v3.2.2:

- **`prototype/components.css`**: +475 lines (5 promoted interactions, slider color correction)
- **`prototype/scripts/theme.js`** (or new `interactions.js`): +790 lines (5 promoted JS handlers)
- **`prototype/stylesheets/prose.css`**: +5 lines (informational comment on Korean handling)
- **`lab/`**: retains 4 unfinished components for ongoing iteration
- **`atlas/material/`**: 1 new rule possibly added (`material-you-carousel-spec.md`) capturing the M3 multi-browse + hero + uncontained pattern

## Future audit cycles

This audit is the first; subsequent audits will be needed when:

- Lab components reach promotion criteria
- New interaction patterns are explored
- M3 spec updates land
- WP Interactivity API maturity changes recommended patterns

Audit cadence: ad-hoc, triggered by promotion candidates. Each audit produces a new `INTERACTION-AUDIT-vN.md` in this directory.
