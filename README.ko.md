# Axismundi

**독립적이고 연합된 발행을 위한 WordPress 제품군.**

Material 3 블록 테마와, 정체성·주소록·활동·장소·일정·미디어·공개 웹 발행을 다루는
동반 플러그인들입니다.

- 제품 목록과 상태: [README.md](README.md)
- 스타일가이드: <https://jiwoon-kim.github.io/axismundi/styleguide/>
- 아키텍처 기준: [CONSTITUTION.md](CONSTITUTION.md)
- 작성자 및 최종 결정권자: KIM JIWOON (designbusan.ai.kr) — Busan, Korea. [AUTHORSHIP.md](AUTHORSHIP.md)

영문 README가 바깥쪽 입구이고, 이 문서는 **저장소를 몇 달 뒤에 다시 열었을 때 필요한
맥락**을 적습니다. 코드를 읽어서는 복원되지 않는 것들입니다.

## 언어 정책

경계는 영어, 설명은 한국어입니다.

```
영어    URL · 파일명 · 슬러그
        토큰명 · CSS 변수 · 블록명
        컴포넌트명 · 표준 용어
        코드 예시와 API
한국어  설명 · 결정 이유 · 사용 기준
```

플러그인과 테마의 **코드 주석은 영어**입니다. wp.org 제출 대상이고 저장소 바깥
사람이 읽을 코드이기 때문입니다. 반대로 저장소 안쪽 설계 문서는 한국어가 낫습니다 —
번역을 거치면서 결정의 뉘앙스가 먼저 사라집니다.

## 왜 이렇게 쪼개져 있나

플러그인이 20개가 넘는 것은 기능을 잘게 나눠서가 아니라, **소유권 경계를 지키려면
그렇게 되기 때문**입니다.

**Actors가 먼저입니다.** 정체성 레지스트리가 URI를 소유하고, 나머지 도메인
플러그인은 자기 아카이브를 거기에 연결합니다. 정체성을 각 도메인이 따로 들고 있으면
같은 사람이 도메인 수만큼 생깁니다.

**Object Projections는 표현만 소유합니다.** WordPress 객체를 ActivityStreams
JSON-LD로 투영하는 transformer registry와 renderer 하나. 상태를 갖지 않습니다.

**Activities는 원장이고 배달은 하지 않습니다.** HTTP inbox·서명·배달 큐를 직접
구현하지 않고 공식 ActivityPub 플러그인을 S2S transport로 씁니다. 그 경계를 잇는 것이
ActivityPub Bridge입니다. **연합 프로토콜을 재구현하지 않는다**는 것이 이 프로젝트의
가장 큰 범위 결정입니다.

**Notifications는 원장에서 투영됩니다.** 각 전이를 소유한 도메인이 투영하고, 알림함은
그것을 모읍니다. 알림이 자기 상태를 따로 쌓지 않습니다.

같은 이유로 **테마는 표현만** 가집니다. 지속되는 커스텀 블록, 에디터 UI, 외부 프로토콜
연동, 데이터 저장은 전부 플러그인 territory입니다.

## 토큰이 흐르는 방향

```
--md-ref-palette-*        리터럴 팔레트. 값이 여기에만 있음
    ↓
--md-sys-color-*          역할. 반드시 var(--md-ref-palette-*)
    ↓
--wp--preset--color--*    WordPress가 theme.json에서 런타임 생성
```

이 방향은 검증기가 강제합니다. `--md-sys-color-*`에 리터럴 hex을 쓰면 실패합니다.
색을 한 군데서만 바꿀 수 있게 하려는 것이고, 런타임 팔레트 교체(Theme Controls)가
성립하는 이유이기도 합니다.

**CJK 폰트는 이음매로 남겨져 있습니다.** 테마는 `var(--axismundi-cjk-sans, system-ui)`를
선언만 하고, 지역별 폰트 제공 플러그인이 `:lang()` 아래에서 그 슬롯을 채웁니다.
`unicode-range`로 Noto의 적용 범위를 한글에 한정합니다. 테마 하나에 CJK 폰트를 전부
넣으면 쓰지 않는 언어의 폰트까지 내려받게 됩니다.

## 세 개의 표면을 헷갈리지 말 것

```
products/wordpress/                   출하되는 정본
products/styleguide/                  공개 디자인 시스템 문서 (Jekyll, CI 배포)
products/reference-implementations/   손코딩 검증 작업대 (비공개)
```

**스타일가이드는 제품에서 읽어옵니다.** 빌드 시점에 테마의 토큰 CSS와 `theme.json`을
가져와 문서를 만듭니다. 팔레트나 타입 스케일을 따로 옮겨 적지 않는다는 뜻이고, 그래야
문서가 "제품이 실제로 렌더하는 것"을 보여줍니다. 옮겨 적은 사본은 반드시 어긋납니다 —
이 저장소에 그렇게 어긋났던 자산 브리지가 있었고, 그래서 지웠습니다.

**Lab은 남아 있습니다.** 컴포넌트를 테마·플러그인 코드로 만들기 전에 손으로 구현해
측정하는 자리입니다. 스타일가이드가 결과를 설명하고, Lab이 근거를 보관합니다. 둘을
합치면 "측정한 것"과 "설명한 것"의 구분이 사라집니다.

Lab은 더 이상 공개 배포되지 않습니다. GitHub Pages가 Actions 아티팩트로 바뀌면서
저장소 전체를 서빙하지 않게 됐고, Lab 페이지는 파일 시스템에서 바로 열면 됩니다.

## 사본에는 검증기를 붙인다

이 저장소의 규칙 하나. **두 번째 사본을 만들었으면 검증기를 함께 만듭니다.**

생성물은 커밋하고, 생성기는 `--check`로 손편집을 거부하며, 검증기는 생성기가 옳은지와
별개의 질문에 답합니다. 예를 들어 스타일가이드 레이아웃은

- 생성기 `--check` — 커밋된 CSS가 생성기 출력과 같은가
- 검증기 — 그 값이 사양·`theme.json`과 같은가

두 질문이 다르기 때문에 둘 다 필요합니다. **버그 있는 생성기는 첫 질문을 늘
통과합니다.**

미디어쿼리가 `var()`를 읽지 못한다는 것이 이 파이프라인의 출발점이었습니다.
브레이크포인트 값은 빌드 때 CSS 텍스트에 리터럴로 박혀야 하고, 그러면 문서의 표와
실제 쿼리가 갈라질 수 있으므로, 한 소스에서 둘 다 내보내고 검증기가 대조합니다.

## 개발

의존성 설치, 스타일가이드 빌드·검증, 토큰 층 검증 순입니다.

```powershell
npm install
python .\products\styleguide\bin\build.py --verify
python .\tools\validators\validate_token_layering.py
```

테마와 플러그인은 `wp-env`로 개발합니다. 각 제품 디렉터리에 자체 실행 메모가 있습니다.

## 라이선스

표면별 multi-license입니다.

- code / theme / tooling: GPL-3.0-or-later
- documentation: CC BY-SA 4.0
- ontology / binding data: CC BY-SA 4.0
- third-party assets: upstream license 보존

[LICENSE](LICENSE), [LICENSE-CC-BY-SA-4.0.md](LICENSE-CC-BY-SA-4.0.md),
[LICENSE-MATRIX.md](LICENSE-MATRIX.md), [NOTICE.md](NOTICE.md)를 참고하세요.

## 후원

[GitHub Sponsors](https://github.com/sponsors/Jiwoon-Kim)에서 후원할 수 있습니다.
