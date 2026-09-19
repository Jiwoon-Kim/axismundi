# Tools

Scripts that operate on layers but are not themselves a layer.

- `refine/` — corpus refinement (cleans `source/` → `refined/`)
- `builders/` — ontology builders (atlas + corpus → core)
- `generators/` — product emitters (core + bindings → product files like theme.json)
- `validators/` — cross-layer audits (e.g., theme-pilot ↔ binding_map)

`validators/verify_upstream_comment.py` is a preflight gate for public posts.
It compares a prepared candidate against the declared source draft byte-for-byte,
apart from line endings. Run it before posting rather than reconstructing text
from a summary:

```powershell
python tools/validators/verify_upstream_comment.py `
  --source docs/UPSTREAM-TRAC-44001-COMMENT.md `
  --source-after "## Comment (Trac WikiFormatting — paste as is)" `
  --candidate tmp/comment-to-post.txt
```

The command prints a SHA-256 digest on success and a unified diff on failure.

Other options:

- `--source-before "<marker>"` ends the compared section, so notes below the
  post in the draft are left out.
- `--source-from "<marker>"` starts the section at a marker and keeps that line,
  for a body that begins with its own heading.
- Every marker must occur exactly once in the source. A marker that also
  appears in the draft's notes (a quoted title, say) stops the check instead of
  silently comparing the wrong section.
- `--require-file <file>` lists literals, one per line, that must appear in the
  source. Use it when escape notation is the content: a draft altered while it
  was saved still matches its own copy.
- After submitting, compare what the server stored: `--github <issue, PR or
  #issuecomment- URL>` reads it through `gh api`; for Trac, copy the posted text
  to a file and pass `--posted <file>`.

`--self-test` runs nine cases, including a decoded source, a posted link in the
wrong syntax, and an ambiguous marker.

**Rule**: tools never persist their state in layer directories. Tool output is layer content; tool internals stay in `tools/`.
