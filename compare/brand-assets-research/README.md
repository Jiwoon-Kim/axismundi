# Brand Assets Research

> **Frozen reference workspace. NOT theme content. NOT for distribution.**
>
> Third-party brand and social-platform logo assets, gathered as
> *references* for icon-system planning. These files are **never**
> bundled into the theme, the plugin, or the published styleguide
> mirror. They exist only to inform policy decisions and, in narrow
> cases, to be used as styleguide reference specimens (sourced fresh
> from each brand's official channels at the time of use).

## Why this folder exists

During v3.4.2 Icon System Scope Audit, the dual-engine policy
established that:

- **Icon font track** (Material Symbols) → theme chrome glyphs
- **SVG track** → WordPress / editor / social / brand / portable content

For the SVG track to have a clear policy, the project's research
included gathering example brand SVGs to understand: licensing
constraints, color conventions, optical sizing, format consistency,
and whether monochrome adaptive coloring is feasible per-brand.

The actual research conclusion: **brand and wordmark assets do not
belong in theme core or plugin core.** They have per-brand licensing
and trademark constraints that vary widely and that would impose
maintenance burden on every release. See `SVG-ICON-POLICY.md` and
the policy paragraph below.

This folder preserves what was gathered, marked as DO-NOT-SHIP, so
future sessions don't repeat the research and so the inventory is
recoverable if styleguide reference specimens are ever needed.

## Policy

Per `lab/modules/icon-system/docs/SVG-ICON-POLICY.md` and
`lab/docs/ARCHITECTURE-BOUNDARIES.md` §6:

```
Axismundi may ship a minimal set of generic, monochrome,
currentColor SVG glyphs when SVG is required for component
geometry, animation, or fallback behavior.

Third-party brand, social, and wordmark assets must NOT be
shipped in the theme core. They belong to plugin territory,
external asset research, or user-provided configuration.
```

For each brand asset in this folder, exactly one of the following
dispositions is permitted:

| Disposition | What it means | Allowed location |
|---|---|---|
| **Reference specimen** | Used in styleguide HTML as an illustration of an interoperability concept (e.g. "the SVG track handles brand logos like this") | `style-guide.html` only, with trademark-attribution caption and link to the brand's official source page |
| **Research-only** | Kept in this folder, never embedded in any HTML, used only for policy thinking | This folder, with this README's attribution context |
| **Discard** | The asset is not useful for either purpose and should be deleted from this folder | Removed; entry struck from the manifest below |

**Forbidden dispositions**:

- Bundling into the theme (`/products/reference-implementations/axismundi-lab/stylesheets/` or similar).
- Bundling into the published styleguide mirror (`/styleguide/`).
- Embedding as a generic icon-system primitive.
- Treating as a "generic social icon" without per-brand source verification.
- Using a stale copy when a styleguide reference specimen is needed — always re-fetch from the brand's official source at the time the specimen is added.

## Manifest

| File | Brand | Source URL | License / Trademark notes | Disposition |
|---|---|---|---|---|
| `WordPress-logotype-wmark.svg` | WordPress (W mark, monochrome) | https://wordpress.org/about/logos/ | WordPress Foundation Trademark Policy — descriptive use in WordPress-related projects permitted; must not imply endorsement or affiliation. See https://wordpressfoundation.org/trademark-policy/ | **v3.4.4: promoted to reference specimen.** Embedded in `lab/modules/icon-system/icon-system-pattern.html §SVG icons` as the SVG-track interoperability demo. `currentColor`-normalized at point of use; original seed file in this folder unchanged. Trademark caption + source link required on every render. |

When new assets are added, append a row. When an asset's disposition
changes (e.g. promoted from research-only to reference specimen),
update the row inline and note the version in which the change
happened.

## What is NOT in this folder

The project research uncovered these brand sources but **none of their
assets are mirrored here**. They are referenced by URL only; fetch
freshly at the time of use:

| Brand | Source URL |
|---|---|
| Mastodon | https://joinmastodon.org/branding |
| Misskey | https://misskey-hub.net/en/brand-assets/ |
| Facebook | https://www.meta.com/brand/resources/facebook/logo/ |
| Threads | https://www.meta.com/brand/resources/instagram/threads/ |
| Instagram | https://www.meta.com/brand/resources/instagram/instagram-brand/ |
| X | https://about.x.com/en/who-we-are/brand-toolkit |
| YouTube | https://brand.youtube/ |
| Lemmy | https://commons.wikimedia.org/wiki/File:Lemmy_logo.svg |
| Reddit | https://redditinc.com/brand |
| Pinterest | https://business.pinterest.com/ko/brand-guidelines/ |
| Google | https://developers.google.com/identity/branding-guidelines |
| GitHub | https://brand.github.com/foundations/logo |
| Slack | https://slack.com/intl/ko-kr/media-kit |
| LinkedIn | https://brand.linkedin.com/downloads |

This URL list is the maintained inventory; the absence of files is
intentional. If a future release needs a specific brand specimen, the
maintainer fetches from the source URL at that time (so trademark and
visual conventions are current) and adds an entry to the manifest above.

## See also

- `lab/modules/icon-system/docs/SVG-ICON-POLICY.md` — full policy on SVG-required cases, sanitization, and accessibility
- `lab/docs/ARCHITECTURE-BOUNDARIES.md` §6 — federation portability rule
- `lab/docs/ARCHITECTURE-BOUNDARIES.md` §4 — theme can / plugin should split (brand registries are plugin territory)
- `BACKLOG.md` (repo root) — items deferred from v3.4.3+ visual QA include "WordPress logo styleguide specimen" and "Monotone SVG theming plugin"

## Change log

- **v3.4.3.1 — folder established.** WordPress wmark SVG (single file)
  added as research-only seed. Manifest, policy clauses, and forbidden-
  dispositions table written. URL inventory of 14 unmirrored brand
  sources captured for future fetch-from-source workflow.

- **v3.4.4 — WordPress specimen promoted.** Disposition for
  `WordPress-logotype-wmark.svg` changed from "research-only" to
  "reference specimen". The seed file in this folder is unchanged
  (it remains the source-of-truth copy). The specimen is embedded
  inline in `lab/modules/icon-system/icon-system-pattern.html` with
  `<path fill="currentColor">` normalization applied at point of use,
  trademark caption, and link to https://wordpress.org/about/logos/.
  Resolves BACKLOG item 5. Policy unchanged: theme core still
  ships zero brand assets; specimen exists only on the lab pattern
  page (lab-internal, not on the public publish surface).
