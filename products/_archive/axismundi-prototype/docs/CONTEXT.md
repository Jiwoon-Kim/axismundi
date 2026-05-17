# Axismundi Project Context

> **Phase 1B 끝. Phase 2 (마이크로블로그 prototype) 시작 대기.**
> 새 chat 또는 미래의 본인이 *지금 어디까지 왔고 다음에 뭘 하는가*를 빠르게 파악하기 위한 문서.

---

## 0. Quick navigation

| 알고 싶은 것 | 어디로 가야 하나 |
|---|---|
| 시스템이 어떻게 작동하는가 | `OVERVIEW.md` |
| 무엇이 바뀌었는가 | `CHANGELOG.md` |
| 어디까지 왔고 다음은 무엇인가 | `CONTEXT.md` ← 이 문서 |
| 마스터 spec | `prompt-v2.md` |
| 컴포넌트 spec | `M3-COMPONENT-SPECS.md` |
| 색상 raw 값 | `M3-COLOR-TOKEN.md` |
| 결정 rationale | `audit-en.md` |
| 제품 요구사항 | `BRIEF.md` |

---

## 1. Project overview

**Axismundi** = ActivityPub-style 마이크로블로그 SNS + WordPress 블록 테마 + M3 디자인 시스템.

### 1.1 목표 (BRIEF.md 요약)
- **Hero surface:** 마이크로블로그 피드 (ActivityPub timeline)
- **Personality:** 다크 우선 (fediverse client 스타일)
- **Internationalization:** Korean first-class (양국어 sample, KR/EN 혼합)
- **Modes:** Auto (system) + manual override
- **Interactivity:** Medium (like/follow/compose, expanding threads, dialog flows)

### 1.2 Phase 분할

| Phase | 산출물 | Tool | 상태 |
|---|---|---|---|
| **Phase 1A** | 디자인 시스템 v0.2.0 (14 컴포넌트) | Claude Design | ✅ 완료 |
| **Phase 1B** | M3 풀스펙 (33 컴포넌트) + Feed prototype | Claude Design | 🔄 진행 중 |
| **Phase 2** | WordPress 블록 테마 + 플러그인 | Claude Desktop | ⏳ 시작 안 함 |
| **Phase 3+** | ActivityPub federation, DB, 추가 surface | TBD | 🔮 미정 |

### 1.3 Tool 경계 (중요)
- **Claude Cowork** = 디자인 시스템 + WP 작업 *전부*. 정규 quota, 큰 산출물 한 번에.
- **본인 로컬** = VS Code Live Server + 브라우저 (시각 검증 전용)
- **GitHub repo** = 향후 디자인 시스템 공개 + Pages 배포 (시각 검증 demo URL)
- **Claude Design** — 사용 안 함 (본인 결정, Cowork + Live Server로 충분)
- **Claude Code CLI** — 요금 부담으로 사용 안 함

---

## 2. 현재 상태 (v1.0.0-rc1 — Phase 2B β 진행 중)

### 2.1 완성된 것 — 누적

**Phase 1B** (33 컴포넌트):
- 토큰 시스템 (3-tier ref / sys / comp + alias + layout)
- 33/33 컴포넌트 — Text field a11y refactor (`<div>` container + real `<label for>`), Date/Time picker visual structure, Carousel multi-browse 등 모두 포함
- Style guide 카탈로그 + scripts/style-guide.js (348 lines)

**Phase 2A α** (Style foundation 확장 + wp.org 진입점):
- `base.css` v0.3.0 — §6.6 `.u-vh`, §8 blockquote/hr/figure, §9 list/task list (`:has`), §10 table baseline, §11 code/kbd, §12 mark/del/ins/abbr, §13 native select (CSS-only chevron via twin linear-gradient + currentColor), §14 heading-anchor, §15 ::selection (+331 lines)
- `prose.css` v0.1.0 (354 lines) — `.prose` wrapper, owl rhythm, 풀 table 스타일, blockquote primary 강조, link always-underline
- `blocks.css` v0.1.0 (452 lines) — WP `wp-block-*` 코어 매핑, alignment helpers, 5 separator styles, 3 card variants (filled/elevated/outlined), 5 button styles fallback, segmented list
- `components.css` §28 button group **전면 재작성** — Pattern A (radio + label, single-select, CSS-only) + Pattern B (button + aria-pressed, multi-toggle, JS) 양쪽 지원, BUG #1/#2/#3 모두 fix
- `style.css` (테마 root) + `readme.txt` — wp.org 디렉토리 표준 메타데이터 (GPL-3.0-or-later, Tags 11개)
- 라이선스 일관 표기 — 3 style guide footer

**Phase 2B β** (Style guide 분리 + theme.js):
- `style-guide-blocks.html` (816 lines) — wp-block-* 카탈로그, 10 sections, alignment + 9 block 그룹
- `style-guide-prose.html` (508 lines) — 한글+영문 long-form sample, IntersectionObserver TOC scroll-spy 인라인 시연
- `style-guide.html` 보강 — 3 가이드 cross-navigation (sg-guide-nav), theme.js 로드, inline toolbar JS 제거
- `scripts/theme.js` (310 lines) — 5 IIFE: §1 nav drawer (`<dialog>` modal mode + focus trap + viewport guard), §2 submenu accordion (depth-N 재귀), §3 toolbar aria-pressed (+is-selected sync), §4 heading anchor injection (unicode slug), §5 TOC scroll-spy stub (Phase 2B 후반 finalize)

**Phase 2B β-fix1/2/2b** (Visual QA round 1/2):
- prose.css §4 — `<p> + <p>` 1.25em 단락 간격 (Tailwind Typography 표준값)
- prose.css §6 — blockquote `margin-block: var(--space-md)` 명시
- prose.css §11.6/§11.7 + blocks.css §6 — figure-as-wrapper figcaption + `is-style-vertical-borders` (셀 병합용) + `is-style-wrap` (가로 스크롤 opt-out)
- text-field disabled — `-webkit-text-fill-color` 38% (iOS Safari UA override), error 시 input/caret/password mask color → error token, suffix 있을 때 `:has(~ .text-field__suffix)` → `text-align: end`
- 가로 스크롤 fix — `.sg-main { min-inline-size: 0 }` (CSS Grid 1fr implicit min-content 문제)
- 3 가이드 themed thin scrollbars
- Time picker §34 전면 재작성 — `<input type="text" inputmode="numeric">` + `<fieldset>` + radio + label + AM/PM divider (`border-block-end: 1px solid outline`) + period frame `outline → border` (selected fill이 덮지 못하도록 box geometry로) + line-height: 0/1 (콜론 글리프 정렬)

### 2.2 검증 통과
- 토큰 100% — base/prose/blocks/components 모두 hex literal 0개
- Logical properties — `padding-inline / margin-block / inline-size / block-size / border-block-end` 일관
- Specificity — element-only (0,0,1) base 위에 prose (0,1,1)~(0,2,3), blocks (0,1,0)~(0,2,0), components (0,2,0)+ 깔끔 layer
- A11y — 3 가이드 모두 cross-nav `aria-current="page"`, button group radio 패턴, time picker fieldset/legend, `:has(~ .text-field__suffix)` RTL-safe
- JS — `node --check` 통과, 5 IIFE 격리

### 2.3 알려진 제약 (v1.5+ Deferred — CHANGELOG `[Unreleased] § Known issues`)
- **Date picker** 4건 시각 bug — `is-in-range` 끊김 (grid gap), `is-range-start + is-selected` shape 충돌, range color 두 셀 중간까지 미반영, 주(week) 넘어가면 padding까지 fill 안 됨. v1.0.0-rc1는 *시각 구조만* 제공 컴포넌트라 release 가능. Phase 3 picker JS interaction wiring 시 정리.
- **Time picker** 콜론 글리프 vertical alignment — 본인이 *시급도 낮음* 명시. v1.5에 정리.
- `:has()` 의존: 모던 브라우저 전용 (2025+ 표준)
- Outlined text-field notch는 `surface` 위 가정 (caveat 9.2)
- Token 명명 — Material Web `--md-{component}-{prop}` 패턴 안 따름 (caveat 9.9)
- v1.5+ Deferred 리스트 — `OVERVIEW.md` §9 Caveat 카탈로그 참조

### 2.4 미해결 외부 결정
- **GitHub Discussion** — `Automattic/wordpress-activitypub`에 게시 대기. Plugin 확장 가능 범위 + service scope 확정용. Draft = `outputs/github-discussion-draft.md`.
- **Phase 1.5 신설** — DB / S2S / FEP 설계는 prototype 후로 defer.

---

## 3. 다음 세션 (Phase 2B γ-3 또는 δ — 페이지 보강 마무리 또는 archive/search)

### 3.1 작업 순서 — γ-1, γ-2 완료, γ-3 또는 δ 선택

**완료한 것:**
- ✅ γ-1 (옵션 A) — `parts/header.html`, `parts/footer.html`, `parts/sidebar.html`, `front-page.html`, `404.html`
- ✅ γ-2 — `home.html` (블로그 인덱스 full features), `single.html` (article + TOC + comments), `index.html` (TT5 fallback)

**다음 chat 선택:**

| 옵션 | 작업 | 권장 시점 |
|---|---|---|
| **γ-3** | `archive.html` + `search.html` + `attachment.html` + `single-axismundi_profile.html` (CPT) | 페이지 prototype 완성 우선 |
| **δ-prep (themes/)** | Theme Handbook 청크화 chat 1회 | δ 본 작업 전 knowledge 정리 우선 |
| **δ** | γ-3 + 플러그인 호환 page templates (전제: knowledge prep 또는 본인이 spec 첨부) | knowledge 있어야 정확 |

내 권장 — **δ-prep (themes/)** 먼저. archive/search template hierarchy의 정확 spec이 있어야 작업 효율적. 본인 페이스 결정.

### 3.2 형식 결정 — Static HTML (γ 합의 그대로 유지)

`<!-- wp:* -->` 코멘트 *없음*. `.prose / .ax-* / wp-block-*` 클래스만 사용. Phase 3 PHP 통합 시 `render_block` 필터로 코멘트 inject 예정.

### 3.3 Parts 포함 패턴 (γ-1 결정 + γ-2에 적용 확인)

각 part의 markup이 templates에 inline 복사. parts/*.html 자체는 *standalone 시각 검증용 페이지*로 작동. γ-2의 home.html / single.html도 이 패턴 따라 header / drawer / footer 인라인 복사. Phase 3 PHP `template-part` include 한 번에 dedupe.

### 3.4 theme.js wiring (γ-1, γ-2에서 실전 검증 진행 중)

skeleton 5 IIFE → 실제 page markup 연결:
- header 햄버거: `data-toggle-nav` + `aria-controls="nav-drawer"` + `aria-expanded` ✅
- 모든 page에 `<dialog id="nav-drawer">` 추가 ✅ (front-page, home, single, 404; index 제외 — minimal fallback)
- sidebar / drawer 내 expandable nav: `.ax-nav-list__expand` + `aria-expanded` + `aria-controls` ✅ (γ-1 sidebar.html depth-3 재귀 시연)
- single.html에 IntersectionObserver TOC scroll-spy 인라인 (style-guide-prose.html과 동일 — theme.js §5 정식 finalize 시 외부화) ✅
- header `data-scrolled="true"` 자동 toggle — *미구현*. theme.js §6에 scroll listener 추가 후 박을 수 있음

### 3.5 wp.org 핸드북 §11 진척 — ✅ 4/4 충족

| 파일 | 상태 |
|---|---|
| `style.css` | ✅ (Phase 2A α) |
| `readme.txt` | ✅ (Phase 2A α) |
| `theme.json` | ✅ (Phase 1B baseline 잔존, Phase 3 finalize 예정) |
| `templates/index.html` | ✅ (γ-2 — TT5-style minimal fallback) |

→ 기술적으로 wp.org 디렉토리 제출 가능 상태 (PHP integration 없는 *static prototype*이므로 실제 제출은 Phase 3 후).

### 3.6 Template hierarchy fallback 패턴 (γ-2에서 결정)

```
홈페이지 요청:
  front-page.html (microblog feed)         ← ActivityPub 플러그인 활성 시
    ↓ 비활성/없을 시
  home.html (블로그 인덱스)                  ← 일반 블로그 fallback
    ↓ 없을 시
  index.html (TT5 minimal)                  ← ultimate fallback

단일 포스트 요청:
  single.html
    ↓ 없을 시
  index.html

archive (cat/tag/date) 요청:
  archive.html (γ-3에서 추가 예정)
    ↓ 없을 시
  index.html
```

이 fallback chain이 본인 의도 — *플러그인 확장 없을 때 site가 깨지지 않게*. WP가 자동 처리.

---

## 4. Knowledge base 점진 정리 plan

본인 합의 — *upfront 정리 X, 작업 영역과 동기화된 just-in-time 정리*. 단 *세션 간 맥락 잃지 않도록* 본 §4에 디렉토리 구조 + 정리 시점 명시 (이 자체가 manifest 역할).

### 4.1 디렉토리 계획

```
knowledge/
├── _manifest.md                  ← 전체 인덱스 (어떤 작업에 어떤 파일)
├── wordpress/
│   ├── block-editor/             ← Block Editor Handbook 청크
│   ├── themes/                   ← Theme Handbook 청크
│   ├── rest-api/                 ← REST API Handbook 청크
│   ├── plugins/                  ← Plugin Handbook 청크
│   └── apis/                     ← Common APIs Handbook 청크
└── activitypub/
    ├── protocol/
    └── feps/
```

각 파일 = 3,000–15,000 토큰 (한 번에 LLM이 읽을 수 있는 단위).

### 4.2 정리 시점 — 작업 영역과 동기화

| 시점 | 정리할 영역 | 트리거 |
|---|---|---|
| **γ 직전 (현재)** | — | Knowledge prep 안 함. 내장 WP 지식 + 작업 중 gap 식별 시 즉석 질문. 본인이 지금 turn에 받은 **5개 핸드북 목차**는 이 §4의 *디렉토리 구조 결정*에만 사용 (이미 반영). |
| **γ 끝나고 (δ 직전)** | `themes/` + `block-editor/core-blocks/` | δ에서 archive/search/attachment template hierarchy 결정 + WP core 블록 spec 확인 immediate value |
| **δ 끝나고 (Phase 3 직전)** | `block-editor/block-api/` + `rest-api/` + `plugins/` | `register_block_style()`, `theme.json` schema, hook 시스템, REST endpoints 정확 spec 필수 |
| **Phase 3 끝나고 (Phase 4 직전)** | `activitypub/` + `feps/` | FEP 스펙, HTTP signatures, instance discovery — 새 영역 |

### 4.3 본인이 받은 핸드북 목차 (현재 turn)

본 §4 디렉토리 결정 ground truth로 기록:

- **Theme Handbook** — Getting Started, Core Concepts, Global Settings (theme.json), Templates, Patterns, Features (Block Style/Stylesheets/Variations), Advanced (i18n/child/build), Releasing
- **Block Editor Handbook** — Getting Started (block 개발 환경, fundamentals), How-to Guides (blocks, dev platform, gutenberg data, curating, themes, widgets), Reference Guides (Block API, Core Blocks, Hooks, Interactivity, SlotFills, RichText, Theme.json, Components, Packages, Data Modules), Explanations
- **Plugin Handbook** — Basics, Security, Hooks, Privacy, Admin Menus, Shortcodes, Settings, Metadata, CPT, Taxonomies, Users, HTTP, REST, JS, Cron, i18n, Plugin Directory, Dev Tools
- **Common APIs Handbook** — Hooks, Responsive Images, Abilities API, Dashboard widgets, Database, i18n, Filesystem, Globals, Metadata, Options, Plugins API, HTTP, Quicktags, REST, Rewrite, Security, Settings, Shortcode, Site Health, Theme, Transients, XML-RPC, wp-config
- **REST API Handbook** — Key Concepts, Using REST API (auth/discovery/global params/linking/pagination), Extending (custom endpoints, controller classes), Reference (전체 endpoint), Requests, Glossary

→ 위 목차가 *δ 직전 themes/ chat*에서 **어떤 raw 자료를 본인이 첨부할지 식별**하는 기준. 작업 시점에 *이 §4 표 → 해당 raw 자료 첨부* 경로로 즉시 진입.

---

## 5. Archive — Phase 2A 진입 가이드 (완료 시점 기록)

> *이 섹션은 Phase 2A 진입 시점의 가이드였음. Phase 2A는 이미 완료 (§2 참조). 결정 rationale 보존을 위해 그대로 둠.*

### 5.1 작업 순서

```
Phase 2A — Style foundation 확장 (next chat — base/prose/blocks 분리)
  1. base.css 확장 — 부족한 element styling
     table, th, td, blockquote, hr, ol/ul/li (list-style),
     pre(code block padding), task list checkbox,
     heading anchor link 패턴, native select styling
  2. prose.css 신설 — `.prose` wrapper + 긴 글 typography
     p+p margin, h2-h6 콘텐츠 마진, blockquote 강조,
     img 최대 너비, table 풀 스타일링 (가장 중요)
  3. blocks.css 신설 — WordPress 코어 블록 (`wp-block-*`)
     paragraph/heading/list/quote/separator/table/image/gallery/columns/group/code

Phase 2B — Feed prototype (Phase 2A 후 새 chat)
  4. Skeleton — Feed view (timeline, post 컴포지션)
  5. Profile view (헤더 + 프로필 + 게시물 그리드)
  6. Article view (long-form + 댓글) — `.prose` 적용 테스트장
  7. Navigation (FAB compose, nav bar, search)
  8. Theme switcher polish + responsive 검증

Phase 1.5 — 병행 또는 prototype 후
  9. GitHub Discussion 게시 → 답 받기
  10. Service scope 세부 확정
  11. DB / S2S / FEP 설계

Phase 3 — WordPress 통합
  12. WordPress block theme 빌드
  13. Generic blocks plugin (`m3-blocks`)
  14. Domain blocks plugin (`axismundi-blocks`)
```

### 3.2 파일 구조 결정 (Phase 2A 기준)

```
Axismundi/
├── style-guide.html        (그대로, 33 컴포넌트 카탈로그)
├── stylesheets/
│   ├── tokens.css          (그대로)
│   ├── base.css            (확장 — table/blockquote/hr/list/select 등)
│   ├── prose.css           (NEW — .prose wrapper, 긴 글 typography)
│   ├── blocks.css          (NEW — wp-block-* 매핑)
│   └── components.css      (그대로, 5,636줄)
├── scripts/style-guide.js
├── prototype/              (Phase 2B에서 시작)
│   ├── feed.html
│   ├── profile.html
│   └── article.html
└── docs/...
```

**분리 이유:**
- `base.css` = 모든 페이지에 항상 로드되는 reset + element defaults
- `prose.css` = Article view + classic editor 본문에만 로드 (`.prose` scoped)
- `blocks.css` = WordPress 블록 출력 전용 (Phase 3에서 theme.json이 enqueue)
- 페이지별 필요한 것만 로드 + specificity 충돌 방지 + WP 통합 시 깔끔

### 3.3 Phase 2A — markdown/classic editor 호환 요구사항

본인 정의 — markdown 문법 범위 안에서 보장해야 할 elements:
- h1-h6, p, ol, ul, strong, em (italic), code, blockquote, a (link), img, hr, **table, th** ← table 가장 중요
- 추가: strong, italic, ordered/unordered lists, reference-style links, heading IDs (anchor), task lists
- HTML: `<select>` + `<option>` 드롭다운 박스

이미 base.css에 있는 것 (확인됨): h1-h6 typography, p, a, a:hover, code/pre/kbd/samp, strong/b, em/i, select reset, img.
누락: **table, th, td, blockquote, hr, ol/ul list-style, pre code-block 패딩, task list checkbox, heading anchor pattern, native select 풀 디자인.**

### 3.4 첫 메시지 초안 (다음 chat용)

> Axismundi v1.0.0-rc1 — Phase 2A: Style foundation 확장.
>
> 첨부: Axismundi-phase-1b-final.zip (Phase 1B 끝난 baseline)
>
> 이번 chat 작업:
> (1) base.css 확장 — table/blockquote/hr/list/pre/select 등 누락 elements
> (2) prose.css 신설 — `.prose` wrapper, 긴 글 typography (table 풀 스타일 핵심)
> (3) blocks.css 신설 — WP `wp-block-*` 코어 블록 매핑
>
> 시작 — (1) base.css 확장부터. 한 turn 한 step.
> 다음 turn = (2) prose.css.

### 3.5 Phase 2A 작업 시 표준
- **Token 사용 100%** — `var(--md-sys-*)`, `var(--space-*)`. Hex 0개.
- **Logical properties** — `padding-inline`, `border-inline-start` (RTL 가능).
- **Element selector — base.css에선 OK**, prose.css에선 *반드시 `.prose` 안에서만* (`.prose h2 { ... }`).
- **Specificity 최소화** — base는 (0,0,1), prose는 (0,1,1) 정도. components.css의 (0,2,0)+ 와 충돌 없도록.
- **A11y first** — table은 `<thead>/<tbody>/<th scope>`, heading anchor는 `<h2 id="...">` + visible link.
- **Mobile-first** — table은 모바일 가로 스크롤 대응 (`.prose .table-wrapper { overflow-x: auto; }` 패턴).

### 3.6 select 컴포넌트 결정

**Phase 2A**: native `<select>`만 styling (base.css). Text field outlined 비슷한 시각 + custom arrow icon overlay.

**Phase 2B 또는 그 후**: 필요하면 `.ax-select` custom 컴포넌트 (`.text-field` trigger + `.ax-menu` dropdown 결합) 추가. 현재 33 카탈로그 *밖*이라 *진짜 필요할 때만* 추가.
## 6. 작업 흐름 권장

### 4.1 Quota 관리
- **Claude Cowork 사용** — 정규 quota, 큰 산출물 한 번에 가능
- Phase 1B/1.5 + Phase 2 모두 Cowork에서 진행 (Claude Desktop / Claude Design 미사용)
- 통상 chunk 사이즈 ~300줄 (CSS) + sidebar/section update — stream 부담 없음

### 4.2 분할 전략 (실제 진행 — Phase 1B 끝)
- Tier 1 (7개) ✅ — E1/E2/E3/E4
- Tier 2 (4개) + Text field refactor ✅ — F1/F2 + 리팩토링 turn
- Tier 3 (5개) ✅ — G1 (List+Progress) / G2 (Button group+Toolbar) / G3 (Carousel)
- Tier 4 (4개) ✅ — H1 (FAB menu) / H234 (Split+Date+Time, 한 turn 묶음)
- 누적 fix turn 2회 + Compose-derived Date/Time picker 보강 1 turn

→ Phase 2 (prototype) — 새 chat에서 시작

### 4.3 매 chunk 끝나고
- self-check 표 작성 (token 사용, BEM, state-layer, motion, disabled 패턴)
- visual QA — 본인이 VS Code Live Server에서 직접 확인
- 발견된 이슈 → CHANGELOG fix entry로 기록

### 4.4 산출물 흐름
1. Cowork에서 작업 → outputs/에 zip 출력
2. 본인 다운로드 → `Axismundi/` 폴더에 압축 해제 (덮어쓰기)
3. Live Server reload → 시각 확인
4. 다음 chunk 진행 또는 fix turn

---

## 7. Phase 2 진입 시 추가 결정사항

> 다음 세션 *작업 안 함*, 그러나 Phase 2 진입 시 한 번에 결정 필요.

### 5.1 Block namespace 분할 (BLOCK-COMPONENT-MAP.md §0.2)
- **Theme**: `axismundi`
- **Generic blocks plugin**: `m3-blocks` (`m3/*` namespace) — generic M3 컴포넌트
- **Domain blocks plugin**: `axismundi-blocks` (`axismundi/*` namespace) — SNS 도메인 composites

### 5.2 플러그인 배포 전략
- v1.0 = 테마 안에 bundle (TGM Plugin Activation 패턴)
- v1.5+ = 분리 배포 (별도 zip)

### 5.3 Form 컴포넌트 (E bucket)
- Theme이 *외형 정의*만, 실제 form 처리는 외부 플러그인 (CF7 / WPForms / Gravity)
- v0.3.0에 Tier 2 form 컴포넌트 빌드 = *외형 정의 layer*. Phase 2에선 플러그인 output에 같은 클래스 적용.

### 5.4 CPT
- `axismundi_profile` — ActivityPub Actor 매핑용
- 다른 CPT (post type)은 `core/post` 사용 (ActivityPub Note 매핑)

---

## 8. 결정 archive (요약)

자세한 rationale은 `audit-en.md`, 변경 history는 `CHANGELOG.md` 참조. 여기는 *주요 결정 한 줄 요약*.

| 결정 | 선택 | Rationale |
|---|---|---|
| 시스템 baseline | M3 official (Plum seed 폐기) | Material Theme Builder 결과가 spec과 drift, deprecated 토큰 포함 |
| Token format | hex (`#RRGGBB`) | `color-mix()` 호환, RGB channels 불필요 |
| State layer | Pattern A (currentColor + opacity) | M3 §0.7 원칙, variant마다 자동 색상 |
| Motion | M3 Expressive spring physics | duration alias보다 정확, native API 호환 |
| Shape scale | 10 tier + directional | M3 Expressive 정확 spec |
| State opacity | 0.08/0.10/0.10/0.16 | M3 Expressive correct (Phase-1 0.12는 M3 1.x 값) |
| KR typography | `keep-all` + `anywhere` + `1.6` | 한글 단어 단위 + 영문 강제 줄바꿈 + 혼합 라인 안전 |
| Theme mode | `prefers-color-scheme` + `[data-theme]` override | Auto + manual 둘 다 지원, FOUC 회피 |
| Component priority | Bucket A/C/D/E/F (BLOCK-COMPONENT-MAP) | Phase 2 WP 매핑 경로 사전 결정 |
| Style guide scope | M3 풀스펙 (33 컴포넌트) | 시스템 *완전성* — 디자인 시스템은 카탈로그가 시각 truth-source |
| Form 컴포넌트 처리 | 외형 정의만 (E bucket) | Phase 2에서 플러그인 output에 styling 적용, 직접 form 처리 안 함 |

---

## 9. Appendix — 다음 세션 즉시 action

```
□ Cowork 새 chat 시작 — 토큰 효율 위해 새로 시작 (현재 chat 매우 김)

□ 첫 메시지에 zip 업로드 (Phase 1B 마무리 zip — `Axismundi-phase-1b-final.zip` 등)
  + §3.2 초안 paste

□ Phase 2 첫 작업 — Feed view skeleton + 단일 게시물 카드 markup

□ 단계별 진행 — 한 turn 한 view skeleton (Feed → Profile → Article)

□ 병행 또는 prototype 후:
  - GitHub Discussion 게시 (Automattic/wordpress-activitypub)
  - Service scope B 세부 확정
  - Phase 1.5 (DB / S2S / FEP) 설계

□ Phase 2 마지막에 CHANGELOG.md v1.0.0 entry + OVERVIEW.md → v1.0.0
```
