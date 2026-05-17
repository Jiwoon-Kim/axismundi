# Axismundi

Axismundi는 WordPress 바인딩과 파일럿 테마 경로를 가진, 온톨로지 기반
Material 3 디자인 시스템입니다.

현재 상태는 공개 준비 단계입니다. Wave 1 컴포넌트 커버리지는 닫혔고,
검증 파이프라인은 통과하며, 다음 단계는 GitHub 저장소 생성과 GitHub Pages
공개입니다.

- 작성자 및 최종 결정권자: KIM JIWOON (designbusan.ai.kr) — Busan, Korea. [AUTHORSHIP.md](AUTHORSHIP.md)를 참고하세요.
- 아키텍처 기준: [CONSTITUTION.md](CONSTITUTION.md)
- 현재 진행 상태: [CURRENT-STATE.md](CURRENT-STATE.md), [ROADMAP.md](ROADMAP.md)
- English README: [README.md](README.md)

## 현재 상태

```txt
v3.5.12  Wave 1 컴포넌트 완료        9 / 9
v3.5.13  Wave 1 cleanup 완료         #32 / #33 / records
v3.5.14  공개 준비                   진행 중
v3.5.15  GitHub 저장소 + Pages        다음 단계
v3.6.0   Ontology Theme Pilot         예정
```

Wave 1은 Button, Icon button, FAB family, Button group, Card, Text field,
Search bar, List, Carousel을 포함합니다. Matrix state, Ripple v2, pill radius,
size variants, List token 정리 같은 기반 보정도 닫힌 상태입니다.

## 이 저장소의 목적

Axismundi는 단순한 WordPress 테마가 아닙니다. 플랫폼 온톨로지, 디자인 시스템
토큰, 컴포넌트 계약, 공개 가능한 reference implementation을 연결하는 구조입니다.

현재 디자인 시스템은 Material Design 3이고, 현재 플랫폼 바인딩은 WordPress입니다.
하지만 구조 자체는 다른 디자인 시스템이나 다른 플랫폼 바인딩으로 확장될 수 있도록
분리되어 있습니다.

## 구조

저장소는 여섯 개 레이어를 가집니다.

| Layer | Directory | 역할 |
|---|---|---|
| A. Corpus | `corpus/` | 원문과 정제된 근거 문서 |
| B. Atlas | `atlas/` | 규칙 기반 지식과 감사 기록 |
| C. Core | `core/` | 플랫폼 / 디자인 시스템 온톨로지 |
| D. Bindings | `bindings/` | 온톨로지 간 번역 |
| E. Products | `products/` | reference implementation과 향후 배포물 |
| F. Tools | `tools/` | 빌더, 생성기, 검증기 |

Public surface는 네 tier로 다룹니다.

| Tier | 의미 |
|---|---|
| Baseline | 안정된 시각 primitive: `components.css`, `tokens.css`, styleguide source |
| Lab | 모듈 검증 표면: audit, pattern page, runtime experiment |
| Public | downstream consumer가 의존할 수 있는 안정 표면 |
| Plugin | editor UI, custom block, federation, 외부 데이터, 통합 동작 |

Publishing surface는 authority가 아니라 mirror입니다. 원본을 고친 뒤 generator를
실행해야 합니다.

## 공개 표면

| Surface | 상태 | Source authority |
|---|---|---|
| `index.html` | 프로젝트 landing | README / project docs |
| `styleguide/` | 생성된 component styleguide mirror | `products/reference-implementations/axismundi-lab/style-guide*.html` |
| `templates/` | 예정된 page-layout / template preview route | 향후 `products/reference-implementations/axismundi-lab/templates/` |

`styleguide/`는 생성물입니다. 직접 편집하지 않습니다.

## 실행 방법

개발 의존성 설치:

```powershell
npm install
```

검증 실행:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

styleguide publish mirror 재생성:

```powershell
python .\tools\generators\publish_styleguide.py
```

기대 결과:

```txt
Overall: 1.000 (PASS)
A schema:  1.000
B theme:   1.000
C css:     1.000
D runtime: 1.000
```

## WordPress / 블록 테마 / 플러그인 경계

WordPress 바인딩은 `bindings/wordpress-material3/`에 있습니다. 현재 검증 대상은
`products/reference-implementations/ontology-theme-pilot/`입니다.

테마가 할 수 있는 일:

- core block 스타일링,
- block style variation 등록,
- template part와 layout slot 제공,
- progressive interaction CSS/JS enqueue.

플러그인이 해야 하는 일:

- durable custom block,
- editor UI,
- icon picker registry,
- ActivityPub 같은 외부 프로토콜 연동,
- 콘텐츠 파싱과 데이터 저장.

## 안정성 메모

- Wave 1 public-surface component audit는 완료되었습니다.
- Lab module pattern HTML은 기본적으로 public API가 아니라 검증 표면입니다.
- `styleguide/`는 생성된 mirror라서 재생성할 수 있습니다.
- GitHub 저장소 생성과 GitHub Pages 활성화는 v3.5.15 범위입니다.
- WordPress.org 제출 패키지는 아직 구성되지 않았습니다.

## 라이선스

Axismundi는 표면별 multi-license 구조를 사용합니다.

- code / theme / tooling: GPL-3.0-or-later
- documentation: CC BY-SA 4.0
- ontology / binding data: CC BY-SA 4.0
- third-party assets: upstream license 보존

[LICENSE](LICENSE), [LICENSE-CC-BY-SA-4.0.md](LICENSE-CC-BY-SA-4.0.md),
[LICENSE-MATRIX.md](LICENSE-MATRIX.md), [NOTICE.md](NOTICE.md)를 참고하세요.
