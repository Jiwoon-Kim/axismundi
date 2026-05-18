# v3.5.18 — Blocks / Prose Pilot Specification Verification

Status: Phase 2 verification record  
Date: 2026-05-18  
Scope: `blocks.html` + `prose.html` as v3.6.0 Pilot input specs

## §0. Verdict

```txt
PASS WITH ONE IN-CYCLE FIX.
```

`blocks.html` and `prose.html` are valid Pilot specification references:

- `blocks.html` covers the non-component WordPress block chrome currently
  represented in `blocks.css`.
- `prose.html` covers the post-body rendering contract represented in
  `prose.css`.
- No Pilot-blocking spec gap was found.
- One local prose page containment issue was fixed in-cycle:
  `.sg-article` now uses border-box sizing and `min(65ch, 100%)` containment so
  the 390px smoke test does not overflow.

## §1. Correct Framing

```txt
style-guide.html = component chrome catalog and public visual demo.
blocks.html      = WordPress core block coverage extension for blocks that do
                   not map cleanly to component modules.
prose.html       = WordPress post-body rendering contract.
```

These pages are not merely documentation. They are reference specifications for
the v3.6.0 block theme Pilot.

## §2. Blocks Coverage

Source files:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
```

Verified coverage:

| Area | `blocks.css` contract | `style-guide-blocks.html` specimen | Result |
|---|---|---|---|
| Alignments | `.alignleft`, `.alignright`, `.aligncenter`, `.alignwide`, `.alignfull` | Alignment specimens | PASS |
| Paragraph | `.wp-block-paragraph.has-drop-cap` | Drop-cap paragraph | PASS |
| Lists | `.wp-block-list`, `.is-style-list-segmented`, task-list reset | Default, segmented, task list | PASS |
| Quote / Pullquote | `.wp-block-quote`, `.wp-block-pullquote` | Quote + pullquote specimens | PASS |
| Code | `.wp-block-code`, `.wp-block-preformatted`, `.wp-block-verse` | Code, preformatted, verse | PASS |
| Separator | `.wp-block-separator` variants | Default, wide, dots, inset, middle-inset | PASS |
| Table | `.wp-block-table`, stripes, wrap, vertical borders | Table matrix incl. wide table | PASS |
| Media | `.wp-block-image`, `.wp-block-gallery.columns-*` | Image + gallery specimens | PASS |
| Layout | `.wp-block-columns`, `.wp-block-column`, `.wp-block-group` | Columns + group/card specimens | PASS |
| Buttons | `.wp-block-button`, `.wp-block-buttons`, style variants | Filled / tonal / elevated / outlined / text | PASS |

Pilot note:

```txt
core/button still needs Pilot PHP work:
  register_block_style()
  render filter / class injection if the theme wants .ax-button takeover

This is not a v3.5.18 blocker; it is expected v3.6.0 implementation work.
```

## §3. Prose Coverage

Source files:

```txt
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-lab/stylesheets/prose.css
```

Verified coverage:

| Area | `prose.css` contract | `style-guide-prose.html` specimen | Result |
|---|---|---|---|
| Container | `.prose` baseline, max measure, rhythm | `.sg-article.prose` | PASS |
| Headings | h1-h6 hierarchy and spacing | Article headings + anchors | PASS |
| Paragraphs | mixed Korean/English line-height and rhythm | Intro paragraphs | PASS |
| Lists | ul/ol/nested lists/definition lists | List sections | PASS |
| Blockquote | quote bar, cite handling | Quote specimens | PASS |
| Rule | prose `hr` rhythm | Divider specimen | PASS |
| Code | inline code + pre/code block containment | Inline and block code | PASS |
| Media | image/figure/figcaption | Media specimens | PASS |
| Links | links + heading anchors | Article links/anchors | PASS |
| Tables | wrapper/figure containment + mobile scroll | Table specimens | PASS |
| Forbidden icons | Material Symbols disallowed inside `.prose` content | Documented in CSS | PASS |

In-cycle fix:

```txt
Problem:
  styleguide/prose.html overflowed at 390px because `.sg-article` measured
  65ch plus padding in a way that exceeded the viewport.

Fix:
  `.sg-layout > * { min-inline-size: 0; }`
  `.sg-article { box-sizing: border-box; inline-size: 100%;
                  max-inline-size: min(65ch, 100%); }`

Result:
  390px smoke test overflow = 0.
```

## §4. Publish Mirror

`publish_styleguide.py` was rerun after the prose containment fix.

Generated files:

```txt
styleguide/blocks.html
styleguide/prose.html
```

Smoke result:

```txt
styleguide/blocks.html  render PASS, console/page errors 0, overflow 0
styleguide/prose.html   render PASS, console/page errors 0, overflow 0
```

## §5. Pilot Handoff Implications

v3.6.0 Pilot should consume:

```txt
blocks.html / blocks.css:
  block chrome and non-component core block coverage

prose.html / prose.css:
  post body rendering contract
```

v3.6.0 Pilot must implement or verify:

```txt
core/button class/style registration
core/group card-style registrations
core/list segmented style registration
core/separator variants
core/table variants
post content `.prose` wrapper placement
```

No Pilot-before blocker remains from blocks/prose verification.
