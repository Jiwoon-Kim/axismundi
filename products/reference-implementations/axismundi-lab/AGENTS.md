# AGENTS.md — axismundi-lab

The workbench. Components are implemented and measured here by hand before they
become theme or plugin code. It is evidence, not a product, and it is not
published.

```bash
python tools/validators/validate_token_layering.py
```

Axis E reads this directory's scheme files and the shipped theme's; axis F is
lab-only, because it audits the hand-written WordPress bridge CSS and the theme
has no equivalent — WordPress generates `--wp--preset--*` from `theme.json` at
runtime, leaving no stylesheet to read. All three lines must report 1.000.

## What this directory is for

The style guide explains a result; this keeps the evidence for it. Do not merge
the two roles — collapsing them erases the difference between what was measured
and what was claimed.

A measurement recorded here should say what was measured, at what viewport or
state, and with what value. A number without those is not evidence.

## Baseline sections

`stylesheets/components.css` and `style-guide.html` carry baseline sections and
`#components-*` anchors that downstream work references by name. Do not
renumber, rename, or reorder them without asking.

## Not published

Lab pages stopped being served when GitHub Pages moved to an Actions artifact.
They open from the file system. Do not add a publishing step for them, and do
not link to them as if they had a public URL.
