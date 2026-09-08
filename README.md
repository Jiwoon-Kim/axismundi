# Axismundi

**WordPress products for independent, federated publishing.**

Material 3 block themes and companion plugins for identity, contacts,
activities, places, events, media, and open-web publishing.

- Author and decision owner: KIM JIWOON (designbusan.ai.kr) — Busan, Korea. See [AUTHORSHIP.md](AUTHORSHIP.md).
- Korean companion: [README.ko.md](README.ko.md).
- Architecture: [CONSTITUTION.md](CONSTITUTION.md).

## Start here

| | |
|---|---|
| **Axismundi Theme** | [`products/wordpress/themes/axismundi/`](products/wordpress/themes/axismundi/) — a Material Design 3 block theme built on WordPress core blocks |
| **Style Guide** | <https://jiwoon-kim.github.io/axismundi/styleguide/> — the design system, its tokens, and how each one binds to a block |
| **Releases** | [GitHub Releases](https://github.com/Jiwoon-Kim/axismundi/releases) — every theme and plugin ships as its own tagged zip |

The theme is the only product that stands alone. Every plugin below assumes it,
and several assume each other; each plugin's own readme names its dependencies.

## Products

### Presentation

The theme and the plugins that extend how a site looks and behaves.

| Product | What it does |
|---|---|
| [Axismundi Theme](products/wordpress/themes/axismundi/) | M3 block theme: token layers, type scale, core-block bindings |
| [Omphalos](products/wordpress/themes/omphalos/) | Child theme kept as a layout laboratory — the one that may break — with its own [switcher block](products/wordpress/plugins/omphalos-theme-switcher/) |
| [Dialogs](products/wordpress/plugins/axismundi-dialogs/) | M3 dialog and side / bottom sheet blocks with native `<dialog>` behaviour |
| [Theme Switcher](products/wordpress/plugins/axismundi-theme-switcher/) | Light / dark / auto block that remembers the reader's choice |
| [Theme Controls](products/wordpress/plugins/axismundi-theme-controls/) | Switches the palette itself, before first paint |
| [Navigation Icons](products/wordpress/plugins/axismundi-navigation-icons/) | Material Symbols leading icons on navigation items |
| [Table of Contents](products/wordpress/plugins/axismundi-table-of-contents/) | Builds from a post's headings and keeps their ids in sync |
| [Korean](products/wordpress/plugins/axismundi-korean-font-provider/) · [Japanese](products/wordpress/plugins/axismundi-japanese-font-provider/) · [Traditional Chinese](products/wordpress/plugins/axismundi-traditional-chinese-font-provider/) font providers | Fill the theme's CJK fallback slot and register as Font Library collections |

### Social domains

Identity first, then what identities do. These are layered on purpose: the
ledger does not own delivery, and the projection layer does not own state.

| Product | What it does |
|---|---|
| [Actors](products/wordpress/plugins/axismundi-actors/) | Identity registry: one immutable URI and one profile hub per local person and cached remote actor |
| [Object Projections](products/wordpress/plugins/axismundi-object-projections/) | Projects WordPress objects into ActivityStreams JSON-LD through one transformer registry |
| [Activities](products/wordpress/plugins/axismundi-activities/) | The activity ledger and social relationship state. Owns no inbox, no signatures, no delivery queue |
| [ActivityPub Bridge](products/wordpress/plugins/axismundi-activitypub-bridge/) | Boundary between these URI-keyed stores and the official ActivityPub plugin's S2S transport |
| [Notifications](products/wordpress/plugins/axismundi-notifications/) | One inbox per Actor, projected from the ledger by the domains that own each transition |
| [Contacts](products/wordpress/plugins/axismundi-contacts/) | Address books of JSContact Cards. Imports and federation are sources; the Card is the record |

### Content and place

| Product | What it does |
|---|---|
| [Note](products/wordpress/plugins/axismundi-note/) | Local object container with a fail-closed ActivityStreams Note projection |
| [Forum](products/wordpress/plugins/axismundi-forum/) | Federated community support for managed Group Actors, with membership policy |
| [Calendar](products/wordpress/plugins/axismundi-calendar/) | Schedules, occurrences, iCalendar subscriptions, FEP-8a8e federation |
| [Geodata](products/wordpress/plugins/axismundi-geodata/) | Canonical geo store with privacy-aware coordinate precision |
| [Map](products/wordpress/plugins/axismundi-map/) | Draws that geo data over a self-hosted basemap |
| [Media Library](products/wordpress/plugins/axismundi-media-library/) | Promotes attachments to independent media objects with visibility controls |
| [Emoji](products/wordpress/plugins/axismundi-emoji/) | Custom and FEP-9098 federated emoji, with admission review |
| [PWA](products/wordpress/plugins/axismundi-pwa/) | The installable-app surface and per-device push subscriptions |

## Status

Versions live in [Releases](https://github.com/Jiwoon-Kim/axismundi/releases);
this is only which bucket a product is in.

**Stable releases** — Axismundi Theme, Theme Switcher, Table of Contents, and
the Korean, Japanese and Traditional Chinese font providers.

**Prerelease** — Actors, Object Projections, Activities, ActivityPub Bridge,
Notifications, Contacts, Note, Forum, Calendar, Geodata, Map, Media Library,
Emoji, Dialogs, Navigation Icons, PWA. Installable and in use; the data schemas
and public function names may still move between versions.

**In the repository only** — Omphalos and its switcher, Theme Controls. No
release, and no compatibility commitment.

Nothing here is on WordPress.org yet.

## How it fits together

```txt
products/wordpress/                 the shipped products; this is the source of record
products/styleguide/                the published design-system documentation
products/reference-implementations/ hand-coded reference for local verification
core/  bindings/  tools/            the internal ground the products stand on
```

`products/wordpress/` is authoritative for anything that ships.
[`products/styleguide/`](products/styleguide/) is a Jekyll site built and
deployed by CI; it reads the theme's own token CSS and `theme.json` at build
time, so it documents what the products actually render rather than a
transcription of it.
[`axismundi-lab/`](products/reference-implementations/axismundi-lab/) is the
measured workbench — component implementations verified by hand before they
become theme or plugin code. It is not published.

### Repository layers

The six layers below are the repository's own ontology, and the direction of
dependency only ever runs down this table.

| Layer | Directory | Role |
|---|---|---|
| A. Corpus | `corpus/` | Raw and refined source documents |
| B. Atlas | `atlas/` | Rule-based knowledge and audits |
| C. Core | `core/` | Formal platform and design-system ontologies |
| D. Bindings | `bindings/` | Cross-ontology translation |
| E. Products | `products/` | Themes, plugins, and published documentation |
| F. Tools | `tools/` | Builders, generators, and validators |

Products are thin consumers. They register block styles, enqueue assets, and
provide WordPress integration code; they do not define ontology entities or
binding rules, which live in `core/` and `bindings/`.

### Where the assets live

| | |
|---|---|
| `core/design-systems/material3/assets/` | Upstream Material fonts and icons, before any product subsets them |
| `products/wordpress/themes/axismundi/assets/` | The theme's own subset fonts, token CSS, and component CSS |
| `bindings/wordpress-material3/` | The WordPress ↔ Material 3 binding data the theme consumes |

## Development

```powershell
npm install
```

Build and verify the style guide — regenerates the token CSS, refuses to
proceed if a committed file disagrees with its generator, then builds the site:

```powershell
python .\products\styleguide\bin\build.py --verify
```

Run the token-layering validator, which holds the rule that
`--md-sys-color-*` may only ever resolve to `var(--md-ref-palette-*)`:

```powershell
python .\tools\validators\validate_token_layering.py
```

Themes and plugins are developed against `wp-env`; each product's directory
carries its own notes on running it.

Contributions and licensing terms: [CONSTITUTION.md](CONSTITUTION.md),
[AUTHORSHIP.md](AUTHORSHIP.md), [NOTICE.md](NOTICE.md).

## Sponsor

Axismundi is an open-source WordPress project focused on Material Design 3,
Korean and CJK typography, reusable block-theme architecture, and ActivityPub-
ready publishing experiences.

Sponsorship helps fund ongoing development, testing, documentation,
localization, WordPress.org releases, and open social web research.

[Sponsor Axismundi development through GitHub Sponsors](https://github.com/sponsors/Jiwoon-Kim).

## License

Axismundi is multi-licensed by surface:

- code, theme, and tooling: GPL-3.0-or-later,
- documentation: CC BY-SA 4.0,
- ontology and binding data: CC BY-SA 4.0,
- third-party assets: original upstream licenses preserved.

See [LICENSE](LICENSE), [LICENSE-CC-BY-SA-4.0.md](LICENSE-CC-BY-SA-4.0.md),
[LICENSE-MATRIX.md](LICENSE-MATRIX.md), and [NOTICE.md](NOTICE.md).
