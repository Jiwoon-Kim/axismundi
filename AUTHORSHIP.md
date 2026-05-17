# Authorship

## Primary author

**KIM JIWOON** (designbusan.ai.kr) — Busan, Korea.

Axismundi is a personal project. All architectural decisions, strategic direction, design judgments, value priorities, and final shipping decisions are mine. The monorepo, its layered architecture, its doctrine, and its design system are *my work*.

## Tools leveraged

LLMs are amplification tools used during this project:

- **GPT (OpenAI)** — used for analysis, doctrine framing, ontology development, ultrareview, predictive utility pilots. Most reports preserved in `products/_archive/_pre-monorepo-reports/` are GPT outputs that I evaluated, accepted, modified, or rejected.
- **Claude (Anthropic)** — used for KB chunk authoring (Phase 7 + Phase 8), Cowork sessions, monorepo refactoring, asset conversion, document drafting, mechanical migrations. The current monorepo's structural mechanics (asset relocation, validation scripts, generators) were largely executed in Claude sessions.
- **Other models** — Gemini and similar used opportunistically, with their outputs forwarded for critical evaluation before adoption.

**Principle**: external review is evaluated critically. No LLM suggestion is adopted without my explicit assessment. Rejected suggestions include (selected examples): logical border-radius vs `transition` claimed conflict (rejected — Sheet component validated the pattern), C-2 outline-variant initial "correction" (reverted — original was correct per M3 spec), pre-monorepo asset relocation in v3.2 (deferred to v3.3 for cleaner staging).

## Decision authority

The decision tiers below clarify *what comes from where*. This is not a credit assignment to LLMs; it is a record of *my* decision territory so future readers (or future me) can distinguish "external standard" from "Ji-woon's call".

### Tier 1 — Universal / external standards (not authored by me)

- M3 design tokens spec (Google)
- WordPress block architecture, theme.json, template hierarchy (WordPress core)
- ActivityPub portability constraints (W3C)
- SIL OFL 1.1, Apache 2.0, GPL 3.0+ license terms
- HTML5 element semantics
- Roboto / Noto / Material Symbols glyph designs (original authors)

These are *adopted* not *authored*. They define the substrate within which Axismundi's decisions sit.

### Tier 2 — LLM-consultation-nudged, user-accepted

Decisions where LLM analysis converged on a recommendation and I accepted it after evaluation:

- **6-layer (A–F) folder architecture** — GPT-articulated; I adopted as canonical (Constitution Article 1)
- **5-point promotion criteria** for lab → prototype (v3.2.2) — GPT formulation, I accepted as gate policy
- **Prototype demotion to legacy** (v3.3.0) — GPT + Claude convergent recommendation, I authorized
- **CHANGELOG style + ROADMAP structure** — drafted by LLMs in formats I subsequently adjusted
- **CSS path rewriting in `publish_styleguide.py`** — Claude implementation of a problem I identified

These are mine in the sense that *I made the call*; LLMs structured the option space.

### Tier 3 — Pure Ji-woon decisions (signature)

Decisions originating in my judgment, my values, my working style:

- **Korean-first typography as first-class concern** (not optional, not afterthought)
- **GPL-3.0-or-later license intent** (Apache 2.0 compatibility with Material Symbols was decisive)
- **Lab-first promotion pipeline** (prototype → lab → distributable as 3-stage evolution)
- **Material Symbols icon font confined to FSE chrome only**; forbidden in post content / federation
- **Roboto full no-subset + Noto as Korean fallback** strategy (v3.2.3 correction of earlier subset error)
- **Future CJK via WP Font Library, not pre-shipped** in theme
- **Theme switcher in theme territory, HCT palette panel in plugin territory** (M3 meta-implementation pattern adoption)
- **Carousel experimentally shipped in theme** (not plugin), with Gallery block as fallback
- **`prototype` ≠ `RC`** naming correction; static visual specification distinguished from runtime release candidate
- **Pre-monorepo ultrareview results not preserved as authority** (only methodology) — Axismundi's authority is its current state, not historical findings
- **External LLM review treated critically**; no automatic acceptance
- **GitHub public push held until license matrix + theme separation done** (no premature shipping pressure)
- **Working environment using zip-versioned freezes** between work locations, not git-first

These are my signature. Anyone forking Axismundi can change any of them, but doing so makes the fork a different project.

## On "mind upload" vs "judgment externalization"

Some of my judgment patterns are now encoded in the monorepo — what I consider risky, what I separate, what I refuse to commit prematurely, what I value as architectural clarity. In that limited sense, Axismundi externalizes part of my design judgment into a structure that LLMs can read and operate on.

This is not mind upload. My sensory intuition, visual aesthetic judgment, contextual reading, and the *reasons-it-felt-right-in-the-moment* are not externalized. The monorepo cannot make new decisions autonomously without me triggering them. It cannot replicate the part of my judgment that says *"this looks correct"* when viewing a rendered style guide.

It *is* judgment externalization (판단 구조의 외부화). The structure encodes my decision-making patterns in a way that future LLM sessions, future contributors, or future-me can re-enter and continue the project with reduced re-orientation cost.

The atlas/doctrine layer in particular reflects my evaluative patterns — promotion criteria, scope boundaries, federation portability guards. Anyone else with different patterns would produce a structurally similar monorepo (A–F is universal) with different doctrinal contents.

## Meta-doctrine: the rules for making rules

Beneath the *what* of Tier 3 decisions (Korean-first, GPL-3.0, lab-first, etc.) sits a *how* — the procedural rules I follow when adding to the project. These are the cleanest fingerprint of authorship: anyone can adopt the 6-layer A–F architecture, but the *patterns under which content is added* are distinctly mine.

The 11 numbered rules in `products/_archive/_pre-monorepo-reports/cowork-kb-operating-rules/chunk-authoring-strategy.md` document this meta-doctrine. Key patterns:

- **Substrate-first** — locking foundations before building on top (Rule 4). Visible in: pilot block theme deferred until lab QA stable.
- **Spike-then-batch** — validate at small scale before scaling (Rule 2). Visible in: v3.2.x progression (font foundation → runtime → audit → coverage fix).
- **Atomize by ontology boundary, not by size** (Rules 1, 9). Visible in: monorepo's 6 layers split by *kind of authority*, not file count.
- **Operational density over historical completeness** (Rule 6). Visible in: prototype demoted not preserved as live reference; ultrareview specific findings retired in favor of methodology preservation.
- **Stay in one bounded context** (Rule 5). Visible in: each version (v3.2.0–v3.3.0) stays in one structural layer until done.
- **Observe-then-codify** (Rule 7). Visible in: 6-layer A–F architecture *emerged* during Phase 8 GPT analysis, then was codified into Constitution Article 1 at v3.3.0.

These meta-rules will continue to govern future KB extension (ActivityPub, additional design systems, alternate platforms) and product construction (pilot → distributable → plugins).

## License of this document

CC-BY-4.0. Attribution to KIM JIWOON (designbusan.ai.kr). Reuse the framing if useful for your own LLM-leveraged projects; the structure is not proprietary to Axismundi.

## Contact

- Site: https://designbusan.ai.kr
- This monorepo's source: (pending public release; held until license matrix finalized + theme/plugin separation complete)
