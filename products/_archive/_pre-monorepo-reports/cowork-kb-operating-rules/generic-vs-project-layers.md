---
name: knowledge chunks must separate generic vs project layers
description: Axismundi KB project — WP handbook + M3 spec chunks must be project-agnostic / reusable across other projects. Project-specific content goes in a separate layer.
type: feedback
originSessionId: 906565b5-2dd0-41e7-96c6-ace8de371cb8
---
The intent of the Axismundi knowledge base is to build a **reusable**
knowledge base for WordPress (and other) handbook material that can be
lifted to other projects, **not** an Axismundi-specific knowledge base.

**The rule:**
- Generic chunks (WP handbook, M3 spec, ActivityPub spec, etc.) →
  `./knowledge/{wordpress,material,activitypub}/...` — must contain ZERO
  project-specific content. No mentions of Axismundi codebase, no
  references to project files, no project-specific divergence notes.
  These chunks should be valid to lift into any future WP/M3 project.
- Project-specific content (Axismundi codebase impl, our M3 token manifest
  vs WP standard divergence, decisions like F-2, CHANGELOG references,
  per-component impl mappings) → `./knowledge/axismundi/...` — separate
  project layer that *references* the generic chunks.

**Why:** User explicitly stated on 2026-05-09 that mixing project-specific
content into WP handbook chunks defeats the reuse purpose. *"내 의도는
워드프레스 핸드북을 다른프로젝트에서 재사용할수있도록 범용성있게 knowledge
base로 만들고싶은거지 프로젝트에 맞춰서 작성하려는것이 아님. 그런건 레이어를
분리해야지."*

**Past mistake to avoid:** First WP `overview.md` chunk had a long
"Axismundi current state — divergence" section embedded directly in it
(F-2 quotes, CHANGELOG line numbers, `./Axismundi/` paths). This violated
the rule and required immediate refactor.

**How to apply:**
- When writing a chunk under `./knowledge/wordpress/`, `./knowledge/material/`,
  or any other generic-spec folder: pretend the reader is a developer on a
  completely different WP block theme project. Would this content be
  useful and accurate to them? If yes → keep. If no → move to
  `./knowledge/axismundi/`.
- Cross-references between layers: project chunks can and should reference
  generic chunks ("see `wordpress/block-editor/theme-json/overview.md` for
  the WP spec"). Generic chunks should NEVER reference project chunks.
- Project layer overlay structure mirrors the generic layer roughly:
  `./knowledge/axismundi/wordpress/theme-json-divergence.md` references
  `./knowledge/wordpress/block-editor/theme-json/overview.md` etc.
- Re-evaluate prior chunks: `text-fields-impl.md` is project-specific
  (components.css §9 implementation) and should live in the axismundi
  layer, not in `./knowledge/material/`. The 2-split itself (spec/impl)
  was already correct in spirit.

**Chat behavior unchanged:** Chat conversation, status reports, outline
proposals stay in Korean. This rule is about chunk file organization and
content scope only.
