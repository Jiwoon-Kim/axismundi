# Inline SVG Inventory — v3.4.2

> Bucket: D (theme interaction) + F (plugin candidate)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §3 (bucket reclassification)

> Hard-data inventory of inline SVG usage across the canonical Axismundi
> styleguide as of v3.4.1. This document is the substrate that future
> per-component icon-font conversions operate on. Each category gets a
> convert-now / convert-later / keep-SVG decision per `ICON-FONT-POLICY.md`
> and `SVG-ICON-POLICY.md`.

## Method

Inline SVGs counted via regex `<svg ` across the three canonical styleguide HTML files in the lab:

- `style-guide.html` — main component catalog (was 189,582 B at v3.3.1; current size includes Korean text-field section)
- `style-guide-blocks.html` — block bridge catalog
- `style-guide-prose.html` — prose / long-form catalog

Each SVG was attributed to the **nearest enclosing component class** found in a 1500-character lookback window. The script that produced this inventory is documented at the bottom; the numbers below are reproducible.

For reference, similar counts in non-canonical surfaces:

- `style-guide-benchmark.html` — 156 inline SVGs (frozen benchmark, will be pruned in v3.4.x / v3.5.0 benchmark prune pass)
- `modules/carousel/lab-carousel-pattern.html` — 2 inline SVGs (carousel-specific)
- `modules/ripple/lab-ripple-pattern.html` — 3 inline SVGs (allowlist hosts demo)
- `modules/search-expansion/lab-search-expansion-pattern.html` — 4 inline SVGs (allowlist + forbidden-scope demo)

Module-pattern SVGs are out of scope for conversion (they are
demonstration markup, not production chrome).

## Counts (canonical styleguide)

| Component category | Count | % of total | Bucket |
|---|---:|---:|---|
| `ax-icon-button` | 40 | 27.4% | D — convert to icon font |
| `ax-fab` (FAB + FAB menu + Extended FAB) | 35 | 24.0% | D — convert to icon font |
| `sg-*` (styleguide chrome — theme toggle, nav, sg utilities) | 21 | 14.4% | D — convert to icon font |
| `ax-button` (leading / trailing icons inside text buttons) | 10 | 6.8% | D — convert to icon font |
| `ax-list` (leading / trailing icons on list items) | 8 | 5.5% | D — convert to icon font |
| `ax-menu` (menu item leading icons) | 7 | 4.8% | D — convert to icon font |
| `text-field` (leading, trailing, error icons) | 7 | 4.8% | D — convert to icon font |
| `ax-checkbox` (check / indeterminate / disabled glyphs) | 7 | 4.8% | **Mixed — see below** |
| `ax-progress` (indeterminate / spinner / SVG circle-progress) | 5 | 3.4% | **Keep SVG** — see below |
| `chip` (chip leading icons) | 4 | 2.7% | D — convert to icon font |
| `search-bar` (leading search icon, trailing actions) | 1 | 0.7% | D — convert to icon font |
| uncategorized (1 SVG outside any recognized component) | 1 | 0.7% | Inspect case-by-case |
| **TOTAL** | **146** | 100% | |

## Per-category conversion advice

### `ax-icon-button` (40 SVGs) — convert in v3.4.3

Highest-volume category and the first conversion target in the v3.4.3
Icon Button Runtime Prototype. Conversion shape:

```html
<!-- Before (current styleguide) -->
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="설정">
  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z..." fill="currentColor"/>
  </svg>
</button>

<!-- After (v3.4.3 prototype) -->
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="설정">
  <span
    class="material-symbols-rounded notranslate"
    translate="no"
    aria-hidden="true"
    draggable="false"
  >
    settings
  </span>
</button>
```

The `ax-icon-button` CSS does not need to change — both `<svg>` and `<span>` work as visible-glyph children inside the button. The pattern is identical to icons.css §5 (current `.ax-icon-button` integration rule).

### `ax-fab` (35 SVGs) — convert in v3.4.3

Same shape as `ax-icon-button`. FABs are M3-canonical surfaces for icon-font usage. The fact that FAB has 35 SVGs and `ax-icon-button` has 40 reflects the v0.4.0 / v1.0.0-rc1 buildout where FAB, FAB menu, and Extended FAB variants each got a full state-and-color-set matrix demonstration. Conversion will collapse identical SVG paths into single icon-font ligature names.

### `sg-*` (21 SVGs) — convert in v3.4.3

Styleguide chrome (theme switcher, nav arrows, section anchors, etc.). Convert alongside `ax-icon-button` since they share size context (24px). Watch for: theme-switcher icons need to match between the `data-theme-button="light/dark/auto"` states — Material Symbols has `light_mode`, `dark_mode`, and `brightness_auto` glyphs that map cleanly.

### `ax-button` (10 SVGs) — convert in v3.4.3 alongside ax-icon-button

These are leading / trailing icons inside text buttons (`<button class="ax-button is-filled"><svg>...</svg><span class="ax-button__label">...</span></button>`). The conversion is the same as `ax-icon-button` but inside a button that also has a text label. Size context is the same (24px or 20px depending on button size). Low risk.

### `ax-list` (8 SVGs) — convert in v3.4.4 or alongside v3.4.3

List items use leading icons heavily in M3 reference. The conversion follows the same shape. Lower priority than icon-button + FAB because the visual rhythm of a list is more sensitive to icon weight variance — wait until icon-button conversion is QA'd before extending to lists.

### `ax-menu` (7 SVGs) — convert when popover module lands (v3.4.3 or v3.4.4)

Menu item icons couple to the popover/menu module work. Best to convert as part of popover lab module's pattern rather than retroactively on the canonical styleguide. Defer.

### `text-field` (7 SVGs) — convert with care

Text field has three distinct icon slots: leading icon (search affordance, mail icon for email field, etc.), trailing icon (clear button, dropdown chevron, voice-input mic), error icon (warning glyph). Trailing-clear and error are particularly visually-sensitive (tight integration with grid layout from v3.3.1's text-field grid-row fix). Convert one slot at a time, starting with the error icon (it's the most static visually).

### `ax-checkbox` (7 SVGs) — **mixed: keep some, convert some**

Checkbox check-mark glyphs are typically inline SVG because they are part of the checkbox's own rendering — they animate (stroke-dashoffset transition), they morph (check → indeterminate → check), and they need to be `currentColor` precisely styled at small sizes (typically 16px or 18px, smaller than the M3 default 24px). Material Symbols has `check`, `check_box`, `check_indeterminate_small` etc., but the rendering at sub-24px optical size may not match the M3 reference checkbox precisely.

**Recommendation**: Keep checkbox check-mark glyphs as inline SVG (they are component-internal rendering, not chrome glyph usage). Inventory shows 7 SVGs in `ax-checkbox` — these are presumably the check, indeterminate, and disabled-state variants across multiple demo checkboxes. They stay.

### `ax-progress` (5 SVGs) — **keep SVG**

Progress indicators use SVG for the circular-progress arc rendering (`<circle>` with `stroke-dasharray` / `stroke-dashoffset` animation). This is geometric vector rendering, not a glyph lookup. Material Symbols does have a `progress_activity` glyph, but it cannot represent variable progress percentages — only a fixed "loading" indicator. Variable-progress arcs stay SVG.

**Recommendation**: Keep all 5 progress SVGs.

### `chip` (4 SVGs) — convert in v3.4.3 alongside ax-button

Chip leading icons follow the same shape as icon-button. Low volume, low risk. Roll into the v3.4.3 conversion pass.

### `search-bar` (1 SVG) — convert in v3.4.3

The single SVG is the leading search icon. The voice-search trailing icon already lives inside an `ax-icon-button` and is counted in that category. Convert together.

### Uncategorized (1 SVG) — inspect

The script flagged 1 SVG that did not match any recognized component class in its 1500-char lookback. Manually inspect when conversion work begins; likely a styleguide chrome element with a unique class name not in the keyword list.

## Conversion ordering

For the v3.4.3 Icon Button Runtime Prototype:

1. `ax-icon-button` (40) — first, validates the conversion shape on the highest-volume category
2. `chip` (4) — same shape, validates that the pattern works inside chip rather than icon-button host
3. `search-bar` (1) — same shape, leading-icon slot inside a label
4. `ax-button` (10) — text button with leading / trailing icon, more layout-sensitive
5. `ax-fab` (35) — FAB + FAB menu + Extended FAB; once `ax-icon-button` is QA'd, FAB is the same shape at larger size
6. `sg-*` (21) — styleguide chrome; convert last to keep the styleguide visually stable until the rest is QA'd

For v3.4.4 or later:

7. `text-field` slots (7) — error icon first (most static), then trailing-clear, then leading
8. `ax-list` (8) — wait for visual rhythm QA on lists with icon-font glyphs
9. `ax-menu` (7) — convert as part of popover module pattern

Keep as SVG (no conversion ever):

- `ax-checkbox` check-mark glyphs (7)
- `ax-progress` arc renderings (5)
- Future brand / social SVGs (when they get added — see `SVG-ICON-POLICY.md`)

## Audit script

For reproducibility, the inventory was produced by this Python snippet
(run against the lab tree):

```python
import re
from pathlib import Path
from collections import Counter, defaultdict

LAB = Path("products/reference-implementations/axismundi-lab")
SOURCES = ["style-guide.html", "style-guide-blocks.html", "style-guide-prose.html"]

COMPONENT_KEYWORDS = [
    "ax-icon-button", "ax-button", "chip", "ax-fab", "ax-list", "ax-menu",
    "ax-nav", "ax-tab", "ax-toolbar", "search-bar", "text-field",
    "ax-snackbar", "ax-tooltip", "ax-card", "ax-dialog", "ax-bottom-sheet",
    "ax-banner", "ax-segmented", "ax-checkbox", "ax-radio", "ax-switch",
    "ax-slider", "ax-progress", "ax-date-picker", "ax-time-picker",
    "ax-carousel", "sg-theme", "sg-",
]

results = defaultdict(list)
for src_name in SOURCES:
    content = (LAB / src_name).read_text()
    for m in re.finditer(r"<svg\b[^>]*>", content, re.IGNORECASE):
        svg_pos = m.start()
        prefix = content[max(0, svg_pos - 1500):svg_pos]
        category, last_pos = "uncategorized", -1
        for kw in COMPONENT_KEYWORDS:
            for cm in re.finditer(r'class="[^"]*\b' + re.escape(kw) + r'[^"]*"', prefix):
                if cm.start() > last_pos:
                    last_pos = cm.start()
                    category = kw
        results[category].append(src_name)

for category, sources in sorted(results.items(), key=lambda x: -len(x[1])):
    print(f"  {category:<22}  {len(sources):>4}")
```

Re-running the script after any styleguide edit will produce a fresh count.

## Change log

- **v3.4.2 — initial inventory.** 146 inline SVGs across 12 categories
  in the canonical styleguide; per-category convert / keep decisions
  per `ICON-FONT-POLICY.md` (chrome glyph → icon font) and
  `SVG-ICON-POLICY.md` (component-internal rendering / variable
  geometric data → keep SVG). v3.4.3 conversion order specified.
  Module-pattern SVGs noted as out of scope. Benchmark page (156
  SVGs) deferred to prune pass.
