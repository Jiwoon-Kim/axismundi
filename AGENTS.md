# AGENTS.md

Rules for any AI coding agent working in this repository. `CLAUDE.md` imports
this file; there is no second copy.

## Read the nearest file, not all of them

Each surface has its own `AGENTS.md`. Open the one closest to what you are
changing; this file holds only what is true everywhere.

| Working in | Also read |
|---|---|
| `products/wordpress/` | `products/wordpress/AGENTS.md` |
| `products/styleguide/` | `products/styleguide/AGENTS.md` |
| `products/reference-implementations/axismundi-lab/` | that directory's `AGENTS.md` |
| `tools/` | `tools/AGENTS.md` |
| `core/` | `core/AGENTS.md` |

Read what the task needs. Do not read the repository to orient yourself.

## Owner-protected source roots

Unless the current task explicitly names them, treat these as read-only source
material. Structural cleanup elsewhere in the repository must not pull them
into a move, rename, rewrite, or generated-output sweep:

```txt
products/
assets/
core/design-systems/material3/
```

They may be read to establish an input or a product contract. A task that does
explicitly name one of these paths sets its own scope; do not infer permission
from a related change.

Protection is not implementation authority. Within
`core/design-systems/material3/`, only `assets/` is a current source input;
`specs/` is reference material to verify against current M3 guidance, and
`runtime/` plus `token_ontology.jsonld` are historical prototype material.

## Rules and enforcement

**A prompt rule explains intent. A validator, test, or CI check enforces it.
Do not describe an invariant as enforced unless its enforcement command is
named and maintained.**

Everything in these files is one of two kinds, and each hard rule below says
which:

- **enforced** — a named command fails when it is broken.
- **convention** — nobody checks. It holds only because you follow it.

What is enforced today:

| Invariant | Command | Runs |
|---|---|---|
| `--md-sys-color-*` resolves to `var(--md-ref-palette-*)`, in the theme and the lab | `tools/validators/validate_token_layering.py` | `.github/workflows/validator.yml`, every PR and push |
| `--wp--preset--color--*` bridges point at a token that exists, in the lab | same command, axis F | same |
| Style-guide tokens match spec, `theme.json`, and their generators | `products/styleguide/bin/build.py --verify` | `.github/workflows/styleguide.yml`, on style-guide paths |

`tools/validators/validate_line_endings.py` exists but is wired to no workflow.
Treat it as a convention until it is.

Nothing else in this repository is enforced by CI. If you find yourself writing
"must never" about something with no command beside it, either write the
command or call it a convention.

## What is current, and what is not

```txt
products/wordpress/       shipped theme and plugins        source of record
products/styleguide/      published design-system docs     source of record
axismundi-lab/            local implementation workbench   evidence, not product
assets/ and core/design-systems/material3/  protected source inputs
corpus/ atlas/ bindings/ core/ (except material3)  research and provenance
```

Within the protected Material 3 directory, the useful distinction is:

```txt
assets/                          protected font and icon source material
specs/                           M3 reference; verify before implementing
runtime/tokens.css               historical; do not import into products
runtime/base.css                 historical runtime policy; not product CSS
token_ontology.jsonld            provenance, not a published vocabulary
```

### Upstream is the source of truth for upstream

For anything about how WordPress or Gutenberg actually behaves, read the
Gutenberg repository, WordPress core, or the official documentation. Do not
answer from `corpus/`, and do not try to keep a copy of upstream current here —
that is not a maintainable goal and the attempt is what produced the stale
snapshot.

### Frozen research

`corpus/`, `atlas/`, `bindings/`, and `core/` outside
`core/design-systems/material3/` are the investigation that built this
project's understanding in early 2026. Keep them, cite them for history, and
do not use them to decide a current implementation. Two verified examples of
how they mislead:

- `bindings/wordpress-material3/binding_map.json` specifies Button Filled as
  `is-style-filled` and Outlined as `is-style-outlined`. What ships is an
  unclassed Filled default, core's `is-style-outline`, and `is-style-outlined`
  only as a legacy compatibility shim.
- The same file says there is no M3 spacing scale and no spacing token. There
  is: `md.sys.measurement.space*`, and `theme.json` carries every value
  WordPress can express.

`CURRENT-STATE.md`, `NEXT-SESSION.md`, `PROJECT-CONTEXT.md` and `docs/v3.5.x/`
describe a phase model the project has moved past. They are history. Nothing
requires you to read them.

To make a slice of the frozen material usable again, verify it against current
code and write the result beside the product. Do not promote it by citing it.

## Measure; do not infer

The rule that has changed the answer most often here. Read the computed value,
run the checker, load the page and take the number off it. A selector existing
is not proof that it applies, and source that reads correctly can behave
wrongly. Recent cases, each of which looked right in the file: an icon that
stayed 20px at every size, a label clipped by a fixed height, a CI job whose
`main()` returned `None` and so passed on every input.

When something looks fixed but behaves broken, suspect the copy before the
code: output that was not regenerated, a cached stylesheet, a mirror that is
not the file you edited.

## Generated files

Generated output is committed, its generator takes `--check`, and a separate
validator asks a different question:

- `--check` — is the committed file what the generator writes?
- validator — is that value what the spec and `theme.json` say?

Both, because a buggy generator passes its own check every time. If you add a
second copy of anything, add the checker with it — otherwise it is a
convention, and conventions drift. Do not hand-edit a file whose header says it
is generated.

## Safety — convention unless noted

- Preserve existing behaviour. A change that looks like cleanup but removes a
  deliberate deviation has cost real functionality here; mark deviations
  `NON-STANDARD` with the reason rather than deleting them.
- Do not rename a public CSS class, block name, or token without asking. Saved
  content carries them.
- Do not change what a published URL serves without asking.
- Do not delete or rewrite `corpus/`, `atlas/`, `bindings/`, or research parts
  of `core/`. Frozen is not disposable.
- Commit or push only when asked. Never force-push, never skip hooks.
- Ontology, category, and layer-boundary decisions belong to the project owner.

## Language

English at the boundaries, Korean for reasoning.

```txt
English   URL, filename, slug, token name, CSS variable, block name,
          standard terms, code samples, and all code comments
Korean    explanation, rationale, criteria for use, in internal design docs
```

Shipped code is read by people outside this repository, so its comments are
English. Internal design documents lose their reasoning in translation.

## This machine

Windows, Git Bash, native Windows Python. Each of these has cost real time.

- **Heredocs mangle backslashes.** `\\n` arrives as `\n`, and `\b` becomes a
  literal backspace in the file you wrote. Use a file-editing tool for content
  containing a backslash.
- **Python needs Windows paths.** `/c/Users/...` works in `ls` and `grep` and
  raises `FileNotFoundError` in Python. Use `C:/Users/...`.
- **`Path.write_text()` writes CRLF.** Pass `newline="\n"`.
- **`git check-ignore --stdin` needs `-z` and bytes.** In text mode `\r` joins
  the path and file-name rules silently miss.
- **A hidden browser pane reports a 0x0 viewport and never fires
  `requestAnimationFrame`.** Transitions freeze at their start value and widths
  read 0. Do not fix CSS from those numbers.
