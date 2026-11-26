# Brief — 인터페이스 레이아웃 두 가지 (Navigation rail / Search app bar)

> **범위**: GUI(셸 + 메일 화면 + 설정). M3 Scaffold(Bar · Rail · Pane)의 두 배치를 Angular 로 구현한다 — 우리 WP 프런트에
> 이미 있는 Scaffold 계약(`appBarScope: 'pane' | 'window'`)을 옮기는 일이다. Bulwark 1.21.3 의 "인터페이스 레이아웃" 두
> 모드가 바로 이 두 배치(M3 예시 그대로)라 완성 화면 비교 기준으로 쓴다 — 코드는 가져오지 않는다(AGPL). 규칙은
> [gui/AGENTS.md](../gui/AGENTS.md), 현재 화면은 [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md).

2026-11-26 작성. "소유자 결정" 과 "Bulwark 에서 확인한 것" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 멈추고
보고한다.

## 소유자 결정

```txt
범위      인터페이스 레이아웃 두 모드만: Navigation rail(기본) / Search app bar.  메일 레이아웃(화면 분할·집중형 목록·하단 읽기 창)은 다음
순서      레이아웃 → 에디터 비교 → 리치 텍스트
이름      M3 컴포넌트 이름 그대로 — Navigation rail = Outlook·Misskey 처럼 왼쪽 레일이 앱 전환을 맡는 배치(지금),
          Search app bar = m3.material.io/components/app-bars 의 search app bar 가 창 위를 맡는 배치.
          값 interfaceLayout: 'navigation-rail' | 'search-app-bar', 설정 라벨도 이 두 이름
기본값    Navigation rail = 지금 Axismundi 배치 그대로
완료 판정 크롬의 Bulwark 와 Axismundi 를 나란히 열어 목록 / 메일 연 상태 / 좁은 창에서 두 모드를 직접 비교한 뒤
```

## M3 Scaffold 와 우리 기존 계약 (근거)

```txt
M3        Scaffold = 안전 영역 · Bar · Pane · Rail (m3.material.io/foundations/layout/scaffold).  Reply 예시 = Rail +
          Pane 1(목록, 위에 검색 bar) + Pane 2(상세).  웹 예시 = 창 전체 위 Bar(검색) + 왼쪽 메뉴·Compose·내비 + 오른쪽 Rail
스타일가이드 products/styleguide/_foundations/layout.md — 용어표, "rail 은 자리, navigation rail 은 거기 앉는 것",
          list-detail(단일 pane 일 때만 상세에 뒤로 가기, 두 pane 일 때만 목록에 선택 표시), supporting pane 360dp
WP 프런트 products/wordpress/plugins/axismundi/src/apps/frontend/foundations/layout/scaffold/index.js — 슬롯
          appBar · navigationRail · navigationBar · companion(뒤쪽 rail) · supporting · sheet, 그리고
          appBarScope 'pane'(bar 가 pane 위) | 'window'(bar 가 창 전체)
대응      Navigation rail = navigationRail 슬롯 사용 + appBarScope 'pane' (지금: 목록 pane 위 search bar)
          Search app bar  = navigationRail 비움 + appBarScope 'window' (창 전체 bar 가 search app bar)
          Bulwark 오른쪽 아이콘 줄(캘린더·할 일·연락처) = companion rail — 이번 범위 밖
```

`appBarScope` 는 bar 가 걸치는 범위만 정한다. `interfaceLayout` 은 그 위에서 앱 탐색(rail/menu)·메일 열기(옆/교체)·
Compose 위치까지 정한다 — 판정은 `gui/src/app/shell/scaffold-layout.ts` 한 곳. 나중에 메일 레이아웃 옵션이 들어와도
검색 위치와 읽기 방식이 엉키지 않게 둘을 섞지 않는다.

Angular 쪽은 이 슬롯 이름과 `appBarScope` 를 그대로 쓰는 셸(Scaffold) 컴포넌트로 — 같은 개념을 두 제품에서 다르게 부르지
않는다. WP 프런트 코드(React)를 복사하지는 않는다(계약·이름만).

## Bulwark 에서 확인한 것 (1.21.3, `D:/axismundi-interop/bulwark-webmail-1.21.3` — 참고만)

```txt
설정      설정 → 화면 설정 → 레이아웃 → "인터페이스 레이아웃 — 검색, 탐색, 도구 모음의 위치"
          값 interfaceLayout: 'default' | 'mountain-view'(라벨 "검색창은 위").  미리보기 그림 포함
기본      왼쪽 앱 레일 + 폴더 패널, 검색은 목록 열 위, 오른쪽 읽기 창("앱과 폴더는 왼쪽, 검색은 목록 위")
검색창은 위  components/layout/mv-top-bar.tsx 문서 주석: 데스크톱에서 64px 한 줄 전역 머리 — 햄버거·브랜드·검색·
          계정 묶음 — 이 **왼쪽 레일을 대신**한다(모든 앱 화면에서).  앱 전환은 머리 오른쪽 격자 메뉴(메일·캘린더·
          연락처·파일·설정).  "메일 쓰기" 는 폴더 패널 맨 위.  메시지를 열면 목록 대신 전체 너비로 열림
          ("검색은 맨 위에, 메시지는 전체 너비로 열림").  목록 행 모양·호버 동작·메뉴도 이 모드에서 조금 다르다
          (email-list.tsx·thread-list-item.tsx·email-hover-actions.tsx — 이번엔 배치만, 행 모양 차이는 보고만)
모바일    별도 mobile-header — 좁은 창은 두 모드가 거의 같다(확인해서 보고)
```

## 지금 Axismundi (Navigation rail 모드의 출발점)

```txt
셸     app.component — 위 mat-toolbar(제목·Accounts) + 왼쪽 레일(Mail·Settings)
메일   mail-page — mat-sidenav 메일함 패널, 목록 pane 위 standalone search bar, 목록 오른쪽 아래 Compose FAB,
       오른쪽 읽기 pane.  EXPANDED 840 / LARGE 1200 접힘
설정   /settings/appearance — HTML 본문 테마 등
```

## 지시

0. **셸 Scaffold**: app.component 의 지금 셸(toolbar + rail + main)을 Scaffold 슬롯(appBar · navigationRail ·
   navigationBar · companion · 본문 pane)과 `appBarScope` 로 다시 짠다. 메일 화면은 자기 bar(search bar)를 `pane` 이면
   목록 pane 위에, `window` 면 Scaffold 의 appBar 슬롯에 넣는다(같은 컴포넌트 한 개).
1. **설정**: 화면 설정(appearance)에 "Interface layout" — `Navigation rail` / `Search app bar`, 미리보기 작은 그림(선택). 값은 앱
   전체 설정으로 저장(기존 appearance 저장 방식과 같게). 바꾸면 다시 시작 없이 즉시 적용.
2. **Navigation rail 모드**(기본): 위 app bar 없음(소유자 결정 2026-11-26) — 계정 메뉴는 레일 맨 아래 Settings 밑으로,
   진행 표시줄은 콘텐츠 위 가장자리에 겹친다. 나머지는 지금 배치 그대로. 회귀 없음을 스크린샷으로.
3. **Search app bar 모드**(EXPANDED 840 이상) — M3 app bars 의 search app bar 구성(앞 아이콘·검색 필드·뒤 아이콘)을 따른다:
   - 셸 머리 한 줄(높이 64): 햄버거(메일함 패널 접기·펴기), 앱 이름, **검색 바**(지금 목록 위의 standalone search bar 를
     여기로 — 같은 컴포넌트, 같은 focused 뷰·결과 동작), 오른쪽 앱 격자 메뉴 + 계정.
   - 왼쪽 앱 레일은 숨긴다. 앱 전환은 격자 메뉴(지금 있는 목적지 — Mail·Settings, 나중 Contacts·Calendar)로.
   - 목록 pane 위 검색 바는 없앤다(머리로 옮겨 갔으므로 둘이 동시에 보이지 않게).
   - Compose 는 메일함 패널 맨 위(Extended FAB 또는 tonal 버튼 — Bulwark 처럼 패널 맨 위), 목록 오른쪽 아래 FAB 는
     이 모드에서 숨긴다.
   - 메시지를 열면 목록 대신 **콘텐츠 영역 전체 너비**의 읽기 화면, 뒤로(툴바 ←·Esc)로 목록에 돌아온다 — 목록 스크롤·
     선택 유지(지금 좁은 창의 "목록 ↔ 상세" 전환을 넓은 창에서도 쓰는 것과 같다).
4. **좁은 창**(840 미만): 두 모드의 차이를 실측해 정한다. Bulwark 가 거의 같게 두면 우리도 지금 compact 그대로.
5. **검색 상태 공유**: 모드를 바꿔도 검색어·결과·focused 뷰 동작이 그대로(같은 검색 서비스·컴포넌트를 위치만 바꿔
   그린다). 검색 바를 둘로 복제하지 않는다.
6. **설정 화면도 같은 셸**: Search app bar 모드에서 설정 화면은 머리의 검색 바를 비활성 또는 숨김(검색 대상 없음) —
   Bulwark 는 설정 화면에서도 같은 머리를 쓴다(확인해 보고).

## 열린 결정 — 좁은 창(840 미만)의 앱 이동

지금은 두 모드 모두 좁은 창에서 navigation rail(80px)로 돌아간다. M3 는 compact 에 navigation bar(아래, 목적지 3–5개)를
권하고, 그보다 많으면 modal navigation drawer 다. 목적지가 Mail·ActivityPub·Settings 에서 Contacts·Calendar·Files 로
늘면 bar 5칸을 넘으므로, 무엇을 bar 에 고정하고 무엇을 drawer/더보기로 보낼지는 그 앱들이 생길 때 함께 정한다
(2026-11-26 보류). **지금은 좁은 창에서 navigation rail 유지**, 하단 navigation bar 는 이번 범위 밖. 완료 판정의 Bulwark
비교는 넓은 창 두 모드 + 좁은 창 메일 사용 흐름까지이고, 좁은 창의 탐색 방식은 Bulwark 와 맞추지 않는다. 바꿀 때 손댈 곳은 `scaffold-layout.ts` 의 compact 판정과 셸의 navigationBar 슬롯 하나.

## 하지 않는 것

메일 레이아웃(화면 분할·집중형 목록·하단 읽기 창), 목록 행 모양 변경, 호버 동작·메뉴 차이, 앱 격자에 아직 없는 앱
추가, 설정 화면 개편, 키보드 단축키.

## 검증

```txt
node --test "gui/src/**/*.spec.ts"   레이아웃 설정 읽기·저장, 모드별 표시 판정(순수 함수로)
npm --prefix gui run build
```

창에서(원격 디버깅 CDP — gui/AGENTS.md), **크롬의 Bulwark(http://localhost:3000) 와 나란히**, 두 모드 × 세 상태:

1. 목록(1280): 머리·레일·검색 위치, Compose 위치.
2. 메일 연 상태(1280): Navigation rail = 오른쪽 읽기 pane, Search app bar = 전체 너비 + 뒤로.
3. 좁은 창(400): 두 모드 차이.
4. 모드 전환 즉시 반영, 검색 중에 전환해도 검색어·결과 유지.

각 상태 스크린샷을 Bulwark 것과 짝지어 보고에 붙인다. 차이(의도·미구현)를 표로.

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
