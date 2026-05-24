# Webdesign Decision Matrix Ontology — Skeleton

Source provenance:

- Source book outline: `corpus/source/webdesign-craftsman-2026/CONTENTS-OUTLINE.md`
- User-authored raw notes (out-of-repo, structured by user): `C:\Users\thaum\dev\wdd.md`
- This skeleton: user-note digest organized per book Part/Chapter/Section + Decision Matrix mapping hints + Codex research TODO markers.

Authoring policy:

- User-authored notes only; no copyrighted book body text was pasted into the repository.
- Korean source vocabulary preserved where the user used it; English glossary added where helpful.
- TBD markers indicate gaps where Codex web research is expected to fill detail in a follow-up pass.

How to read each Section block:

```txt
### SECTION
- User note (digest)         : user's own paraphrased notes from the book
- Decision Matrix mapping     : sketch of workflow_stage / decision_point / Axismundi_route
                                fields for the eventual matrix (per Phase 0 plan schema)
- Codex research TODO        : items Codex should web-research to enrich Phase 1 evidence
- User open question         : marked with ">" in user notes; surfaced for cycle decision
```

This skeleton is **prep material**. It is not a Decision Matrix yet. Phase 1 of v3.6.25 will classify each entry against the schema in `docs/v3.6.25/WEBDESIGN-DECISION-MATRIX-ONTOLOGY-PHASE-0-PLAN.md` and route into `corpus/atlas/core` per [[project-axismundi-written-material-ontology]].

---

## PART 01 — 프로토타입 기초데이터 수집 및 스케치

### CHAPTER 01 — 기초데이터 및 레퍼런스 수집

#### SECTION 01 — 기초데이터와 레퍼런스 데이터 수집

- User note: TBD (not present in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `intake`
  - decision_point: what to collect as baseline data vs reference data
  - Axismundi_route: `corpus` (data collection methodology)
- Codex research TODO:
  - 기초데이터(baseline data) vs 레퍼런스 데이터(reference data) 정의 차이
  - 수집 도구/방법 (web crawling, competitive analysis, etc.)
  - 한국 웹디자인기능사 시험 정의 표준 (KS standard if any)

#### SECTION 02 — 프로젝트 개념 및 구조

- User note:
  - **PMBOK** (Project Management Body of Knowledge):
    - 5 Process Groups: 착수 / 계획 / 실행 / 감시 및 통제 / 종료
    - 10 Knowledge Areas: 통합관리, 범위관리, 일정관리, 비용관리, 품질관리, 인적자원관리, 의사소통관리, 리스크관리, 조달관리, 이해관계자 관리
  - **PMBOK-based 웹디자인 프로세스**: 프로젝트 기획 → 웹사이트 계획 → 사이트 구축/디자인 개발 → 테스트 및 디버깅 → 배포/홍보/유지보수
    - Outcome → output → artifact 분류
  - **프로젝트 관련 문서**: 제안요청서(RFP) / 제안서(Proposal) / 프로젝트 계획서 / 최종보고서
  - **MaRMI-III**: CBD (Component-Based Development) 방법론
    - 4 공정 / 30 활동
    - ISO/IEC 12207 기반
    - 개발공정 계층화
    - UML 기반 객체지향 모델링
  - **UML diagrams**: Class / Sequence / Usecase / State / Activity
  - **WBS** (Work Breakdown Structure):
    - 프로젝트 산출물 최상위 → 단계별 주요 산출물
    - 트리 구조 다이어그램
    - 주요 요소: 프로젝트 목표 / 주요 단계 / 작업 패키지 / 작업 할당
    - 작성 절차: WBS 작성 → 작업 순서 결정 및 의존성 정의 → 작업 기간 추정 → 자원 할당 → Gantt Chart 등 스케줄링 도구
- Decision Matrix mapping:
  - workflow_stage: `concept` (프로젝트 정의 단계)
  - decision_point: 프로젝트 관리 방법론 선택 (PMBOK vs MaRMI-III vs Agile vs custom)
  - input_evidence: RFP, 이해관계자 요구사항
  - output_artifact: 제안서, 프로젝트 계획서, WBS, Gantt
  - Axismundi_route: `core` (workflow rule), `atlas` (deliverable atlas)
- Codex research TODO:
  - PMBOK 최신 edition (현재 7th edition 추정) — version snapshot reference
  - MaRMI-III 원문 specification URL (한국 SW산업진흥원/TTA)
  - WBS 예시 templates (Gantt chart / mind map / tree)
  - ISO/IEC 12207 개요 + Axismundi cycle 모델과의 cross-reference

#### SECTION 03 — 산업재산권과 저작권

- User note: TBD (not present in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `intake`, `handoff_report` (release seal context)
  - decision_point: 자료 사용 권한, attribution 의무
  - Axismundi_route: `core` (Axismundi LICENSE-MATRIX cross-reference), `decisions_candidate` (specific project licensing)
- Codex research TODO:
  - 산업재산권 (특허/실용신안/디자인/상표) vs 저작권 차이
  - 한국 저작권법 웹디자인 관련 조항
  - CC BY-SA 4.0 / GPL-3.0 / Apache 2.0 등 OSS 라이선스 대조 (Axismundi LICENSE-MATRIX와 연결)
  - Axismundi `compare/brand-assets-research/` DO-NOT-SHIP 정책 link

### CHAPTER 02 — 아이디어 스케치

#### SECTION 01 — 프로젝트 기획 의도와 아이디어 스케치

- User note:
  - **아이디어 발상법**:
    - Brain Storming
    - How Might We
    - **SCAMPER** (Substitute, Combine, Adapt, Modify/Magnify/Minify, Put to other uses, Eliminate, Reverse/Rearrange)
- Decision Matrix mapping:
  - workflow_stage: `concept`
  - decision_point: 아이디어 발산 vs 수렴 방법 선택
  - Axismundi_route: `core` (ideation method library)
- Codex research TODO:
  - SCAMPER 한국어 사례
  - 디자인 thinking double diamond와 PMBOK 단계 매핑

#### SECTION 02 — 콘셉트의 시각화

- User note:
  - **시각화 도구**: sketching / diagramming / storyboarding / prototype / infographic
  - **아이디어 시각화 차원**:
    - 1차원: 정보 일렬 배열, 텍스트
    - 2차원: 위치/크기/방향 공간 속성 이용 — 그래프, 차트, 웹페이지 레이아웃
    - 3차원: 3D 모델링, 3축 그래프
  - **데이터 시각화**: Heatmap / Treemap / Hyperbolic Tree
    - **User open question** "> 프로토타입 index에 Hyperbolic Tree 사용할지?"
  - **Prototype Fidelity**: Low / Middle / High
    - **User open question** "> CodePen 사용할지?"
- Decision Matrix mapping:
  - workflow_stage: `concept`, `prototype`
  - decision_point: 시각화 차원/도구 선택, fidelity level 선택
  - Axismundi_route: `atlas` (visualization technique map), `decisions_candidate` (Pilot index visualization)
- Codex research TODO:
  - Hyperbolic Tree 라이브러리 + WordPress block 호환성
  - CodePen vs Codesandbox vs local prototype 비교
  - prototype fidelity matrix (Sketch → Figma → coded prototype lineage)

---

## PART 02 — 프로토타입 제작 및 사용성 테스트

### CHAPTER 01 — 프로토타입 제작

#### SECTION 01 — 사진ㆍ이미지 준비

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `prototype` (asset preparation)
  - decision_point: 이미지 소스 (project-owned / CC / paid) + 라이선스 분류
  - Axismundi_route: `core` (asset policy, [[project-axismundi-release-seal]] + ASSET-SURFACE-INDEX cross-reference)
- Codex research TODO:
  - 한국 시험 기준 이미지 형식 (JPG/PNG/WebP/AVIF) 표준
  - 이미지 최적화 / lazy loading 가이드라인
  - WCAG 1.1.1 텍스트 대체 (alt text) 관련

#### SECTION 02 — 영상ㆍ아이콘ㆍ서체ㆍ타이포그래피ㆍ애니메이션 준비

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `prototype`
  - decision_point: 자산 종류별 형식/라이선스/접근성 요건
  - Axismundi_route: `core` (asset matrix), Material Symbols / Roboto / Noto fonts 등 Axismundi M3 asset과 cross-reference
- Codex research TODO:
  - 영상 형식 비교 (MP4/WebM/Ogg) + WordPress core/video block 지원
  - 아이콘 라이브러리 비교 (Material Symbols / Font Awesome / Heroicons)
  - 서체 라이선스 (OFL 1.1, Apache 2.0) 한국어 폰트 (Noto Sans KR 등) 정책
  - prefers-reduced-motion + 애니메이션 가이드라인

#### SECTION 03 — 아이디어 시각화

- User note: TBD (별도, 위 CHAPTER 02 SECTION 02와 중복 가능성).
- Decision Matrix mapping:
  - workflow_stage: `prototype`
  - decision_point: 어떤 아이디어를 어떤 시각 형식으로 (mockup / interactive prototype / video walkthrough)
  - Axismundi_route: `atlas` (visualization pattern map)
- Codex research TODO:
  - 책에서 SECTION 02 (CHAPTER 02)와 PART 02 CHAPTER 01 SECTION 03의 "아이디어 시각화" 내용 차이 확인

#### SECTION 04 — 프로토타입 제작과 종류

- User note:
  - (사용자 메모는 위 CHAPTER 02에서 부분 다룸) Fidelity 3-tier (Low/Mid/High)
- Decision Matrix mapping:
  - workflow_stage: `prototype`
  - decision_point: prototype 종류 선택 (wireframe / mockup / interactive / clickable / coded)
  - Axismundi_route: `core` (PrototypeType enum candidate)
- Codex research TODO:
  - 프로토타입 종류 분류 (paper / wireframe / mockup / interactive / native / coded)
  - HTML 정적 프로토타입 vs Pilot block theme 비교 (Axismundi context)

### CHAPTER 02 — 사용자 조사ㆍ분석 및 사용성 테스트

#### SECTION 01 — 사용자 조사 및 분석

- User note:
  - **User Test vs Usability Test** distinction (definitions TBD)
- Decision Matrix mapping:
  - workflow_stage: `usability`
  - decision_point: 사용자 조사 방법 (interview / survey / observation / analytics)
  - Axismundi_route: `core` (UserResearchMethod enum)
- Codex research TODO:
  - User Test vs Usability Test 정의 차이 (기능 검증 vs UX 검증)
  - Persona / Empathy Map / User Journey Map 관계
  - 한국 시험 표준 정의 cross-check

#### SECTION 02 — 사용성 테스트 및 분석

- User note:
  - **Formative Usability Testing** 평가 항목:
    - 정보의 효율성: 필요 정보를 얼마나 빨리 찾는지
    - 작업의 효율성: 시간/노력
    - 사용자 만족도
    - 직관성: 내비게이션/인터페이스
    - 접근성
    - 응답성: 다양한 장치/화면크기에서의 동작
  - **Heuristic Evaluation** (Jakob Nielsen 10 휴리스틱):
    1. 시스템 상태의 가시성 유지
    2. 실제 세상과 시스템의 일치
    3. 사용자 제어 및 자유
    4. 일관성과 표준
    5. 오류 예방
    6. 회상보다는 인식
    7. 유연성과 효율성
    8. 심미적이고 최소의 디자인
    9. 오류로부터 회복 지원
    10. 도움말 및 문서 제공
- Decision Matrix mapping:
  - workflow_stage: `usability`
  - decision_point: 테스트 종류 (formative / summative / heuristic) + 평가 기준 선택
  - Axismundi_route: `core` (UsabilityHeuristic enum + EvaluationCriterion enum), `atlas` (testing methodology atlas)
- Codex research TODO:
  - Formative vs Summative Usability Testing 차이
  - Nielsen 10 heuristics 원문 reference URL + 한국어 번역 비교
  - Axismundi UI 평가에 Nielsen heuristics 적용 사례 (lab style-guide-blocks.html 평가)

---

## PART 03 — 디자인 구성요소 설계 및 제작

### CHAPTER 01 — 스토리보드 설계 및 제작

#### SECTION 01 — 정보설계와 정보구조

- User note:
  - **정보설계** — 정보의 종류 (Robert M. Gagne 분류 추정):
    - facts / concepts / procedures / processes / principles
  - **정보설계를 위한 기초지식**:
    - 웹사이트 정보구조 이해
    - 내비게이션 구조 설계
    - 구축기능 이해 및 사용도구 운용
  - **웹사이트 설계요소**:
    - 이용자 등록 (사용자등록 폼 위치 / 필수 입력 / 계정 관리 페이지)
    - 로그인 및 인증 (로그인 페이지 위치 / 인증 절차 / 관련 링크/메뉴)
    - 비밀번호 재설정
    - 프로필 관리
    - 검색 기능 (검색창 위치 / 검색결과 페이지 구조)
    - 내비게이션 메뉴 (주요 카테고리 / 하위메뉴 / 사이트 내 이동)
    - 콘텐츠 관리 (분류 / 배치)
    - 피드백 및 문의 (폼 / 페이지 위치)
    - 알림 (시스템 / 페이지 배치)
  - **정보체계화**:
    - 개별 분산 콘텐츠를 체계적 그룹화
    - 논리적 연결로 일관된 섹션 그룹화
    - **과정**: 콘텐츠 수집 → 그룹화 → 조직/구조화 → 계층구조 설계 → 구조 테스트
    - **방법**:
      - 특징 명확: 알파벳순 / 연대와 날짜 / 위치
      - 특징 불명확: 주제 / 기능 / 이용자 / 상징
    - **주요 요소**: 정보분류 / 정보구조화 / 라벨링 / 내비게이션
  - **Information Architecture (IA)**:
    - **Hierarchy** (상하위 관계)
    - **Depth** (최상위~최하위 단계)
    - **Width** (계층 항목 수)
    - **Level** (같은 Depth 항목들)
    - 설계 시 Width / Depth 고려
  - **정보구조 유형**:
    - **Hierarchical Structure**: 상위-하위 계층
    - **Hub and Spoke Structure**: 중앙 허브 → 분산 스포크
    - **Nested Doll Structure**: 큰 개요 → 세부정보 선형
    - **Dashboard Structure**: 홈에서 다양한 정보 한눈에
    - **Labeling Structure**: 레이블 기반 탐색
- Decision Matrix mapping:
  - workflow_stage: `IA`
  - decision_point: 정보구조 유형 선택, IA depth/width 결정, 사이트 설계요소 포함 여부
  - input_evidence: 사용자 페르소나, 콘텐츠 인벤토리
  - output_artifact: 사이트맵, 카테고리 트리, 라벨링 가이드
  - Axismundi_route: `core` (InfoArchType enum + IADimension entity), `atlas` (IA pattern map)
- Codex research TODO:
  - Robert M. Gagne 5 instructional content types 원문
  - IA pattern 비교 (Hierarchy vs Hub-Spoke vs Nested Doll vs Dashboard vs Labeling) — 사용 사례
  - 한국 정부 사이트 IA 사례 (정부24 등) 분석
  - WordPress Site Editor의 IA 모델 (templates / parts / patterns) cross-reference

#### SECTION 02 — 와이어프레임 작성과 레이아웃

- User note:
  - **Wireframe vs Layout**:
    - Wireframe: 초기 아이디어와 구조 중점
    - Layout: 와이어프레임 기반으로 헤더/내비게이션/콘텐츠/푸터/배너 시각 요소 배치, 웹페이지 골격 구체화
  - **레이아웃 구성요소**: Header / Navigation / Contents / Aside / Footer / Ads / etc
  - **레이아웃 구성방법**:
    - 중요 콘텐츠 먼저 배치 → 세부사항 결정
    - 단순/간결
    - 일관성/논리성
    - 사용자 테스트로 적합한 레이아웃 최종 선택
    - Template 사용으로 일관 스타일 유지
  - **레이아웃 종류**:
    - Fixed Width Layout
    - Fluid Layout
    - Responsive Layout
    - Adaptive Layout
- Decision Matrix mapping:
  - workflow_stage: `wireframe`
  - decision_point: 레이아웃 종류 선택 (Fixed / Fluid / Responsive / Adaptive), 와이어프레임 fidelity, template 사용 여부
  - input_evidence: IA, 콘텐츠 우선순위
  - output_artifact: 와이어프레임 spec, 레이아웃 grid
  - Axismundi_route: `core` (LayoutType enum), `atlas` (layout pattern map), `Pilot_harness_later` (Pilot template layout 적용)
- Codex research TODO:
  - Fluid vs Responsive vs Adaptive 정확 차이 + 사례
  - WordPress block theme의 layout 패턴 (theme.json + block patterns)
  - 와이어프레임 도구 (Figma / Balsamiq / Pen & Paper) 비교

#### SECTION 03 — 스토리보드 작성

- User note:
  - **Storyboard**: 일종의 작업지침서, 웹사이트 전체 구성 문서
  - 웹페이지 화면 계획, 레이아웃, 내비게이션, 기능 등을 그림+설명으로 시각화
  - 주로 와이어프레임 + 시나리오
  - **작성 개념**:
    - 다양한 페이지/화면요소 시각적 표현
    - 페이지 콘텐츠/화면설계 구체적 명시
  - **작성 내용**:
    - 표지 페이지
    - 개정 이력
    - 화면 설계
    - 서비스 흐름도
    - 페이지 상세정보
    - 디자인 요소
    - 기능과 요구사항
  - **주의사항**:
    - 디자인 요소보다 페이지 노출 주요 요소 표현
    - 화면 설명 부족 시 별도 서비스 페이지 보완
    - 작성자 의도 정확 전달 (페이지 구성요소/기능 상세)
  - **UX 디자인 스토리보드 작성 순서** (Persona 기반):
    1. 사용자 페르소나 정의
    2. 사용자 목표/니즈 식별
    3. 사용자 시나리오 작성
    4. 스토리보드 시각화
    5. 피드백 개선
  - **스토리보드 예시 spec**: page name / webpage details (font / font-size / color / img / text / link / assets)
- Decision Matrix mapping:
  - workflow_stage: `storyboard`
  - decision_point: 스토리보드 fidelity, 포함 요소 (페이지별 상세 vs 흐름도 vs 인터랙션)
  - input_evidence: 와이어프레임, 페르소나
  - output_artifact: 스토리보드 문서
  - Axismundi_route: `core` (StoryboardSpec entity), `decisions_candidate` (Pilot 페이지별 storyboard)
- Codex research TODO:
  - 스토리보드 표준 형식 (page-by-page spec sheet)
  - UX 스토리보드 vs 영화/애니메이션 스토리보드 차이
  - Persona-driven scenario writing 사례

### CHAPTER 02 — 심미성·사용성 설계 및 구성

#### SECTION 01 — 디자인 의미와 조건

- User note:
  - **디자인의 발상 단계**: 모방 → 수정 → 적응 → 혁신
  - **디자인의 과정**: 발의 → 확인 → 조사 → 분석 → 종합 → 평가 → 개발 → 전달
    - 발의: 대상/방법 요청 수렴/선정
    - 확인: 예측 문제점/가능성
    - 조사: 문제점 연구, 정보 수집
    - 분석: 자료 분류/분석
    - 종합: 자료 종합, 디자인 진행
    - 평가: 최종 평가/결정
    - 개발: 디자인화된 제품 제작
    - 전달: 사용자 직접 사용
  - **디자인 문제해결**: 계획 → 조사 → 분석 → 종합 → 평가
- Decision Matrix mapping:
  - workflow_stage: `visual_design`
  - decision_point: 디자인 발상 단계 / 과정 적용 방법
  - Axismundi_route: `core` (DesignProcess workflow rule), `atlas` (design methodology map)
- Codex research TODO:
  - 디자인 사고 (Design Thinking) IDEO 모델과의 비교
  - Double Diamond와 본 과정의 매핑

#### SECTION 02 — 디자인 구성요소

- User note:
  - **웹사이트 디자인 요소**:
    - 배치 및 레이아웃 (전반적 구조 + 각 요소 배치)
    - 색상 구성
    - 타이포그래피
    - 이미지와 그래픽
    - 내비게이션
    - 반응형 디자인
    - Metaphor
    - Accessibility
    - 개인화된 경험
    - 로드 속도
- Decision Matrix mapping:
  - workflow_stage: `visual_design`
  - decision_point: 각 요소별 디자인 결정 + 우선순위
  - Axismundi_route: `atlas` (design element checklist), `core` (DesignElement enum)
- Codex research TODO:
  - 각 요소별 best practice 사례
  - Material Design 3 / Apple HIG / Microsoft Fluent의 design element 분류와 cross-reference

##### UI/UX 심리학 — Gestalt Principles (책 분류 위치 추정)

- User note:
  - 근접성의 법칙
  - 유사성의 법칙
  - 연속성의 법칙
  - 폐쇄성의 법칙
  - 대칭성의 법칙
  - 전경-배경의 법칙
  - 공통운명의 법칙
- Decision Matrix mapping:
  - workflow_stage: `visual_design`
  - decision_point: Gestalt 원칙 적용 (그룹화 / 시각 계층 / 인지 부담 감소)
  - Axismundi_route: `core` (GestaltPrinciple enum)
- Codex research TODO:
  - Gestalt 7 principles 원문 + 한국어 번역
  - Axismundi 디자인 시스템 (lab catalog)에서 Gestalt 적용 사례

#### SECTION 03 — 그리드 시스템 및 반응형 디자인

- User note:
  - **Grid System** 구성요소:
    - Container / Column / Gutter / Margin / Module
  - **그리드 시스템 유형**:
    - 삼등분 / 황금비율 / column / 기준선 / 황금비율 수직수평그리드
  - **Responsive Design** 요소:
    - 반응형 레이아웃 / 반응형 그리드 / viewport / 미디어쿼리 / 유연한 이미지 및 미디어 / 반응형 타이포그래피 / 로딩속도 최적화
  - **반응형 레이아웃 — Luke Wroblewski 5 패턴**:
    - Mostly Fluid
    - Column Drop
    - Layout Shifter
    - Tiny Tweaks
    - Off Canvas
- Decision Matrix mapping:
  - workflow_stage: `responsive`
  - decision_point: 그리드 시스템 선택, 반응형 패턴 선택, breakpoint 결정
  - Axismundi_route: `core` (GridSystem entity + ResponsivePattern enum), `Pilot_harness_later` (Pilot template responsive 적용)
- Codex research TODO:
  - Luke Wroblewski 5 patterns 원문 + 시각 예시
  - WordPress theme.json의 grid/spacing 시스템 cross-reference
  - CSS Grid / Flexbox / Container Queries 비교

#### SECTION 03 (중복 marker — 책 errata) — UX 적용 및 UI 설계

> NOTE: 책 목차에 SECTION 03이 두 번 나옴 (그리드 시스템 + UX 적용). 책 errata 가능성. Phase 1 진단 시 SECTION numbering 정정 결정.

- User note:
  - **UX 디자인 과정**:
    - Task 분석 → 사용자 조사/분석 → 정보구조 설계 → 와이어프레임 생성 → 프로토타입 제작 → 사용성 테스트 → 피드백 반영 → 최종 구현/출시 → 지속적 개선
  - **User Journey Map** / **Experience Map**
  - **경험지도 구성요소**:
    - Persona / Timeline / Touch Points / Channels / Emotional States / Pain Points / Insights
  - **UX 디자인 접근법**:
    - Interaction Design
    - Responsive Design
    - User-Centred Design
    - Visual Design
    - Ethical Design
  - **UX Gesture**:
    - Press / Long Press / Scroll / Drag / Pull to Refresh / Single Tap / Double Tap / Pinch
  - **User Experience Honeycomb Model**:
    - Useful / Usable / Credible / Findable / Accessible / Desirable / Valuable
  - **User Interface**:
    - **Jakob Nielsen UI 사용성 휴리스틱** (PART 02 SECTION 02와 동일 10개)
  - **UI 그룹화 장점**:
    - 신속성 / 재사용성 / 일관성 / 가독성 / 유지보수 용이
  - **기타 UI/UX 용어**:
    - Fidelity / Affordance / Agile UX / Brand Identity / Breadcrumb / Decision Matrix / User Flow / Mockup / Dark Pattern / Color Palette / Hamburger Button / Whitespace / GUI
- Decision Matrix mapping:
  - workflow_stage: `UX_design`, `wireframe`, `prototype`
  - decision_point: UX 접근법 선택, journey map 작성 여부, gesture 지원 범위
  - Axismundi_route: `core` (UXApproach enum + UXGesture enum + UXHoneycombDimension enum), `atlas` (UX methodology map)
- Codex research TODO:
  - Peter Morville UX Honeycomb 원문
  - Journey Map vs Experience Map vs Service Blueprint 차이
  - Dark Pattern 분류 (Harry Brignull)
  - WCAG와 UX Honeycomb의 Accessible 차원 cross-reference

##### Navigation (별도 정리)

- User note:
  - **내비게이션 구성요소**:
    - 내비게이션 바 (홈버튼 / 메뉴항목 / 검색창)
    - 메뉴 (계층구조 / 드롭다운 / 햄버거)
    - 링크
    - 이미지맵 / 사이트맵
    - 사이드바 (widget 포함 가능)
    - 바닥글 내비게이션
    - 기타 (방문자정보 / 이용자위치정보)
  - **내비게이션 메뉴 종류**:
    - 고정된 헤더 (sticky)
    - Dropdown menu
    - Hamburger Menu (모바일)
    - Mega Menu
  - **내비게이션 유형**:
    - Sequence (선형, 앞뒤)
    - Grid (수평+수직)
    - Hierarchy (가장 일반적)
    - Network (순서 없음, 엔터테인먼트)
- Decision Matrix mapping:
  - workflow_stage: `IA`, `wireframe`
  - decision_point: 내비게이션 유형/종류 선택
  - Axismundi_route: `core` (NavigationType enum + NavigationComponent enum), WordPress `core/navigation` block cross-reference
- Codex research TODO:
  - Mega Menu 사용 사례 + 접근성 고려사항
  - WordPress Navigation block의 mega menu 지원 현황
  - 햄버거 메뉴 vs 탭바 모바일 UX 비교

#### SECTION 04 — 2D, 3D 디자인 소프트웨어 활용

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `visual_design`, `prototype`
  - decision_point: 디자인 도구 선택 (2D: Figma / Sketch / XD / Photoshop / Illustrator, 3D: Blender / Cinema 4D / Spline)
  - Axismundi_route: `atlas` (design tool atlas)
- Codex research TODO:
  - 2D / 3D 도구 비교
  - 웹용 3D (WebGL / Three.js / Spline) 동향

### CHAPTER 03 — 매체성 구성요소 설계 및 제작

#### SECTION 01 — 디바이스별 특성 및 디자인

- User note:
  - **웹브라우저 해상도**: HD / FHD / QHD / UHD
  - 모바일 디바이스별 해상도 및 화면비율 (구체 표 없음, TBD)
- Decision Matrix mapping:
  - workflow_stage: `responsive`
  - decision_point: 타겟 디바이스/해상도 범위
  - Axismundi_route: `core` (DeviceClass enum + ViewportRange entity)
- Codex research TODO:
  - 현재 (2026) 주요 모바일/태블릿/데스크탑 해상도 분포 (StatCounter 등)
  - viewport meta tag 권장 설정
  - Apple/Google 디바이스 specification 표

#### SECTION 02 — 웹 표준 및 웹 표준 검사

- User note:
  - **Web Standards**: 호환성 / 접근성 / 유지보수성 / SEO
- Decision Matrix mapping:
  - workflow_stage: `standards_accessibility`
  - decision_point: 웹 표준 준수 범위, 검사 도구 선택
  - Axismundi_route: `core` (WebStandard policy), `atlas` (validation tool atlas)
- Codex research TODO:
  - W3C validator / WAVE / Lighthouse 비교
  - 한국 KWCAG (Korean Web Content Accessibility Guidelines) 현황
  - WordPress 테마 검수 ([Theme Check plugin]) 표준

#### SECTION 03 — 웹 접근성

- User note:
  - **WCAG**: Perceivable / Operable / Understandable / Robust (POUR)
- Decision Matrix mapping:
  - workflow_stage: `standards_accessibility`
  - decision_point: WCAG 준수 레벨 (A / AA / AAA), 적용 범위
  - Axismundi_route: `core` (WCAGPrinciple enum + WCAGLevel enum)
- Codex research TODO:
  - WCAG 2.2 / 3.0 동향
  - 한국 KWCAG 2.2 / 국가표준 cross-reference
  - WordPress 테마 접근성 표준 (wp.org submission 기준 AA)

---

## PART 04 — 구현 및 응용

### CHAPTER 01 — 웹디자인 구성 및 프로세스

#### SECTION 01 — 웹디자인 시스템 및 인력 구성

- User note: TBD (단편적, 책 본문 참조 필요).
- Decision Matrix mapping:
  - workflow_stage: `implementation`
  - decision_point: 팀 구성 / 역할 분담 / 시스템 선택
  - Axismundi_route: `core` (TeamRole enum + WebSystem entity)
- Codex research TODO:
  - 한국 웹디자인 표준 인력 구성 (PM / Designer / FE / BE / Tester)
  - 웹디자인 시스템 (CMS vs 정적 / Headless / WordPress / Webflow / Framer) 비교

#### SECTION 02 — 웹디자인 프로세스

- User note:
  - **웹디자인 프로세스 (5단계)**: 기획 → 설계 → 개발 → 출시 → 유지보수
  - **상세 과정**:
    - 프로젝트 기획: 목표 설정 / 요구사항 정의
    - 웹사이트 계획: 사이트맵 / 콘텐츠 전략 / 기술 스택
    - UI/UX 디자인
    - 개발
    - 테스트 및 배포
    - 유지보수 및 업데이트
  - **상세 작업**:
    - 프로젝트 기획: 목표/목적, 시장조사, 타겟고객, 아이디어 도출, 타당성 조사, 팀 구성, 일정, 예산
    - 웹사이트 기획: 정보구조 설계, 콘텐츠 전략, 기술 스택
    - UI/UX 디자인: 와이어프레임, 스토리보드, 프로토타입, 디자인 가이드라인
    - 개발: 프론트엔드 / 백엔드 / CMS 통합 / 테스트
    - 테스트 및 배포: 테스트/디버깅, 출시/모니터링, 마케팅/홍보
    - 유지보수: 지속적 유지보수, 성능 최적화, 피드백, 백업/복구
  - **웹디자인 프로세스 3단계**:
    - Pre-Production: 디자인 계획 / 컨셉 / 디자인 구체화
    - Production: 실제 제작 / 콘텐츠 / 사이트 구축 / 서버 구성
    - Post-Production: 홍보 / 콘텐츠 제작
- Decision Matrix mapping:
  - workflow_stage: `implementation` (cross-cutting all stages)
  - decision_point: 단계 분할 (5단계 vs 3단계) + 단계별 deliverable
  - Axismundi_route: `core` (WebDesignProcess workflow rule), `atlas` (process map)
- Codex research TODO:
  - PMBOK / MaRMI-III와 본 5/3단계 cross-mapping
  - Agile / Waterfall / DevOps와 본 process 비교

### CHAPTER 02 — 웹디자인 구현 및 응용

#### SECTION 01 — 웹 인터페이스

- User note:
  - **Client-Side / Server-Side** 구분
- Decision Matrix mapping:
  - workflow_stage: `implementation`
  - decision_point: client/server 책임 분담, SPA vs MPA vs SSG vs SSR
  - Axismundi_route: `atlas` (rendering pattern map)
- Codex research TODO:
  - SPA / MPA / SSG / SSR / ISR 정의 + 사례
  - WordPress block theme의 rendering 모델 (PHP-rendered + block-level interactivity API)

#### SECTION 02 — 화면 및 기능 요소 구현

- User note:
  - **화면 구성요소**: Header / Navigation Bar / Content Area / Sidebar / Footer / Button / Images and Icons / Form
  - **기능요소** (Interactive):
    - **Form**:
      - 필수정보: ID / 비밀번호 / 이름 / 생년월일 / 성별 / 이메일 / 주소 / 모바일번호
      - 선택정보: 직업 / 취미 / 수신동의
    - **Animation**: 버튼클릭, 스크롤
    - **Drag and Drop**
    - **Modal Window**:
      - Modal layer (웹페이지 종속, 긴급 / 기본창 비활성 / 배경 어둡게)
      - Layer popup (독립, 새 레이어 / 기본창 영향 없음)
    - **Feedback** (폼 제출 후 성공/오류 메시지)
- Decision Matrix mapping:
  - workflow_stage: `implementation`
  - decision_point: 화면 구성요소 표준화, 기능 요소 사양, 모달 종류 결정
  - Axismundi_route: `core` (ScreenComponent enum + InteractiveElement enum + ModalType enum), WordPress core block 매핑 ([[v3.6.24]] catalog cross-reference)
- Codex research TODO:
  - 한국 개인정보보호법 폼 필수정보 제한 (실명/주민번호 등 제한)
  - Modal vs Dialog vs Popover (ARIA dialog / popover API 최신 동향)
  - WordPress core/dialog block (있다면) 현황

#### SECTION 03 — 웹 프로그래밍 개발

- User note: TBD (구체 메모 없음, "프론트엔드/백엔드" 정도만 언급).
- Decision Matrix mapping:
  - workflow_stage: `implementation`
  - decision_point: 프로그래밍 언어/프레임워크 선택 (FE: Vanilla JS / React / Vue / Svelte; BE: Node / PHP / Python / Go; CMS: WordPress / Drupal)
  - Axismundi_route: `atlas` (tech stack atlas), `core` (PrincipalStack policy)
- Codex research TODO:
  - 2026 기준 FE 프레임워크 인기도 (State of JS 등)
  - PHP / WordPress 위상 (Web 점유율 + 트렌드)
  - Headless WordPress / Block API 동향

#### SECTION 04 — 웹 트렌드

- User note:
  - 반응형 / AI 기반 맞춤형 경험 / 인터랙션 향상 / micro-interactions / minimalism / dark mode / VR/AR / 접근성 / 최적화와 캐싱
- Decision Matrix mapping:
  - workflow_stage: `implementation`
  - decision_point: 트렌드 채택 여부 (current vs evergreen)
  - Axismundi_route: `atlas` (trend snapshot)
- Codex research TODO:
  - 2026 웹 디자인 트렌드 (Awwwards / Smashing Magazine 등)
  - AI 기반 디자인 도구 동향 (v0 / Vercel / Framer AI)
  - dark mode 사용 통계

---

## PART 05 — 색채혼합과 조색

### CHAPTER 01 — 목표색 분석 및 색채혼합

#### SECTION 01 — 색의 3속성

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `color_system`
  - decision_point: 색 표현 모델 (HSL / HSB / HSV / HCT) 선택
  - Axismundi_route: `core` (ColorAttribute enum: Hue / Saturation / Value), Material 3 HCT cross-reference
- Codex research TODO:
  - 색의 3속성 (Hue / Value / Chroma 또는 H/S/B) 정의
  - HCT (Material 3) vs HSL vs HSV 비교
  - 색 표현 모델 한국어 표준 용어

#### SECTION 02 — 색채혼합, 가산혼합, 감산혼합

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `color_system`
  - decision_point: 혼합 모델 (가산 RGB / 감산 CMYK / 중간혼합) 선택, 웹 context (가산 우선)
  - Axismundi_route: `core` (ColorMixingModel enum)
- Codex research TODO:
  - 가산혼합 (RGB) / 감산혼합 (CMY/CMYK) / 중간혼합 정의 + 사례
  - 웹 디자인에서 RGB의 우위 + 프린트 출력 시 CMYK 변환 시 주의사항

#### SECTION 03 — 색채 표준의 조건과 역할

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `color_system`
  - decision_point: 색채 표준 채택 (PCCS / Munsell / NCS / Pantone / sRGB / Display P3)
  - Axismundi_route: `core` (ColorStandard enum), Material 3 색 시스템 cross-reference
- Codex research TODO:
  - PCCS / Munsell / NCS / Pantone 비교
  - 웹 디자인 sRGB vs Display P3 (Apple) 차이
  - 한국 KS 색채표준

### CHAPTER 02 — 조색 검사 및 완성

#### SECTION 01 — 조색 검사

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `color_system`
  - decision_point: 조색 검사 방법
  - Axismundi_route: `atlas` (color inspection methodology)
- Codex research TODO:
  - 조색 검사 procedure (육안 vs 측정기)
  - 웹 색채에서 calibration 의미 (모니터 캘리브레이션)

#### SECTION 02 — 색상·명도·채도·색조의 색차 보정

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `color_system`
  - decision_point: 색차 보정 방법 + 허용 범위
  - Axismundi_route: `core` (ColorDifferenceTolerance entity), WCAG 명도 대비 cross-reference
- Codex research TODO:
  - 색차 (ΔE) 정의 + CIELAB Delta E 2000
  - WCAG contrast ratio 와 색차의 관계
  - Material 3 HCT의 tone 시스템과 색차 보정

---

## PART 06 — 배색

### CHAPTER 01 — 색채 계획과 배색 조합

#### SECTION 01 — 색채 계획

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `palette_system`
  - decision_point: 색채 계획 단계 / 적용 범위
  - Axismundi_route: `atlas` (color planning workflow)
- Codex research TODO:
  - 색채 계획 (Color Strategy) 일반 단계 (분석 → 컨셉 → 배색 → 검증)
  - Brand color guidelines 사례

#### SECTION 02 — 주조색, 보조색, 강조색

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `palette_system`
  - decision_point: 색채 role 비율 (60-30-10 rule 등)
  - Axismundi_route: `core` (ColorRole enum: Dominant / Secondary / Accent), Material 3 (primary / secondary / tertiary / surface / etc) cross-reference
- Codex research TODO:
  - 60-30-10 rule 원천
  - 주조색/보조색/강조색 한국어 용어와 영어 (dominant / secondary / accent) 매핑
  - Material 3 color roles와 본 분류 cross-reference

#### SECTION 03 — 색채 조화와 색채 조화론

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `palette_system`
  - decision_point: 색채 조화 모델 선택 (Itten / Ostwald / Munsell / PCCS)
  - Axismundi_route: `atlas` (color harmony theory map)
- Codex research TODO:
  - Itten / Ostwald / Munsell / PCCS 조화론 비교
  - 디지털 (HCT 기반) 조화 모델

#### SECTION 04 — 배색 조합

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `palette_system`
  - decision_point: 배색 유형 선택 (유사색 / 보색 / 분리보색 / 삼색조 / 사색조)
  - Axismundi_route: `core` (ColorScheme enum)
- Codex research TODO:
  - Monochromatic / Analogous / Complementary / Split-complementary / Triadic / Tetradic
  - Material 3 color scheme generation 알고리즘

#### SECTION 05 — 색과 색채의 심리적ㆍ기능적 작용

- User note: TBD (not in `wdd.md`).
- Decision Matrix mapping:
  - workflow_stage: `palette_system`
  - decision_point: 색채 심리 적용 (브랜드, 감정, 행동 유도)
  - Axismundi_route: `atlas` (color psychology map)
- Codex research TODO:
  - 색채 심리 (warm/cool, advancing/receding 등) 일반 원칙
  - 문화권별 색채 의미 차이 (한국/서양 등)
  - 색약/색맹 고려 (accessibility cross-reference)

---

## PART 07 — 프로젝트 완료 자료 정리

### CHAPTER 01 — 산출물 수집 및 정리

#### SECTION 01 — 프로젝트 산출물 수집

- User note:
  - **IEEE SWEBOK** (Software Engineering Body of Knowledge) 17 지식영역 + 산출물:
    1. 소프트웨어 요구사항 → 요구사항 명세서 / 요구사항 추적 매트릭스
    2. 소프트웨어 아키텍처 → 시스템 아키텍처 설계서 / 아키텍처 검토 보고서
    3. 소프트웨어 설계 → 상세 설계서 / 설계 패턴 문서
    4. 소프트웨어 구축 → 소스코드 / 코드리뷰 보고서
    5. 소프트웨어 테스팅 → 테스트 계획서 / 테스트 케이스
    6. 소프트웨어 운영 → 운영계획서 / 운영매뉴얼
    7. 소프트웨어 유지관리 → 유지보수 계획서 / 수정요청서
    8. 소프트웨어 구성관리 → 형상관리계획서 / 형상 베이스라인
    9. 엔지니어링 관리 → 프로젝트 계획서 / 리스크관리 계획서
    10. 엔지니어링 프로세스 → 프로세스 정의서 / 프로세스 평가 보고서
    11. 엔지니어링 모델 및 방법 → 프로세스 모델 문서 / 모델링 다이어그램 / 방법론 지침
    12. 소프트웨어 품질 → 품질보증계획서 / 품질리뷰보고서
    13. 소프트웨어 보안 → 보안 요구사항 명세서 / 보안검토보고서
    14. 엔지니어링 전문가 관행 → 교육계획서 / 자격증명서
    15. 엔지니어링 경제학 → 경제분석보고서 / 비용예측문서
    16. 컴퓨팅기반 → 컴퓨터 이론 보고서 / 알고리즘 설계 문서
    17. 수학적 기반 → 수학 모델링 보고서 / 수학적 분석 문서
    (18. 엔지니어링 기반 — 공학원리보고서 / 공학적 분석 문서)
  - **산출물 종류**:
    - 디자인 파일: 시안 / 프로토타입 / UI/UX 디자인
    - 소스 코드: 소스코드 / 스크립트 / 라이브러리 / 모듈
    - 데이터베이스: 사용자 / 콘텐츠 / 로그
    - 문서화된 산출물:
      - 기획: 프로젝트 계획서 / 제안서 / 요구사항 정의서
      - 실행: 디자인 시안 / 소스코드 문서 / 시스템 설계 문서 / 테스트 계획 및 결과 / 배포 계획서
      - 이후: 유지보수 계획서
  - **문서화된 산출물 상세** (12개):
    - 제안서 / 프로젝트 계획서 / 요구사항 정의서 / 디자인 시안 / 설계 문서 / 테스트 계획서 / 최종보고서 / 산출물 목록 / 소스코드 및 기술문서 / 테스트 결과 / 사용자 메뉴얼 / 유지보수 계획 / 회의록 / 일정표
- Decision Matrix mapping:
  - workflow_stage: `handoff_report`
  - decision_point: 산출물 분류 체계 / 보존 형식
  - Axismundi_route: `core` (DeliverableType enum + SWEBOKArea enum), `atlas` (deliverable atlas)
  - G1/G2 relevance: G2 high (release seal + wp.org submission deliverable 매핑)
- Codex research TODO:
  - SWEBOK V3 (latest) 지식영역 정확 list (현재 18+ 영역, 위는 17개로 transcription 누락 가능성)
  - IEEE SWEBOK V4 (있다면) update
  - WordPress.org theme submission required deliverables cross-reference

#### SECTION 02 — 콘텐츠 및 데이터 분류, 보존, 폐기

- User note:
  - **산출물 정리 과정**: 생성된 작업물 수집 → 콘텐츠/데이터 분류 → 보존 → 폐기
  - **분류 방법**:
    - 콘텐츠 유형별 (텍스트 / 이미지 / 비디오 / 오디오)
    - 데이터 유형별
    - 메타데이터 추가 (작성자 / 생성일 / 카테고리)
  - **보존 방법**:
    - 백업 및 복원 (로컬 + 클라우드, 복원 절차)
    - 버전 관리
    - 접근 권한 관리
    - 보관 정책 (보관 기간 / 방법)
  - **폐기 방법**:
    - 폐기 기록 관리
    - 폐기 정책 (삭제 방법 / 시기)
  - **산출물 정리 방법** 단계:
    - 분류 및 체계화 (카테고리화, 폴더 구조)
    - 명명 규칙 (일관된 파일명, 버전 관리)
    - 문서화 및 기록 (메타데이터, 변경이력)
    - 접근성 및 보안 (권한, 백업/복구)
    - 폐기 기준 (보관기간, 폐기 절차)
    - 정기적 검토 및 업데이트
  - **유효하지 않은 자료 제거 기준**:
    - 임시파일 / 불필요한 초안 / 중복자료 / 오래된 기록 / 관련없는 자료
  - **주요 산출물 목록 by 단계**:
    - 기획: 프로젝트 계획서
    - 디자인: 디자인 시안 (와이어프레임, UI/UX 시안)
    - 개발: 코드문서 (소스코드, API 문서, 코드 설명서)
    - 테스트: 테스트 계획 및 결과 (시나리오, 결과 보고서, 버그 리스트)
    - 배포: 배포 계획서
    - 유지보수: 유지보수 계획서
  - **CBD SW 개발표준산출물** (25개 필수):
    - 분석 단계:
      - 사용자 요구사항 정의서
      - Use Case 명세서
      - 요구사항 추적표
    - 설계 단계 (12개):
      - 클래스 설계서
      - 사용자 인터페이스 설계서
      - 컴포넌트 설계서
      - 인터페이스 설계서
      - 아키텍처 설계서
      - 총괄시험 계획서
      - 시스템 시험 시나리오
      - ERD 기술서
      - 데이터베이스 설계서
      - 통합시험 시나리오
      - 단위시험 케이스
      - 데이터 전환 및 초기데이터 설계서
    - 구현 단계 (3개):
      - 프로그램 코드
      - 단위시험 결과서
      - 데이터베이스 테이블
    - 시험 단계 (7개):
      - 통합시험 결과서
      - 시스템시험 결과서
      - 사용자 지침서
      - 운영자 지침서
      - 시스템 설치 결과서
      - 인수시험 시나리오
      - 인수시험 결과서
- Decision Matrix mapping:
  - workflow_stage: `handoff_report`
  - decision_point: 분류 체계 / 보존 정책 / 폐기 정책 / 명명 규칙
  - Axismundi_route: `core` (DeliverableLifecycle entity + RetentionPolicy entity + NamingConvention rule), `atlas` (CBD deliverable map)
  - G1/G2 relevance: G2 high (Axismundi release seal + commit lineage convention과 직접 연결)
- Codex research TODO:
  - CBD 25 필수 산출물 원전 (한국 SW 산업진흥원 표준)
  - GDPR / 개인정보보호법 데이터 폐기 요건
  - Git lineage convention (Axismundi commit chain) cross-reference

### CHAPTER 02 — 프로젝트 결과 및 보고

#### SECTION 01 — 프로젝트 최종 보고

- User note:
  - **프로젝트 최종 보고 사례**:
    - 목표 달성: 반응형 웹사이트 / UX 개선 / 고객만족도 향상
    - 주요 성과:
      - 디자인: 현대적/직관적 UI
      - 기능: 로그인/회원가입, 검색/필터, 실시간 채팅
      - 프론트엔드: HTML/CSS/JS 반응형
      - 백엔드: 서버 / DB / API
      - 테스트: 시나리오 / 사용자 테스트 / UX 개선점 반영
    - 문제점 해결방안
    - 향후 개선점
  - **프로젝트 최종 프레젠테이션 사례**:
    - 표지 / 목차
    - 프로젝트 개요 (프로젝트명, 기간, 목표, 기대효과)
    - 프로젝트 진행과정 (계획/일정, 작업분담 — PM/디자이너/FE/BE/Tester)
    - 프로젝트 결과 (디자인, 기능)
    - 테스트 및 검증 결과
    - 결과분석 및 평가 (목표달성, 문제점/해결방안, 향후 개선점)
    - 결론 및 향후 계획 (총평, 향후 계획)
  - **프로젝트 사후 관리**:
    - 유지보수 계획 수립
    - 정기적 리뷰 및 업데이트
    - 사용자 피드백 수집 및 반영
    - 문서화 및 기록 유지
- Decision Matrix mapping:
  - workflow_stage: `handoff_report`
  - decision_point: 보고서 구조 / 프레젠테이션 구성 / 사후 관리 정책
  - Axismundi_route: `core` (FinalReportStructure entity + PostProjectPolicy rule), `atlas` (presentation template atlas)
  - G1/G2 relevance: G2 very high (wp.org submission readme + portfolio presentation에 직접 적용)
- Codex research TODO:
  - 한국 기능사 시험 최종 보고서 표준 형식
  - WordPress.org theme submission readme.txt 형식 cross-reference
  - 포트폴리오 사이트 (Axismundi 자체) presentation 형식 참고

---

## Cross-Cutting Notes (CHAPTER/SECTION 외 — 사용자 메모 자체 주제)

### User-marked open questions

- `> 프로토타입 index에 Hyperbolic Tree 사용할지?` (PART 01 CHAPTER 02 SECTION 02)
- `> CodePen 사용할지?` (PART 01 CHAPTER 02 SECTION 02)

→ Phase 1 진단 또는 별도 decision record에 routing.

### Skeleton의 Decision Matrix 사전 매핑 통계 (Phase 1 진단 입력)

```txt
workflow_stage 후보 distribution (사전 hint):
  intake                  : PART 01 (CHAPTER 01)
  concept                 : PART 01 (CHAPTER 02), PART 04 (process intro)
  prototype               : PART 01 (CHAPTER 02), PART 02 (CHAPTER 01)
  usability               : PART 02 (CHAPTER 02)
  IA                      : PART 03 (CHAPTER 01 SECTION 01)
  wireframe               : PART 03 (CHAPTER 01 SECTION 02)
  storyboard              : PART 03 (CHAPTER 01 SECTION 03)
  visual_design           : PART 03 (CHAPTER 02 SECTION 01/02)
  responsive              : PART 03 (CHAPTER 02 SECTION 03), PART 03 (CHAPTER 03 SECTION 01)
  UX_design               : PART 03 (CHAPTER 02 SECTION 03 dup)
  standards_accessibility : PART 03 (CHAPTER 03 SECTION 02/03)
  implementation          : PART 04
  color_system            : PART 05
  palette_system          : PART 06
  handoff_report          : PART 07

Axismundi_route 후보 distribution:
  corpus                  : 모든 PART의 raw notes/summaries
  atlas                   : workflow maps + design tool atlas + UX methodology atlas + 
                            CBD deliverable atlas + color planning workflow + 등
  core                    : enum + entity + workflow rule (대부분 SECTION에 후보 존재)
  decisions_candidate     : 사용자 자체 question (Hyperbolic Tree, CodePen 등) + 
                            Pilot-specific 적용 결정
  Pilot_harness_later     : Layout / Responsive / Components → Pilot template 적용
  TT5_audit_later         : 다음 cycle (v3.6.26) 입력
  Google_Sites_extraction_later : 다음 cycle 입력
  out_of_scope            : Embeds / Pattern Overrides / Block Bindings 등
```

### G1 / G2 relevance hot spots

```txt
G1 high (스타일가이드 → Pilot 구현 직접 영향):
  PART 03 CHAPTER 01 (IA / Wireframe / Storyboard)
  PART 03 CHAPTER 02 SECTION 02-03 (Design components, Grid, Responsive, UX)
  PART 03 CHAPTER 03 (Device, Standards, Accessibility)
  PART 04 CHAPTER 02 SECTION 02 (Screen + Interactive elements)

G2 high (wp.org submission 직접 영향):
  PART 07 CHAPTER 01 (Deliverables: SWEBOK / CBD)
  PART 07 CHAPTER 02 (Final report → readme.txt / portfolio)
  PART 01 CHAPTER 01 SECTION 03 (Copyright → LICENSE-MATRIX)
  PART 03 CHAPTER 03 SECTION 03 (Accessibility → wp.org submission 기준)

cross-cutting (Axismundi M3 token system과 연계):
  PART 05 (color system → md-ref tokens)
  PART 06 (palette → md-sys roles)
```

### Codex web research 우선순위 (다음 step 가이드)

```txt
priority 1 (G1 direct + 사용자 메모 detail 부족):
  PART 01 CHAPTER 01 SECTION 01 (기초데이터/레퍼런스)
  PART 02 CHAPTER 01 (전 sections, 사용자 메모 거의 없음)
  PART 03 CHAPTER 02 SECTION 04 (2D/3D 디자인 SW)
  PART 04 CHAPTER 02 SECTION 03 (웹 프로그래밍 개발)

priority 2 (G2 direct + 표준 reference 필요):
  PART 01 CHAPTER 01 SECTION 03 (산업재산권/저작권)
  PART 03 CHAPTER 03 (전 sections)
  PART 07 CHAPTER 01 SECTION 01 (SWEBOK V4)

priority 3 (cross-cutting + Axismundi M3와 연계):
  PART 05 (전 sections, 사용자 메모 없음)
  PART 06 (전 sections, 사용자 메모 없음)

priority 4 (사용자 메모 풍부, web research는 보완):
  PART 03 CHAPTER 01 (IA / wireframe / storyboard)
  PART 03 CHAPTER 02 SECTION 03 dup (UX / UI)
  PART 04 CHAPTER 01 SECTION 02 (웹디자인 프로세스)
  PART 07 CHAPTER 01 SECTION 02 (콘텐츠/데이터 정리)

priority 5 (사용자 자체 question 결정):
  PART 01 CHAPTER 02 SECTION 02 (Hyperbolic Tree / CodePen 결정)
```

---

## Next Steps (cycle 외 prep → cycle Phase 1 진입)

1. (별도 user trigger) Codex 웹서칭 detail 보완:
   - 위 Codex research TODO 항목 처리
   - 결과를 본 skeleton에 incremental update 또는 별도 file (예: `SKELETON-RESEARCH-FILL.md`)
2. (선택) 사용자 추가 메모 (`wdd.md` 추가 작성 또는 별도 첨부) 통합
3. v3.6.25 Phase 1 GO trigger:
   - 본 skeleton + research fill + 추가 메모를 source material로 Phase 1 진단 진입
   - Phase 0 plan의 13 Q evidence-based 답변
   - Decision Matrix schema test
   - corpus/atlas/core 라우팅 결정
   - decisions/ layer 신설 여부 verdict

Phase 1 진단 후 Phase 2에서 본 skeleton 내용이 corpus refined material로 promote, atlas workflow map + core enum/entity로 distilled.
