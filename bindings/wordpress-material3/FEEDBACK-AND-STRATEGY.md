# WordPress – Material 3 Binding: Feedback and Strategy

> This document is **strategic feedback** for the WordPress–Material 3 binding
> layer. It does NOT block v3.4.9 Chip Full Spec Module work.
>
> 이 문서는 WordPress–Material 3 바인딩 계층을 위한 전략 메모이며,
> v3.4.9 Chip Full Spec Module의 blocker가 아니다.
>
> Authored 2026-05-15 (post-v3.4.8 freeze, before v3.4.9 entry).
> Future binding work refers back to this document for rationale.

## §1 — Color picker concern and the bridge strategy

### The concern

WordPress's `theme.json` color palette UI / Global Styles inspector / color swatch preview is built around a single-color-per-slug assumption. If the runtime design system uses something more expressive — `light-dark()`, `color-mix()`, M3 tonal palette role pairs, or CSS variable chains pointing at sys tokens — the editor UI surfaces a **visible-but-non-functional control**: the swatch renders something, but changing it does not propagate to the actual design system runtime.

This is not just an annoyance; it is a UX anti-pattern with two distinct risks:

1. **User trust** — once a user discovers one control that "doesn't actually do anything", they begin to distrust every other control on the surface. The theme as a whole loses credibility.
2. **Review risk** — the WordPress.org theme review process is known to test color controls. "I changed primary to red and nothing happened" is a common rejection reason. The reviewer reads documentation only after clicking controls.

### Why this matters at Axismundi's level

Axismundi's design system pivot is to keep the M3 token graph (`ref → sys → comp`) as the single source of truth in `tokens.css`, and use `theme.json` as a thin Gutenberg compatibility layer. This pivot is correct, but it creates exactly the visible-but-non-functional control problem if a user opens Global Styles and finds color picker UI that does not reach the runtime.

### Two-mode resolution

```
Theme-only mode (default for the theme alone):
  settings.color.custom = false
  Lock Gutenberg color customization UI.
  Tell users explicitly that this theme protects its M3 token graph and
  that full color customization requires the M3 Interpreter Plugin.
  This is the safe-honest mode and the right WordPress.org submission default.

Interpreter Plugin active mode:
  settings.color.custom = true (or a custom M3 color panel replaces the
  default Gutenberg color UI).
  The plugin bridges Gutenberg's color preset slugs and the
  --md-sys-color-* tokens. Now visible controls really do propagate.
```

Both modes follow Principle 1 below: visible controls behave.

### Bridge stages

Three stages, designed to be implemented incrementally. v3.6.0 refined the
Stage 1 wording: it is a **static slug bridge**, not a claim that `theme.json`
hex values are the source of truth.

```
Stage 1 — Static slug bridge (theme-only mode)
  theme.json palette = static slugs registered for the editor UI.
  theme.json color values are registration / fallback values.
  tokens.css keeps the M3 graph as the source of truth:
    md-ref -> md-sys -> wp-preset projection.
  CSS bridge direction:
    --wp--preset--color--primary: var(--md-sys-color-primary)
  Outcome: stable, review-safe, but customization is limited.

Stage 2 — Preset bridge (v1 Interpreter Plugin)
  theme.json palette slugs registered.
  CSS / plugin runtime treats the WordPress preset as user input.
  Outcome: changing the WordPress preset slug propagates through plugin-owned
  M3 sys token generation.
  Limitation: changing one preset alone does not regenerate the M3 role family
  (on-primary, primary-container, on-primary-container, etc.).

Stage 3 — Semantic M3 bridge (v2 Interpreter Plugin)
  User chooses a seed color or a role color.
  Plugin generates M3 tonal palette (ref tier).
  Plugin computes light/dark role sets (sys tier).
  Plugin synchronizes Global Styles / editor CSS / frontend CSS.
  Outcome: a single color change becomes a coherent M3 theme change.
  Cost: depends on Material Color Utilities (HCT color space), which is
  non-trivial to ship in either JS or PHP.
```

### Circular reference warning

Do not bidirectionally bind:

```
--md-sys-color-primary: var(--wp--preset--color--primary);
--wp--preset--color--primary: var(--md-sys-color-primary);
```

CSS variables are not bidirectional. The bridge must pick a direction per mode:

- **Strict M3 mode**: M3 sys token → WP preset (M3 is upstream).
- **Customizable bridge mode**: WP preset / plugin value → M3 sys (Gutenberg UI is upstream, plugin computes the rest).

The two modes are mutually exclusive at any given moment; the plugin must declare which is active.

## §2 — Three-tier architecture (the actual binding shape)

The ontology-theme-pilot already uses this shape and it is correct:

```
theme.json
  = Gutenberg UI contract / preset slug registry / compatibility layer
  Purpose: tell the block editor what color/typography/spacing controls
  to show and what slugs to use. NOT the source of truth.

tokens.css
  = Material 3 token graph engine (ref → sys → comp)
  Purpose: the actual runtime design system. Single source of truth.

--wp--preset--color--*  (the bridge variables)
  = the contract surface between the two.
  Purpose: let CSS reference values that originate from theme.json's
  preset registry, so that Gutenberg-generated preset CSS variables
  resolve to Axismundi's M3 tokens.
```

The trap to avoid: putting too much into `theme.json`. Trying to express the full M3 graph in `theme.json` flattens the role/state model. Treat `theme.json` as a thin compatibility shell. Audit each new entry: is this here because Gutenberg needs to know about it, or because the design system runtime needs it? If only the design system needs it, it goes in `tokens.css`.

### v3.6.0 Pilot refinement

The v3.6.0 Pilot validated the theme-only default but also exposed the next
architecture layer:

```txt
md-ref     = primitive source (hex / px / rem / duration)
md-sys     = runtime semantic source (light/dark maps roles to ref)
wp-preset  = editor-facing semantic projection
wp-custom  = WordPress-managed internal token bridge
ax-comp    = component contract consuming md-sys / ax-comp values
```

For color, Strict M3 mode projects downstream:

```css
--md-sys-color-primary: var(--md-ref-palette-primary40);
--wp--preset--color--primary: var(--md-sys-color-primary);
```

For shape, state-layer, motion, elevation, and other non-picker values, use
`wp-custom` only where WordPress-managed override is needed. Otherwise component
CSS should consume `md-sys` / `ax-comp` directly.

The Pilot confirms `settings.color.custom = false` as the safe theme-only
default. Final BACKLOG #20 closure is deferred to v3.6.1 because dark-mode
sys-layer swapping and the `wp-preset` / `wp-custom` bridge still need a
cross-cutting token architecture pass.

## §3 — Interaction vs Component module taxonomy

The taxonomy itself lives in `lab/modules/README.md` (canonical home). This section records its rationale, for cross-reference from binding-strategy documents:

```
Interaction modules validate behavior.
Component modules expand baseline components into full-spec, measurement,
variant, and WordPress mapping surfaces.
```

For WordPress binding work specifically:

- **Component modules** are the natural home for the `CHIP-WP-MAPPING.md`, future `TEXT-FIELD-WP-MAPPING.md`, etc. — the mapping document is part of the component module's deliverables, not a separate binding-side artifact.
- **Interaction modules** generally do not need WP mapping docs (interaction is on the theme side; the WordPress block doesn't usually carry the interaction).

## §4 — Strict mode vs Interpreter mode separation

If both modes are supported by the same plugin, the plugin must:

1. Declare which mode is active and surface that to the user clearly.
2. Provide a switch (or document why the switch is one-way).
3. Disable the controls that the inactive mode does not support, so visible controls still behave.

This is the v3.5.0+ phase, not now. Recorded here so the decision is not re-discovered later.

## §5 — Design principle: Visible control must map to real runtime behavior

```
Visible control must map to real runtime behavior.
```

If a control is rendered on the page, it must take a real action, carry real state, or be visibly disabled with a stated reason. Anything else is a fake control — visible but non-functional — and that is the failure mode this entire memo is built around preventing.

This principle is hoisted into `lab/modules/README.md §Design principles` as the cross-module design rule. It originated here in the WordPress/M3 binding feedback, but applies to every module, every demo page, every audit doc's example section, and every future plugin surface.

The principle is **not** "use as few controls as possible". It is "don't render a control unless you can guarantee it behaves". Both extremes are acceptable: a fully-static visual specimen with `aria-hidden="true"` and a "visual specimen — not interactive" label is fine, and a fully-wired runtime is fine. The forbidden pattern is the middle: clickable visual that pretends to be a control.

## §6 — Impact on current v3.4.x work

| Memo item | v3.4.x impact |
|---|---|
| Visible control principle | **Adopted into `lab/modules/README.md` at v3.4.9 entry**. Applies to chip filter/input variants — must use native `<input type="checkbox">` / `<input type="radio">`, not fake `<button>` toggles. |
| `theme.json = contract / tokens.css = runtime` 3-tier | **Already matches current ontology-theme-pilot**. No change needed. |
| `settings.color.custom=false` default in theme-only mode | **Routed to BACKLOG #20** — confirm policy when ontology-theme-pilot is next reviewed. |
| `data-theme="auto"` explicit 3-state model | **Routed to BACKLOG #22** — paired with v3.5.0 Public Surface Reframe. |
| Interpreter Plugin separation (Stage 2 / Stage 3 bridge) | **Routed to BACKLOG #21** — v3.5.x+ milestone, not v3.4.x. |
| `light-dark()` experiment | **Deferred to v3.5.0+ Public Surface Reframe**. Pilot's explicit override model is more stable for WordPress until interpreter plugin lands. |
| `color-mix()` for state layers in component CSS | Currently allowed; no change. |
| Block Pattern = layout, InnerBlocks = nested composition | **Will inform Pilot Block Theme Probe** (already on ROADMAP `v3.4.x`). Profile-card-style composition decisions deferred until that probe. |
| Text field WordPress mapping ambiguity | **Already BACKLOG #17** (Text Input Corpus / Ontology Audit). |
| `m3Role` / `m3Variant` attribute on core blocks | **Speculative until validated in Stage 2 plugin**. Not assumed to work without testing. |

## §7 — v3.5.0+ Interpreter Plugin scope (preview)

When the binding strategy moves out of feedback and into implementation, the plugin scope is roughly:

```
Phase 1 — CSS token runtime + data-theme auto/light/dark + --wp--preset--color bridge
Phase 2 — Core block → M3 role mapping (className-based, no fragile attribute changes)
Phase 3 — PluginSidebar / InspectorControls for M3 role UI on selected blocks
Phase 4 — theme.json and m3-tokens.json synchronization
Phase 5 — Onbology / KB / LLM generation pipeline integration
```

Each phase is an independent shippable. The principle is: **extend Gutenberg's official extension surface, do not modify Gutenberg core**. The plugin reverse-engineers the extension surface (block filters, block supports, SlotFill, PluginSidebar, theme.json contract, Global Styles engine) — not the Gutenberg source tree.

This phasing is preview-only; the actual milestone breakdown will be authored when v3.5.x phase begins.

## §8 — Cross-references

- `lab/modules/README.md` — Module taxonomy + cross-module design principles (canonical home for the "Visible control" principle)
- `lab/docs/ARCHITECTURE-BOUNDARIES.md` — Charter §1 four-layer model, §4 theme-can/plugin-should, §5 forbidden ancestors
- `BACKLOG.md #17` — Text Input Corpus / Ontology Audit (paired)
- `BACKLOG.md #20` — Theme-only color customization policy (new at v3.4.9)
- `BACKLOG.md #21` — M3 Interpreter Plugin separation (new at v3.4.9)
- `BACKLOG.md #22` — Explicit `data-theme="auto"` model (new at v3.4.9)
- `bindings/wordpress-material3/README.md` — current binding documentation
- `bindings/wordpress-material3/binding_summary.md` — current binding map output

## §9 — Status

This document is **not a roadmap entry**. It is a strategic memo that informs future BACKLOG items and a v3.5.x+ Interpreter Plugin milestone.

The active v3.4.9 work (Chip Full Spec Module) adopts §5 (Visible control principle) into the module design rules. No other §1–§7 content is required by v3.4.9.

v3.6.0 update: the Axismundi Pilot validates the theme-only default and the
reverse mapping direction from WordPress core blocks to M3. It does not close
the full token architecture policy; v3.6.1 owns that ref/sys/preset/custom
refactor.
