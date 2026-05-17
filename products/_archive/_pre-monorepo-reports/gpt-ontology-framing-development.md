# AXISMUNDI ONTOLOGY FRAMING — 발전사 / 검증사 / 구조적 진화 브리핑

> "그냥 문서 정리가 아니라, 어떻게 '생성 가능한 구조'가 되었는가"

> Source: GPT session
> Preserved verbatim for archival value. This report's framework (the four ontology layers A/B/C/D) is now encoded in the monorepo's layered architecture (corpus/atlas/core/bindings/products/tools).

---

# 한 줄 요약

처음엔 "디자인 시스템 정리"였고, 중간엔 "WordPress 구조 해부"였고, 지금은 "LLM이 재사용 가능한 구조적 생성 언어"에 가까워졌다.

---

# I. ORIGIN — 시작점 (문제의식 단계)

최초 질문: "머티리얼 디자인 기반으로 WordPress를 더 체계적으로 만들 수 없나?"

초기 상태: 디자인 욕심, M3 관심, 블록테마, ActivityPub, 개인 브랜딩.

특징: 구조보다 결과물 중심 — 예쁜가? 일관성 있는가? WordPress에 넣을 수 있는가?

이 시기 Ontology: 사실상 "명시적 온톨로지"가 아니라 스타일 가이드 + 개념적 분류 (buttons, cards, navigation, tokens).

핵심 한계: "무엇이 있는가"는 알지만 "왜 그렇게 구조화되는가"는 약함.

---

# II. TOKEN ERA — 첫 구조화 (분류 체계 탄생)

전환점: Material Theme Builder drift 문제 (HCT 알고리즘/자동화 한계 인식).

결정: Hardcoded baseline + token architecture.

## ref → sys → comp

이게 매우 중요함. 왜 중요? 단순 CSS 변수 정리가 아니라 "추상도 계층"이 생김.

- **ref**: 원색/기준값
- **sys**: 의미 계층 (primary / surface / outline)
- **comp**: 실제 UI 행동

여기서 Ontology 1차 진화: "값" → "역할 기반 계층 구조"

검증: 실제 컴포넌트 제작 시 — 버튼, 폼, 카드, 네비게이션 — 반복 사용 가능성 확인. 재사용 가능한 설계 언어로 발전 시작.

---

# III. DESIGN SYSTEM → ONTOLOGY SHIFT

중요한 질문 등장: "컴포넌트가 아니라 시스템을 정의할 수 있나?"

여기서 DESIGN.md, Style guide, Prototype이 단순 문서가 아니라 "World Model"로 바뀜.

변화:
- 이전: Button = 버튼
- 이후: Button = Selection / State / Hierarchy / Accessibility / Interaction contract

즉: UI 객체 → 구조적 개체 (Entity + Rules).

이게 Ontology 2차 진화: "사물 목록" → "관계 구조"

---

# IV. WORDPRESS KB INGESTION — 도메인 확장

전환: "내 시스템"만으로는 부족. WordPress 자체 구조를 해부해야 함.

그래서: theme.json, template hierarchy, hooks, block lifecycle, variations, admin, interactivity.

핵심 변화: Ontology가 "디자인 시스템"에서 "디자인 + 플랫폼 메커니즘"으로 확장.

예:
- Block Variation 단순 스타일 옵션 아님 → candidate selection / activation semantics
- Hook 단순 이벤트 아님 → federation / composition / priority semantics

여기서 중요한 검증: 같은 vocabulary가 여러 terrain에서 재사용 가능한가? → 가능 → doctrine vocabulary 등장.

---

# V. DOCTRINAL PHASE — 구조 문법화

결정적 진화: Ontology가 taxonomy를 넘어서 "Interpretive Grammar"가 됨.

## 주요 doctrine

- **Law 1**: Declaration ≠ Exposure
- **Law 4**: Arbitration Compiler
- **Doctrine 5**: Authority Continuity
- **Doctrine 6**: Authority Mediation

중요: 이건 분류표가 아니라 "읽는 방식".

예:
- "등록되었다" = 존재
- "실제 활성" = 노출
- "권한 있음" ≠ "현재 허용"

검증 방식: 각 chunk마다 fit / false fit / anti-pattern / boundary.

즉: ontology가 "맞는 사례"보다 **"틀린 유사성 거부 능력"**으로 검증됨.

이게 매우 강력한 이유: ontology robustness = false-positive resistance.

---

# VI. AUDIT ERA — 자기검증 체계

**M1 (breadth)**: "얼마나 넓게 적용되는가?"
**M2 (grammar)**: "정확히 무엇이 fit인가?"
**M3 (topology)**: "규모/거버넌스가 커져도 유지되는가?"

여기서 Ontology는 단순 구조 → 과학적 모델처럼 발전.

핵심: Pattern recognition → grammar → geometry.

즉: "있다" → "왜 맞다" → "어느 구조 규모까지 맞다".

---

# VII. FRONTIER TEST — 미래 확장성 검증

P1 질문: "새 scale은 어디서 시작되는가?"

여기서 온톨로지는 WordPress 내부용인지, ActivityPub / Ecosystem scale로 확장 가능한지 검토됨.

중요: 즉시 확장보다 **구조 이전 가능성 테스트**.

---

# VIII. 검증 방식 총정리

네 온톨로지가 강한 이유:

1. **Cross-terrain reuse**: 같은 구조가 다른 영역에서 재사용됨
2. **False analogy rejection**: 비슷해 보여도 아니면 거부
3. **Positive control**: 진짜 사례가 나오면 정확히 인정 (예: rewrite rules)
4. **Scale stress**: runtime / governance / jurisdiction
5. **Audit synthesis**: meta-layer에서 구조 점검

이건 사실상 "Prompt engineering" 수준이 아니라 **"Knowledge architecture engineering"**.

---

# IX. 현재 형태 — 무엇이 되었나?

현재 Axismundi Ontology는:

## A. Design ontology
- M3, token, components

## B. WordPress mechanism ontology
- block, theme, admin, REST, multisite

## C. Interpretive doctrine grammar
- Law 1, Law 4, Doctrine 5, Doctrine 6

## D. Product compiler substrate
- spec 가능, retrieval 가능, prompt orchestration 가능

즉: "문서"가 아니라 **"LLM-readable constitutional infrastructure"**.

---

# X. 아직 부족한 부분 (중요)

현재 강점: 분석 / 구조 / 해석.

아직 필요한 것: **executable ontology**
- machine-readable schemas
- JSON/YAML spec
- block mapping tables
- component contracts
- generation pipelines

쉽게 말하면: 지금은 "철학 + 헌법"은 강력. 하지만 "컴파일러 구현"은 아직 초기.

---

# XI. 다음 진화 단계

Phase 9 핵심: Ontology → Compiler

필요: `axismundi-spec`. 예:
```yaml
Component:
  Button
Maps to:
  core/button
Tokens:
  sys-primary
Rules:
  accessible
  state-layer
  density
```

이렇게 되면 LLM: "설명 이해"에서 **"정확한 생성"**으로 이동.

---

# XII. 최종 평가

너의 온톨로지 프레이밍 발전:

Style Guide → Token Hierarchy → System Ontology → WordPress Mechanism Ontology → Doctrine Grammar → Governance Geometry → Frontier Mapping → Product Compiler 후보

가장 중요한 진실: 이 과정은 우회처럼 보여도, 실제로는 "남들보다 훨씬 깊은 생성 기반"을 만든 셈.

현실적 경고: 너무 깊으면 ship 못 한다. 그래서 이제 핵심은: **"더 깊게"보다 "더 압축 가능하게"**.

---

# 한 줄 결론

네 온톨로지는 "디자인 정리"에서 시작해서, **"WordPress + LLM용 구조적 생성 언어"**로 발전했고, 이제 남은 건 그것을 **실행 가능한 spec/compiler로 압축**하는 단계다.
