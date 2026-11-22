# Brief — Settings › Appearance: 밝기 · 대비 · 강조색

> **범위**: 클라이언트 GUI 의 외관 설정 한 화면과, 그것을 담을 Settings 하위 탐색. Rust·Tauri 명령·
> 연결 코드는 건드리지 않는다. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 셸 구조는
> [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md).

2026-11-22 작성. 아래 "소유자 결정" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 지시를 고치지 말고
멈추고 보고한다.

## 소유자 결정

```txt
위치        다이얼로그가 아니라 페이지  /settings/appearance  (Misskey·Mastodon 처럼).
            축이 셋이라 다이얼로그가 좁다.  빠른 라이트/다크 전환이 필요해지면 그때 메뉴를 따로 붙인다
토큰        mat.theme() 과 --mat-sys-* 만.  WordPress 플러그인의 --md-ref/--md-sys 를 가져오지 않는다
투자        클라이언트는 디자인 투자를 최소로 한다 — Material 부품(mat-radio-group, mat-button-toggle 등)을
            그대로 쓰고, 테마 엔진이나 런타임 색 계산을 만들지 않는다
```

## 화면

```txt
밝기     System · Light · Dark                  기본 System
대비     Default · High                         기본 Default
강조색   프리셋 스와치 몇 개                      기본 Azure (지금 값)
```

선택하면 앱 바·레일·현재 화면까지 즉시 바뀐다. 저장 버튼·미리보기 패널은 두지 않는다.

## 구현 지시

### 1. Settings 하위 탐색

지금 `/settings` 는 연결 화면 하나다. `/settings/connections`(지금 화면 그대로)와 `/settings/appearance` 로
나누고 `/settings` 는 connections 로 보낸다. 앱 곳곳이 `/settings` 로 이동한다(연결이 없을 때, 라우트 가드) —
그 동작이 그대로인지 확인한다. 하위 탐색 부품은 Material 의 것을 고른다(`mat-nav-list` 나 `mat-tab-nav-bar`).

### 2. 밝기 — `color-scheme`

`material-theme.scss` 의 `body { color-scheme: light; }` 고정을 풀고, 선택에 따라 `light` · `dark` ·
`light dark`(System) 를 문서 루트에 적용한다. `mat.theme()` 이 `light-dark()` 로 두 벌을 이미 내므로 색을 새로
만들 필요는 없다 — 그 전제를 실제 산출 CSS 에서 확인하고 시작한다.

### 3. 대비 — 설치본의 `theme-color` schematic

`ng generate @angular/material:theme-color` 에 `includeHighContrast` 옵션이 있다(설치본 22.2.7
`schematics/ng-generate/theme-color` 에서 확인). CSS 로 만들면 고대비 오버라이드가
`@media (prefers-contrast: more)` 안에, SCSS 로 만들면 mixin 으로 나온다.

```txt
Default   선택한 Angular Material 프리셋의 기본 색을 유지한다
High      프리셋별로 생성한 라이트·다크 오버라이드를 적용한다
```

**먼저 잴 것**: schematic 은 seed 색(hex)에서 팔레트를 새로 만든다. 그 결과가 지금의 `mat.$azure-palette`
와 같은지, 다르면 얼마나 다른지 산출 CSS 를 비교해 보고한다. 기본 대비의 색을 바꾸는 결정은 소유자에게 남긴다.

Windows 대비 테마(`forced-colors: active`)는 이것과 별개다. 앱이 그 위에 색을 덮지 않는다.

### 4. 강조색 — 프리셋

설치본의 팔레트(`core/theming/_palettes.scss`, 실측 12개):

```txt
red · green · blue · yellow · cyan · magenta · orange · chartreuse · spring-green · azure · violet · rose
```

프리셋은 Angular Material 문서 사이트의 테마 선택기와 같은 **primary · tertiary 짝 네 개**를, 같은 이름으로 쓴다
(소유자 결정 2026-11-22 — M3 디자인 문서의 Baseline·Static 시안을 재현하는 일은 WordPress 플러그인 몫이고,
클라이언트는 Angular Material 프리셋을 그대로 쓴다):

```txt
Azure & Blue        primary azure    · tertiary blue      기본값 (지금 값)
Rose & Red          primary rose     · tertiary red
Magenta & Violet    primary magenta  · tertiary violet
Cyan & Orange       primary cyan     · tertiary orange
```

짝의 정확한 지정은 문서 사이트 소스(angular/components 의 docs 테마 정의)에서 확인하고 적는다 — 위 표는 이름에서
읽은 것이다. 이 이름들은 M3 Baseline 이나 "Static – Blue" 같은 M3 시안과 대응하지 않는다. 프리셋마다 클래스 아래에
`mat.theme((color: ...))` 의 색만 다시 낸다. High에서는 네 프리셋 모두에
`includeHighContrast`로 생성한 오버라이드만 추가하며, 생성된 기본 팔레트로 기존 색을 교체하지 않는다.

임의 색 선택(color picker)과 OS 강조색 연동(다이나믹 컬러)은 범위 밖이다 — 런타임 팔레트 계산이 필요하다.

### 5. 저장과 첫 화면

선택값은 이 기기의 이 앱에만 둔다. 비밀이 아니므로 `localStorage` 로 충분하다(읽기·쓰기는 try/catch, 실패하면
기본값). 앱이 뜰 때 저장값을 **첫 페인트 전에** 적용해 라이트→다크 깜빡임이 없게 한다. 방법(index.html 인라인
스크립트, APP_INITIALIZER 등)은 고르되, Tauri CSP 가 인라인 스크립트를 막는지 먼저 확인한다.

### 6. 상태 → 클래스 매핑은 순수 함수로

`{brightness, contrast, accent}` → 루트에 걸 `color-scheme` 과 클래스 목록을 내는 함수를 Angular 없는 `.ts` 로
두고 `*.spec.ts` 로 검사한다(`node --test "gui/src/**/*.spec.ts"`, gui/AGENTS.md 의 방식). 저장값이 깨졌거나
모르는 값이면 기본값으로 떨어지는지도 본다.

## 접근성 (완료 조건)

라디오 그룹마다 이름(`aria-labelledby`), 스와치는 색만이 아니라 이름 텍스트를 갖는다. 키보드로 모든 선택 가능.
고대비 선택 시 실제 대비가 올라가는지 대표 화면(레일 선택 항목, 메일 목록의 미읽음 행)에서 계산값으로 잰다.

## 검증

```txt
npm --prefix gui run build                       경고 없이
node --test "gui/src/**/*.spec.ts"              매핑 함수 포함 전부
Tauri 창                                         밝기 3 × 대비 2 × 강조색 4 중 대표 조합을 눈으로 보고 스크린샷
                                                 재시작해도 선택이 유지되고 깜빡임이 없는지
```

`ng serve` 단독으로는 Tauri 명령이 없어 연결 화면이 비지만, 외관 화면 자체는 브라우저 패널에서도 볼 수 있다.

## 하지 않는 것

Rust·Tauri·연결·Mail 코드 변경, WordPress 토큰 이식, 런타임 다이나믹 컬러, 연속 대비 슬라이더, 서버로의 동기화.
커밋은 주제별로 좁게(하위 탐색 / 밝기 / 대비 / 강조색), `main` 에 직접.
