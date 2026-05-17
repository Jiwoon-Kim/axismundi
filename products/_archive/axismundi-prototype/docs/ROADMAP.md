---
doc_id: axismundi-mvp-build-roadmap
type: product-roadmap
phase: phase-9-product-build
status: active
captured: 2026-05-10
supersedes_in_priority:
  - phase-8-knowledge-accumulation
  - phase-8-doctrinal-frontier-mapping
relationship_to_kb:
  - kb_role: "engine-room manual (reference)"
  - kb_status: "frozen for active forward work; available for lookup"
---

# Axismundi MVP Build Roadmap

Phase 8 (KB 구축) 종료 선언. Phase 9 (제품 빌드) 시작.

이 문서는 *지식 챕터*가 아니라 *제품 로드맵*입니다.
WordPress의 모든 메커니즘을 이해할 필요는 없습니다 —
Axismundi를 출시할 만큼만 정확히 조립하면 됩니다.

---

## 0. Phase 전환 선언

| Phase | 역할 | 상태 |
|---|---|---|
| Phase 2B δ-2 | 정적 프로토타입 | 완료 |
| Phase 8 (KB) | WordPress 구조 이해 + 헌법 grammar | **종료, 참조용 archive** |
| **Phase 9 (Product Build)** | **Axismundi MVP 출시** | **시작** |

**Phase 8 KB의 역할 변경**:
- 이전: 활성 작업 산물 (35 chunks + 3 audits + 1 frontier map)
- 이후: 엔진룸 매뉴얼 (필요 시 lookup, 더 이상 확장 안 함)

**중단 항목** (Phase 8 의제였으나 Phase 9에서 deprioritize):
- Ecosystem-scale doctrine
- External federation theory deep dives
- Constitutional cartography
- Frontier path 2/3 (P1 문서 참조용으로 보존)

---

## A. MVP Scope (최소 출시 가능 범위)

**Axismundi MVP** = 다음 4개의 결합:

1. **Material Design 3 Block Theme** — 토큰 기반 디자인 시스템
2. **Identity / Brand Layer** — 개인 정체성 / 프로필 / 브랜드 표면
3. **ActivityPub-Compatible Surfaces** — 페디버스 친화적 콘텐츠 구조
4. **Korean-First Authoring** — 한국어 콘텐츠 / 라틴 mixed-script 처리

**Out of MVP** (deferred to Tier B):
- Travel CPT / 여행 콘텐츠 구조
- Schema markup 자동 생성
- Local maps / 부산 지역 데이터
- Reviews / 평가 시스템
- Moderation tooling
- Social graph 가시화

**MVP 성공 기준**:
- WordPress.org에서 다운로드 가능한 블록 테마
- 깔끔한 M3 시각 언어
- 게시한 글이 ActivityPub 네트워크에서 잘 보임
- 한국어/영어 mixed content 처리 정상

---

## B. Theme Surface — 빌드 우선순위

### B.1 — theme.json (M3 토큰 substrate)

**우선순위: 1순위 (모든 것의 substrate)**

다룰 항목:
- `version: 3`
- `settings.color.palette` — M3 color roles (primary / secondary / tertiary / surface / etc.)
- `settings.color.duotone` — 옵션
- `settings.typography.fontFamilies` — 한국어/영어 dual stack
- `settings.typography.fontSizes` — M3 type scale
- `settings.spacing.spacingScale` — M3 spacing tokens
- `settings.layout.contentSize` / `wideSize`
- `settings.appearanceTools` — 자동 노출 컨트롤
- `styles.elements.h1..h6` — M3 typography styles
- `styles.elements.button` — M3 button styles
- `styles.elements.link`
- `styles.blocks.core/...` — 블록별 M3 스타일

**KB 참조** (작성 시):
- `theme-config.settings.*` chunks
- `theme-config.styles.*` chunks
- `style-engine.preset-materialization`
- `style-engine.theme-json-source-layering`

### B.2 — Templates (FSE 블록 테마 구조)

**우선순위: 2순위 (콘텐츠 표면)**

필수 templates (`templates/*.html`):
- `index.html` — 폴백
- `front-page.html` — 메인
- `single.html` — 단일 글
- `singular.html` — 글/페이지 공통
- `page.html` — 페이지
- `archive.html` — 아카이브 일반
- `archive-author.html` — 작성자 페이지 (identity layer 핵심)
- `404.html`
- `search.html`

필수 template parts (`parts/*.html`):
- `header.html` — 사이트 헤더
- `footer.html` — 사이트 푸터
- `comments.html`
- `post-meta.html` — 메타정보 (날짜/작성자/카테고리)

**KB 참조**:
- `site-building.template-hierarchy-and-resolution`
- `theme-config.templateParts`
- `theme-config.customTemplates`

### B.3 — Block Style Variations

**우선순위: 3순위 (디자인 일관성)**

`registerBlockStyle`로 등록할 M3 변형:
- Buttons: `default` / `outlined` / `text` / `filled` / `tonal`
- Headings: `display` / `headline` / `title` / `body`
- Cards: `elevated` / `filled` / `outlined`
- (필요 시 추가)

**KB 참조**:
- `block.block-styles-registration`
- `style-engine.per-block-style-attribution`

---

## C. Block / Component Priority — 커스텀 블록

### C.1 — Tier A 블록 (MVP 필수)

| 블록 | 목적 |
|---|---|
| `axismundi/profile-card` | 작성자 프로필 카드 (identity layer 핵심) |
| `axismundi/social-links` | 페디버스/소셜 링크 (ActivityPub 친화) |
| `axismundi/post-meta-extended` | 한국어 친화 메타 표시 |
| `axismundi/m3-button` | M3 버튼 변형 (필요 시 — block style로 충분할 수도) |

각 블록 빌드 절차:
1. `block.json` 작성 (apiVersion: 3)
2. `edit.js` + `save.js` (또는 `render.php` for dynamic)
3. `style.css` + `editor.css`
4. `wp-scripts build`

**KB 참조**:
- `block.edit-and-save-contracts`
- `block.json-attributes-core`
- `block.api-version-and-block-evolution`
- `build-tooling.wp-scripts`

### C.2 — Tier B 블록 (deferred)

- 여행 후기 카드
- 지도 임베드
- 평점 위젯
- 갤러리 변형

---

## D. ActivityPub Integration Points

ActivityPub WordPress 플러그인을 *전제*로 작업합니다 (자체 구현하지 않음).

### D.1 — Theme이 제공해야 할 것

**Author actor 표면**:
- `author.html` template — ActivityPub actor 페이지로도 사용됨
- 프로필 정보 (display name / bio / avatar / header)
- 페디버스 핸들 표시 (`@user@domain`)
- Follow 버튼 (플러그인 제공 UI)

**Note / Article 스타일링**:
- 짧은 글 (`<note>` 형) — 마이크로블로그 스타일
- 긴 글 (`<article>` 형) — 풀 글 스타일
- 두 모드의 시각적 구분

**Federation metadata**:
- Open Graph 태그 (플러그인이 자동 처리하지만 theme에서 보강)
- 적절한 `<meta>` 정보
- 인용/공유 시 잘 보이는 thumbnail / excerpt

### D.2 — 통합 검증 단계

1. ActivityPub 플러그인 설치
2. Mastodon 등에서 follow 시도
3. 프로필 표시 확인
4. 글 게시 → 페디버스 전파 확인
5. 외부에서 본 모습 점검 (Mastodon viewer)

**KB 참조** (필요 시):
- `rest-api.authentication-and-permission-callbacks`
- `template-tags.render-context`
- 기타 federation-adjacent chunks

---

## E. Deferred Complexity (Tier B — 명시적 보류)

다음 항목은 **MVP 출시 후** 별도 단계:

### E.1 — Travel / 부산 지역 layer
- Travel CPT
- 장소 메타 (위경도 / 주소 / 분류)
- 지도 통합 (Leaflet / Mapbox / 카카오맵)
- 여행 후기 schema

### E.2 — Reviews / Ratings
- 별점 시스템
- 리뷰 메타데이터
- 리뷰 schema markup

### E.3 — Social Graph
- 팔로우/팔로잉 시각화
- 인용 그래프
- 토픽 클러스터링

### E.4 — Advanced Federation
- ActivityPub objects 외 (Question, Event, etc.)
- IndieWeb webmentions
- Bridgy fed 등 외부 통합

### E.5 — KB 확장
- Phase 8 KB는 동결 상태
- 새 chunk 작성하지 않음
- 필요 시 lookup만

---

## F. GitHub Repo Architecture

### F.1 — 권장 monorepo 구조

```
axismundi/
├── axismundi-theme/                 # WordPress block theme (메인)
│   ├── theme.json
│   ├── style.css
│   ├── functions.php
│   ├── templates/
│   ├── parts/
│   ├── patterns/
│   ├── assets/
│   └── readme.txt
├── axismundi-design-system/         # M3 토큰 정의 (theme.json 생성 source)
│   ├── tokens/
│   │   ├── color.json
│   │   ├── typography.json
│   │   ├── spacing.json
│   │   └── motion.json
│   ├── scripts/
│   │   └── build-theme-json.js      # 토큰 → theme.json 변환
│   └── docs/
├── axismundi-blocks/                # 커스텀 블록 (Tier A)
│   ├── src/
│   │   ├── profile-card/
│   │   ├── social-links/
│   │   └── post-meta-extended/
│   ├── package.json
│   └── webpack.config.js (optional)
├── docs/
│   ├── roadmap.md                   # 이 문서
│   ├── style-guide.md               # M3 적용 가이드
│   ├── activitypub-integration.md   # ActivityPub 통합 노트
│   └── archive/
│       └── kb/                      # Phase 8 KB chunks (참조용)
└── README.md
```

### F.2 — 분리 vs 통합 결정

- **별도 repo**: theme / design-system / blocks 각자 독립 배포 가능 (WordPress.org 분리 등록)
- **monorepo**: 단일 저장소에서 함께 개발, 배포 시 분리 빌드

**MVP 시점 권장**: monorepo로 시작 → 출시 임박 시 분리 결정.

### F.3 — 배포 채널

- `axismundi-theme` → WordPress.org 테마 디렉토리
- `axismundi-blocks` → WordPress.org 플러그인 디렉토리 (분리 등록 시)
- `axismundi-design-system` → npm package (선택, 다른 프로젝트 재사용 시)

---

## G. 즉시 다음 액션 (이번 주 내)

### G.1 — 의사결정 필요

- [ ] Monorepo vs separate repos
- [ ] 자체 빌드 도구 vs `@wordpress/create-block` 사용
- [ ] 한국어 우선 vs i18n-ready 동시
- [ ] 라이센스 (GPLv2 (WP 호환) / MIT)

### G.2 — 첫 빌드 단계

1. **저장소 초기화** — `axismundi/` 생성, `.gitignore`, README
2. **theme.json 첫 버전** — M3 색상 + 타이포 + 스페이싱 토큰
3. **최소 templates** — index / single / page / archive / 404
4. **최소 parts** — header / footer
5. **로컬 WordPress 환경** — `wp-env` 또는 Local
6. **첫 시각 확인** — 테마 활성화 후 기본 글 렌더링 확인

### G.3 — Phase 8 KB archive 처리

- [ ] `knowledge/` 폴더를 `docs/archive/kb/`로 이동 (또는 그대로 두고 reference만)
- [ ] `_meta/` audits를 `docs/archive/audits/`로 이동
- [ ] README에 Phase 8 KB가 frozen reference임을 명시
- [ ] 추후 chunk 작성 충동 발생 시 **단호히 거절** (메모리에 기록)

---

## H. 작업 원칙 (Phase 9 doctrine)

Phase 8.27의 *"Reference when clarifying. Omit when unnecessary. Deploy naturally."* 는 *KB 작성 doctrine*이었습니다. Phase 9에서는 다음 doctrine으로 대체:

> *"Build the smallest thing that ships. Reference KB only when stuck. Deferred is not denied — it's just not now."*

**Phase 9 anti-patterns**:
- 새 KB chunk 추가 충동 (Phase 8.49? 라는 표시가 머릿속에 떠오르면 거절)
- 빌드보다 doctrine 정밀화에 시간 사용
- "이걸 더 이해해야 빌드 가능"이라는 합리화
- Tier B 항목으로 scope creep

**Phase 9 success patterns**:
- 코드가 굴러간다
- 시각적으로 보인다
- WordPress.org에 업로드 가능한 zip이 생긴다
- 누군가 다운로드해서 활성화 가능하다

---

## I. KB의 새 역할 — Engine-Room Manual

Phase 8 KB의 35 chunks + 3 audits + 1 frontier map은 *동결*되지만 *유용*합니다.

**언제 KB를 참조하는가**:
- 빌드 중 특정 메커니즘 작동 방식이 모호할 때
- 디버깅 중 "이게 왜 이렇게 되지?" 질문 시
- 새 기능이 적절한 패턴인지 확인 시

**언제 KB를 참조하지 않는가**:
- *학습을 위해* 읽기 (이미 충분)
- *완전성을 위해* 새 chunk 작성하기 (불필요)
- *doctrinal precision을 위해* 기존 chunk 재작성하기 (anti-pattern)

KB는 도구입니다. 더 이상 작품이 아닙니다.

---

## J. 마무리

WordPress의 가장 깊은 메커니즘 grammar까지 이해한 것은 의미가 있었습니다.
하지만 그 이해의 가치는 *제품 출시*에서 realize됩니다.

Phase 8은 *왜 WordPress가 이렇게 움직이는지* 답했습니다.
Phase 9는 *그 위에서 무엇을 만들 것인지* 답해야 합니다.

**다음 단계**: Section G의 즉시 액션부터.

Phase 8.49는 없습니다. Axismundi v0.1이 있습니다.

---

## 참조

이 문서는 다음을 supersede 합니다 (활성 작업 우선순위에서):
- Phase 8.M3 (governance geometry audit)
- Phase 8.P1 (frontier mapping)
- 모든 추가 KB chunk 작성 의제

다음을 보존합니다 (참조용):
- `knowledge/wordpress/...` 35개 chunks
- `knowledge/wordpress/_meta/...` 4개 audit/synthesis 문서
- 기타 `_meta/` 문서

다음을 시작합니다:
- `axismundi/` repo 초기화 (Section G.2)
- M3 design system 정의
- Block theme 첫 buildable 버전
