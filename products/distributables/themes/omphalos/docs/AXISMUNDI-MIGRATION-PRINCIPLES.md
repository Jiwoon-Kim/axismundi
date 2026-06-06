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
