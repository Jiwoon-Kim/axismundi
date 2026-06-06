# Omphalos → Axismundi — migration principles (block-rooted vs prose fallback)

> **Purpose**: fix the classification rules learned in Omphalos so the Axismundi
> clean build inherits the *conclusions* without re-inheriting the *TT5 child-theme
> artifacts* that produced them. Omphalos is the lab that discovers "what belongs
> where"; Axismundi encodes that as a clean structure from an empty
> `Create Block Theme` base.
> **목적**: Omphalos에서 실측으로 얻은 분류 규칙을 고정해, Axismundi clean build가
> *결론*만 상속하고 그 결론을 만든 *TT5 child 아티팩트*는 상속하지 않게 한다.
> **Date**: 2026-06-06 · WP 7.0 · M3 Expressive.

---

## §1 — Two themes, two roles

```txt
Omphalos    TT5 child · Axismundi pilot · frontier lab.
            Proves WordPress core-block ↔ Google MD3 component binding.
            Experiments with Gutenberg beta blocks / Jetpack / ActivityPub.
            Lives ON TOP of TT5, so prose + core-block CSS + theme.json +
            parent templates overlap — boundaries look blurry here BY NATURE.

Axismundi   Clean build from an empty Create Block Theme base, Global Styles first.
            The real theme: extends WordPress CMS into an ActivityPub-based
            SOCIAL CMS. Inherits Omphalos's binding/token/classification
            conclusions, but drops TT5 inheritance and prose-leak.
```

Omphalos blur is not a defect — it is the experiment. The job here is to emit
*classification data*; the job in Axismundi is to build on that data with no blur.

Omphalos의 경계 흐림은 결함이 아니라 실험 그 자체다. 여기서는 분류 데이터를 생산하고,
Axismundi에서는 그 데이터를 전제로 흐림 없이 빌드한다.

---

## §2 — The core rule: block-rooted vs prose fallback

The recurring confusion ("is `table` prose-rooted or block-rooted?") is a
*child-theme layering artifact*. On TT5-child, two CSS sources both reach a raw
element — prose (`.wp-block-post-content table`) and the block
(`.wp-block-table`) — so "the root" looks ambiguous. It is not.

**Normative rule / 규칙:**

```txt
Block class / block supports / theme.json
    = FIRST-CLASS source for authored, structured content.
    = the binding surface for tokens and M3.

prose (raw element selectors under post-content)
    = FALLBACK only, for raw / imported / federated HTML that has NO block wrapper.
    = never the "true" style of a block; a block always wins by being a block.
```

- 저작·구조화된 콘텐츠의 1차 스타일 소스는 **block class / supports / theme.json**.
- prose는 블록 래퍼가 없는 **raw/imported/federated HTML의 폴백**일 뿐, 블록의 근본 스타일이 아니다.

Test to apply: *does this element have a block wrapper (`<figure class="wp-block-*">`,
`.wp-block-*`)?* If yes → bind the block class; treat any prose rule reaching it as
leak to be reset. If no → it is fallback content and prose is the correct owner.

---

## §3 — prose's real home is the federated/remote renderer

In a block-first social CMS, authored post bodies are **entirely blocks**, so a
broad prose layer has almost nothing legitimate to style there. The content that
genuinely lacks a block schema is **remote/federated** — ActivityPub `note` /
`article` / object HTML pulled in from elsewhere.

```txt
Authored content (local)     = blocks → block-class binding, no prose needed.
Remote / federated content   = raw HTML, no block schema → prose / object renderer.
                               isolate in a scope, e.g. .federated-content.
```

So in Axismundi: **do not ship a broad post-content prose layer.** Bind block
classes; confine prose to an explicit remote/imported scope — which is exactly the
social-object renderer territory.

Axismundi에서는 광범위 post-content prose 레이어를 깔지 않는다. 블록 클래스를 바인딩하고,
prose는 `.federated-content` 같은 명시적 remote/imported 스코프 안에만 격리한다 —
그 스코프가 곧 social-object 렌더러 영역이다.

---

## §4 — Classification buckets

```txt
Content primitive (block-rooted, token-styled, NOT an MD3 component)
    core/paragraph · heading · list · quote · table · code · separator
    → style via block class + theme.json; MD3 = tokens only, not a component map.

Chrome / template component
    core/navigation · query(-loop) · comments(+template) · query-title
    · breadcrumbs · post-* template blocks
    → template-context; global binding by block class.

MD3 component binding
    core/button(s) · search · form fields · (chips) · (cards via group) · avatar
    → real M3 component treatment; register_block_style + partial + CSS contract (§5).

Social object renderer (NOT WP core comment/post template)
    ActivityPub note / article / reply / feed objects
    → remote object renderer + federated prose scope (§3); plugin/custom-block lane.

Fallback
    raw <table>, raw <ul>, raw markup in remote HTML
    → prose / federated scope only.
```

`table` resolves cleanly: **content primitive, block-rooted, token-styled — not an
MD3 component** (M3 has no core "table" component; data tables are a separate
concern). Raw `<table>` in remote HTML → fallback/federated.

---

## §5 — Variation-layer contract (encoded conclusion)

A concrete classification result Omphalos produced, verified empirically in the
Site Editor (2026-06-06). A core-block **style variation** is THREE
non-redundant layers, not a duplicated one:

```txt
1. register_block_style()                  → selectable name + label + is-style-<slug>
                                             class. Required for toolbar + CSS render.
2. styles/blocks/<block>-<slug>.json partial → Global Styles / Stylebook DISCOVERABILITY.
                                             Verified: register_block_style() ALONE does
                                             NOT surface a variation in the "Style
                                             variations" panel — removing the partial made
                                             Tonal/Elevated/Connected vanish from the editor
                                             UI (front end still rendered). The partial is
                                             the UI layer, not a duplicate.
3. blocks.css                              → visual treatment + hover/focus/active state
                                             layers (color-mix) + connected geometry.
                                             Partials carry base colour only.
```

```txt
NOT used:  theme.json styles.blocks.*.variations inline.
           Pure duplication of the partial's base colour. Removed in 0b8edc1.
```

Axismundi encoding rule / 규칙: one variation = `register_block_style` (label) +
one partial (discoverability + base) + CSS (state/geometry). Never add the
theme.json inline copy; never delete the partial thinking it is redundant.

---

## §5b — token var() vs UI-editable concrete value

A second empirically-verified UI constraint (Site Editor, 2026-06-06). Global
Styles **dimension-class controls** (border radius, spacing, size) parse their
value with `parseQuantityAndUnitFromRawValue()` — number + unit. A `var(--token)`
fails to parse, so the control renders **empty / 0** even though the style applies
on the front end. Color controls differ (they accept `var()` and show a custom
swatch), so the constraint is dimension-specific.

```txt
core/image border.radius = var(--md-sys-shape-corner-medium)
    → front end: 12px rounding applied
    → Global Styles Radius slider: shows 0, NOT editable (var() unparseable)

core/image border.radius = 12px
    → front end: identical
    → Global Styles Radius slider: shows 12, editable ✓
```

**Normative rule / 규칙:**

```txt
A theme.json style VALUE that is meant to be surfaced/edited in a Global Styles
dimension control MUST be concrete (px), not a var() token.
Reserve var() tokens for FIXED theme decisions where front-end application is
enough and editor visibility/editability is not required.
```

- Global Styles dimension 컨트롤(radius/spacing/size)에 **노출·편집되어야 하는** theme.json
  style 값은 concrete(px)여야 한다. var() 토큰은 적용은 되나 그 컨트롤에 안 보인다.
- var()는 front 적용만으로 충분하고 editor 편집이 불필요한 **고정 테마 결정**에만 쓴다.

This is the same shape as §5: Omphalos discovers which layer the WordPress UI can
actually read, and Axismundi encodes the readable form. (`core/image` radius fixed
to a concrete `12px` so its Radius slider is editable; the M3 medium corner is the
spec constant 12px, so no token drift in practice.)

### Shape tokens and WordPress presets

M3 exposes a full shape/corner scale (`md.sys.shape.corner.*` and
`md.sys.shape.corner-value.*`). Omphalos keeps that scale in CSS system tokens.
WordPress theme.json, however, does **not** expose a border-radius preset axis
equivalent to `color.palette`, `typography.fontSizes`, `spacing.spacingSizes`, or
`shadow.presets`.

```txt
settings.border.radius  = boolean control enablement only
styles.border.radius    = concrete string/object value
settings.border.presets = not a WordPress theme.json schema feature
```

Therefore:

```txt
M3 shape scale → CSS system tokens
Global Styles radius UI → concrete per-block/per-element style values
```

Do not invent a fake `theme.json` radius preset layer. If WordPress later adds a
first-class radius preset axis, Axismundi can map the CSS system tokens into that
axis during the clean build.

### Spacing tokens and WordPress spacing presets

M3's official spacing scale is `md.sys.measurement.space*` (0, 2, 4, 6, 8, 10,
12, 14, 16, 20, 24, 32, 36, 40, 48, 56, 64, 72). Omphalos keeps that family in
CSS system tokens as `--md-sys-measurement-space*`.

WordPress also has a first-class `settings.spacing.spacingSizes` preset axis, but
that axis is an editor/UI preset surface, not the canonical M3 token namespace.
During migration:

```txt
M3 measurement scale               → CSS system tokens
WordPress spacingSizes             → editor-friendly preset subset / aliases
Existing Omphalos --space-* aliases → keep stable until a deliberate sweep
```

Do not mechanically replace `--space-xs/sm/md/lg/xl` while porting tokens. Those
aliases are current Omphalos authoring shorthands and should be migrated only in
a focused spacing sweep.

### Motion tokens

M3 motion has three useful layers:

```txt
spring physics              → damping/stiffness tokens for native runtimes
easing + duration           → canonical CSS transition/animation tokens
converted spring curves     → web-friendly cubic-bezier + duration pairs
```

WordPress theme.json does not expose a first-class motion preset axis. Omphalos
therefore keeps motion as CSS system tokens only. Existing
`--md-sys-motion-curve-*` variables are stable Omphalos aliases used by current
CSS; explicit `--md-sys-motion-expressive-*` and `--md-sys-motion-standard-*`
tokens can be introduced alongside them without retargeting existing components.

---

## §6 — Axismundi clean-build encoding

```txt
theme.json / block supports / style variations  = 1st-class source (authored content)
CSS                                              = state, edge cases, glue only
prose                                            = federated/remote fallback scope only
plugin / custom block                            = behavior + social-object schema
```

This is the structure Omphalos's experiments are converging toward. Each Omphalos
route doc (THEME-VQA, BUTTON, LIST-GRID, EMBEDS, …) is a source of classification
data; this file is the standing summary of the rules they have settled.

---

## §7 — WordPress.org submission lessons for Axismundi

Omphalos's first WordPress.org upload produced a second kind of migration data:
not visual/component classification, but **distribution constraints**. Axismundi
must treat these as build-time requirements from the start, not as a late release
cleanup pass.

```txt
source tree       = lab / documentation / VQA / seed scripts may exist
distributable ZIP = only runtime theme files + GPL-compatible assets + wp.org
                   submission metadata
```

### 7.1 Build a distributable, do not submit the source tree

Omphalos started around 580 MB because the source theme contained `node_modules`,
VQA media, seed scripts, docs, and development files. The submitted ZIP became
~10 MB only after a `.distignore` + build script produced a clean package.

Axismundi rule:

```txt
Never upload the working directory.
Always upload a built ZIP produced by a repeatable build script.
```

Minimum dist exclusions:

- `node_modules/`, package lockfiles, local environment files.
- docs, seed scripts, VQA-only patterns/pages, screenshots used only for testing.
- source-only media that is not needed at runtime.
- any non-GPL-compatible asset, even if it is free-to-use elsewhere.

The release artifact, not the repository checkout, is the review surface.

### 7.2 Asset licensing: "free to use" is not enough

The upload scanner rejected Omphalos when a Pixabay reference was present.
Pixabay allows broad usage, but its standalone redistribution restrictions are
not GPL-compatible for WordPress.org theme packages.

Axismundi rule:

```txt
Every file inside the ZIP must be GPL-compatible.
Free-to-use / no-cost / commercial-use-allowed is NOT sufficient.
```

Keep per-file asset provenance in a manifest (`assets/LICENSES.md` style), but
also make sure the manifest itself does not describe non-GPL bundled assets in
the distributable. If a demo asset is not GPL-compatible, keep it in source/VQA
only and exclude it from the ZIP.

### 7.3 Custom blocks are plugin territory for wp.org themes

Omphalos's theme-bundled `register_block_type()` produced a REQUIRED failure.
The theme switcher had to move to a companion plugin; the theme kept only a
custom-HTML fallback with plain CSS/JS.

Axismundi rule:

```txt
Theme can ship:
  block styles, style variations, patterns, templates, CSS, vanilla runtime glue.

Plugin should ship:
  custom block registration, custom schemas, dynamic block behavior, editor block
  UI, ActivityPub object actions, async/form/social behavior.
```

If a feature is essential to the site shell, provide a theme fallback that uses
core blocks or custom HTML. Put the richer block UI in a companion plugin.

### 7.4 Submission metadata is part of the theme, not paperwork

Omphalos needed these files/fields before upload:

- `readme.txt` with a real WordPress.org contributor username.
- `LICENSE` with the full license text.
- `NOTICE.md` for bundled third-party notices (Apache/OFL/etc.).
- `assets/LICENSES.md` for per-file asset provenance.
- `screenshot.jpg`/`screenshot.png` at the root.
- `style.css` headers aligned with the actual project license.
- Theme URI that points to the specific theme, not only the monorepo root.

Axismundi rule:

```txt
Submission metadata lives next to the theme from the beginning.
Do not wait until release week to add license/readme/screenshot files.
```

### 7.5 Size limit is decimal 10 MB

The WordPress.org upload page reports a maximum file size of 10 MB. Omphalos's
first GitHub release ZIP was about 10.23 MB and therefore over the decimal limit,
even though it looked close in MiB terms. Converting the root screenshot from PNG
to JPEG brought the package below the threshold.

Axismundi rule:

```txt
Check bytes, not the friendly "MB" label.
Target < 10,000,000 bytes for wp.org uploads.
```

Large icon fonts (for example a full Material Symbols font) should be subset
before release if they threaten the limit.

### 7.6 Block themes still hit classic automated checks

The WordPress.org upload scanner reported classic-theme REQUIRED items for a
TT5 child block theme: `index.php`, `wp_head()`, `wp_footer()`,
`language_attributes()`, `body_class()`, `wp_body_open()`, `wp_link_pages()`,
and theme supports such as `title-tag` and `automatic-feed-links`.

Some of this is scanner noise for block themes and child themes, but Axismundi
should avoid unnecessary reviewer friction:

```txt
For a clean block theme:
  include a minimal index.php where required by the scanner;
  declare standard add_theme_support() flags when harmless;
  rely on block templates for rendering, but satisfy legacy scanner expectations
  where doing so does not distort the theme architecture.
```

Do not blindly add classic template rendering just to satisfy a scanner. Add only
compatibility shims that are inert for the block-template path.

### 7.7 Test the installed distributable, not only the source theme

Omphalos used two different validation surfaces:

```txt
source theme check      = useful during development, but can see source-only files
installed dist ZIP check = the actual submission artifact
```

The latter is authoritative. Always install the built ZIP into a temporary theme
directory and run Theme Check there. This catches missing runtime files and avoids
false failures from source-only docs/media/scripts.

### 7.8 Child-theme lessons do not all carry forward

Omphalos is a TT5 child theme, so some warnings are parent/child artifacts:

- parent text-domain references can appear in scans;
- inherited templates may satisfy runtime behavior but not static source scans;
- Site Editor database customizations can hide file-template changes during VQA.

Axismundi should be a clean theme, so these artifacts should disappear. The clean
build should own its templates and parts directly, then validate with a fresh
database before release.
