# Icon System Audit — v3.4.2

> Bucket: D (theme interaction, icon font track) + F (plugin, picker/registry track)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1, §3, §4, §6

> First cross-cutting module under the v3.4.1 charter. Scope audit only —
> minimal implementation, mostly inventory + policy. The actual icon-button
> runtime prototype is a separate later release (v3.4.3 candidate); this
> release establishes the dual-engine design, the policy documents, and the
> impacted-component inventory so subsequent work has a single source of
> decisions to cite.
>
> Authored at v3.4.2.

## TL;DR

```
Material Symbols icon font = M3 chrome glyph engine        (Bucket D — theme runtime)
SVG icons                  = WordPress / editor / social /
                             brand / portable-content engine (Bucket F — plugin)
```

These are **not alternatives** — they are complementary tracks. The icon
system module is **dual-engine** by design. Picking one and abandoning the
other would either cripple M3 chrome (no variable-axis glyphs, no
size-density harmony) or break WordPress ecosystem interoperability (no
`@wordpress/icons` parity, no Social Icons block, no custom social SVG via
WP 6.9's `core/social-link` variation, no Icon Block plugin compatibility).

## Why this audit exists

By the end of v3.4.1, four upcoming work items (popover, TOC, date/time,
ActivityPub) and one cross-cutting concern (the icon layer itself) were
all queued. The icon layer is **cross-cutting** in a different way than
the others: it does not own a single component; it touches **every
chrome surface that currently renders an inline SVG**. Inventory below
shows 146 inline SVGs spread across 12 components in the canonical
styleguide alone, plus 156 more in benchmark and 9 in module pattern
pages. Touching the icon layer without an audit first would force the
same per-decision rework on each touched component.

The charter §3 bucket framework was authored partly with this audit in
mind. Icon system items distribute across two buckets, not one:

| Sub-layer | Bucket | Charter clause |
|---|---|---|
| Material Symbols icon font runtime (already in `lab/stylesheets/icons.css`) | **D** | §1 theme interaction; §4 theme can render baseline M3 glyph system |
| Icon font hardening (`notranslate`, `draggable="false"`, `user-select: none`, etc.) | **D** | §1 theme interaction enforcement |
| Block editor icon picker / sidebar axis controls / icon registry | **F** | §1 plugin; §4 plugin should host editor UI |
| SVG icons for `@wordpress/icons` / Social Icons / Icon Block / brand / portable content | **F** | §1 plugin; §6 federation portability |
| Inline SVG fallback for chrome glyphs when the icon font fails to load | **D** | §1 theme interaction; §7 frontier-theme allowed failure mode |

## What this release does

1. Establishes the cross-cutting **icon-system module** as a documentation
   surface — three policy files plus an inventory plus a picker UX
   sketch plus a small pattern page.
2. Codifies the **icon font hardening rules** that the existing
   `lab/stylesheets/icons.css` file's comments already reference but
   does not yet enforce in CSS. The hardening is a charter §5
   (forbidden-ancestor) and §6 (federation portability) enforcement
   mechanism on the icon font side.
3. Inventories the **146 inline SVGs in the canonical styleguide** by
   enclosing component (40 in `ax-icon-button`, 35 in `ax-fab`, 21 in
   styleguide chrome, 10 in `ax-button`, etc.). This is the substrate
   that future Material-Symbols-conversion work will operate on.
4. Specifies the **picker UX** — what belongs in a block toolbar item
   (quick insert / toggle) vs sidebar inspector (variable axis
   controls: FILL, weight, GRAD, optical size, label). This is the
   plugin-side contract; the theme provides only the rendering surface.
5. Names the **SVG-required cases** so future plugin work knows the
   minimum scope of an SVG track.

## What this release does NOT do

- Does not convert any inline SVG to Material Symbols. Conversion is
  per-component work scheduled for later (`v3.4.3 Icon Button Runtime
  Prototype` is the first candidate). Doing all 146 in one pass would
  destabilize visual rhythm before per-component QA passes finish.
- Does not implement the picker UI. Picker is plugin territory (Bucket
  F); a plugin extraction is scheduled for `axismundi-icons` after
  `axismundi-pilot` (v3.4.x) demonstrates the theme can host the icon
  font runtime cleanly without a plugin.
- Does not change `theme.json`, the icon registry shape (only sketches
  it), or any WordPress block registration.
- Does not modify the existing `lab/stylesheets/icons.css` infrastructure
  — that file's runtime rules and `@font-face` declaration in `fonts.css`
  remain authoritative. Hardening rules (the missing CSS-side enforcement
  of the comment-documented policy) get added in `v3.4.3` together with
  the icon button runtime prototype, where they become visually
  testable.

The pattern of "audit first, implement next" is the same pattern that
v3.3.3 used for Beer CSS intake (`BEER-CSS-INTAKE.md` came in the same
release as the first ripple extraction, but the policy was the
deliverable; ripple was the application). Here the policy is more
expansive (three docs + inventory + UX sketch) so the application is
deliberately deferred to the next release.

## Sibling documents in this module

| File | Purpose |
|---|---|
| `docs/ICON-SYSTEM-AUDIT.md` | this file — umbrella |
| `docs/ICON-FONT-POLICY.md` | Material Symbols hardening, axis defaults, theme-chrome scope |
| `docs/SVG-ICON-POLICY.md` | when SVG is required, sanitization, WordPress integration points |
| `docs/INLINE-SVG-INVENTORY.md` | the 146-SVG inventory + per-category conversion advice |
| `docs/ICON-PICKER-UX.md` | toolbar item + sidebar inspector UX sketch |
| `icon-system-pattern.html` | minimal pattern page — Material Symbols showcase + SVG-required examples + hardening demonstration |

`docs/ICON-FONT-POLICY.md` and `docs/SVG-ICON-POLICY.md` are the two
canonical references from which `v3.4.3 Icon Button Runtime Prototype`
and any future picker plugin will be built. `ICON-PICKER-UX.md` is
intentionally a sketch — the actual picker is plugin territory and will
be re-specified inside that plugin when it is extracted.

## Promotion criteria (per charter §7)

Icon system as a whole is too large to score against the five-criterion
check that single-component modules use. Each impacted component
(`ax-icon-button`, `ax-fab`, etc.) will score independently when its
inline-SVG-to-icon-font conversion is attempted. The criteria stay the
same:

1. Works without JS (icon font loads via CSS; SVG fallback ships inline)
2. M3 state-layer compliance (icon does not interfere with parent's `::before` layer)
3. `prefers-reduced-motion: reduce` honored (icon-system has no animation itself; surface components handle motion)
4. Keyboard-operable (icon is `aria-hidden`; parent control owns accessible name)
5. No leak into `.prose` / `post_content` / federated surfaces (charter §6; enforced by prose.css §12 + icon font hardening)

For this audit-only release, the equivalent check is policy-level:

- ✓ Icon font hardening rules are codified in `ICON-FONT-POLICY.md`
- ✓ SVG-required cases are named in `SVG-ICON-POLICY.md`
- ✓ Inventory of impacted components exists (`INLINE-SVG-INVENTORY.md`)
- ✓ Picker UX boundaries (toolbar vs sidebar, theme vs plugin) are documented (`ICON-PICKER-UX.md`)
- ✓ Charter-aligned bucket classification per sub-layer (this file)

## Lineage

External references:

- **Material Symbols** — Google Fonts variable icon font. ~2,500 icons, four axes (FILL, wght, GRAD, opsz), three styles (Rounded, Outlined, Sharp). Axismundi ships only Rounded by default; Outlined / Sharp would be plugin-added if needed. The font file is bundled at `core/design-systems/material3/assets/icons/material-symbols-rounded/`.
- **Figma Material Symbols plugin** — UX reference for the icon picker. Surfaces the four variable axes as draggable / numeric controls. Useful as a Figma-side analogue; the WordPress block editor equivalent is a different surface (block toolbar + sidebar inspector) but the same axis vocabulary applies.
- **`@wordpress/icons`** — Block editor's official icon library, used by the `Icon` component. New icons get added as SVG files into `src/library/` plus a `manifest.json` entry. This is the path that custom block variation icons (e.g. registering a new `core/social-link` variation per WP 6.9) follow. Lives entirely in SVG, not icon font.
- **Social Icons block** — WP core block that renders one icon per service via `core/social-link` block variations. Each variation has an `icon` value that accepts `<SVG><Path/></SVG>` JSX. SVG track, not font.
- **Icon Block plugin (NickDiego)** — Third-party WordPress plugin that adds a content-block surface for inserting an SVG icon into post content. Demonstrates that SVG is the content-portable icon path inside post body.

## Change log

- **v3.4.2 — initial draft.** Umbrella audit established. Dual-engine
  decision codified. Inventory of 146 inline SVGs across 12 components
  in the canonical styleguide captured. Policy documents (icon font
  hardening, SVG required cases) drafted. Picker UX boundaries sketched.
  Implementation deferred to v3.4.3 (icon button runtime prototype) and
  later (plugin extraction).
