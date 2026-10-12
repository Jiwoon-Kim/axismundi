# 결정 기록: overlay Surface를 누가 소유하는가

## 상태

**채택됨. 2026-10-10.** 3절과 5절은 소유자 판정이다. 2절은 측정이고, 6~8절은 그 판정에서
따라 나온다.

## 1. 왜 이 문서가 있는가

`DECISION-FRONTEND-SCROLL-OWNERSHIP.md` 3-2절은 overlay escape를 **결정하지 않고 금지**해
두었다. 그 금지는 "첫 overlay 컴포넌트가 escape 계약을 정하고, 그 기록이 이 금지를 해제하거나
유지한다"는 조건을 달고 있다.

그런데 이 저장소에는 이미 overlay가 출하되어 있다. `axismundi-dialogs`가 dialog,
object media dialog, post quick view를 소유하고, `includes/surface.php`라는 Surface 추상까지
갖고 있다. 그러므로 먼저 답해야 하는 질문은 escape 계약이 아니라 **그것이 "첫 overlay"인가**,
즉 React 프론트앱이 그 계약을 소비하는가였다.

## 2. 측정 — dialogs의 Surface는 블록 테마 표면이다

```txt
axismundi-dialogs/includes/surface.php:57
  add_filter( 'default_wp_template_part_areas', ... )
```

`surface.php`가 등록하는 것은 **template-part area**다. 렌더 경로가 `wp_template_part`이고,
편집 표면이 사이트 편집기이며, 소비자가 블록이다. 이것은 React SPA가 가져다 쓸 수 있는
모듈이 아니다 — 추상이 부족해서가 아니라, **다른 런타임의 렌더 경로를 계약으로 들고 있기**
때문이다.

focus·scroll lock·dismiss 로직 자체는 `blocks/dialog/view.js`에 있으므로 **읽을 가치는 있다.**
그러나 그것을 가져오는 일은 라이브러리를 소비하는 것이 아니라 코드를 옮기는 것이다.

## 3. 결정 — 프론트앱은 평행한 자체 Surface를 갖는다

소유자 판정이다. React 프론트앱은 자기 Surface를 소유하고, `axismundi-dialogs`의 것을
소비하지 않는다.

이것은 중복 구현을 허용한다는 뜻이 아니다. 두 표면이 공유하는 것과 공유하지 않는 것을
가른다는 뜻이다.

```txt
공유한다        md.comp.dialog / sheet 토큰, surface.yml의 수치와 금지 조합,
                presentation x breakpoint 계약
공유하지 않는다  렌더 경로, 템플릿 파트 등록, PHP, 편집 표면
```

`products/styleguide/_data/surface.yml`이 그 공유 지점이다. 수치가 두 곳에 있으면 갈라지므로,
List가 `list-segmented.json`과 교차 핀된 것과 같은 방식으로 묶는다.

관리 표면을 나중에 React로 다시 만들 가능성은 이 결정을 바꾸지 않는다. 그때는 어드민 앱이
생기는 것이고, 그 앱이 프론트앱의 Surface를 소비할지는 그 시점의 판정이다.

## 4. 결정 — 행 단위 swipe는 구현하지 않는다

소유자 판정이다. Gmail 류의 swipe-to-archive는 브라우저 기반 PWA에 적절하지 않다.

M3 원문도 같은 방향을 가리킨다. swipe-to-reveal은 **Android Views 전용**으로 표시되어 있고,
원문 스스로 "swipeable list items should include alternative ways to access hidden actions,
such as a more icon"이라고 쓴다. 즉 swipe는 대체 경로가 있어야 하는 보조 수단이고, 대체 경로가
본체인 플랫폼에서는 본체만 두면 된다.

`PLAN-FRONTEND-HOME.md`의 Section 5는 "Swipeable area + column of List items"로 적혀 있다.
이 결정에 따라 그 **swipeable은 행 단위 swipe-to-reveal이 아니다.** Section 5를 구현할 때
Figma 프레임에서 그 영역이 무엇인지 읽고, 가로 스크롤 영역이면 기존 Carousel로 구성한다.
행 swipe로 읽히더라도 구현하지 않고, trailing action 또는 overflow menu로 대체한다.

`list.yml`의 `interactions_deferred`에 있는 `swipe`는 이제 **보류가 아니라 비채택**이다.
기록을 그렇게 고쳐야 한다.

## 5. 구현 순서

```txt
1  Tabs                      게이트 없음. 토큰 표 있음, overlay 의존 없음
2  Surface: Dialog / Sheet    반응형 레이아웃 완성의 전제
3  Menu                       2의 overlay / focus / dismiss 계약을 소비     닫힘
4  Search                     컴포넌트가 아니라 제품 흐름. 부품이 먼저
```

1~3은 닫혔다. 1은 Tabs, 2는 Dialog·SideSheet·BottomSheet, 3은 vertical standard Menu와
그 group 축이다. Menu의 **서브메뉴는 같은 행의 남은 slice**이지 새 행이 아니다 — M3가
발행한 Menu의 표면이고, `_data/menu.yml`이 무엇이 미구현인지와 그 사유를 들고 있다.

Tabs가 먼저인 이유는 중요도가 아니라 **의존이 없다는 것**이다. 2번은 surface.yml 대조와
escape 계약 기록을 먼저 요구하므로, 그 사이에 멈추지 않기 위해 1번이 앞에 있다.

Dialog를 미룰 수 없는 이유는 반응형이다. Surface는 **presentation x breakpoint**이므로, compact
에서 dialog가 sheet 또는 전체 화면이 된다. list-detail canonical layout이 compact에서 detail을
여는 경로가 바로 그것이다. Dialog 없이는 반응형 레이아웃이 닫히지 않는다.

Search를 마지막에 두는 이유는 `core/search`가 이미 Search Bar bridge로 테마 쪽에 있고, React
쪽에 필요한 것은 search field + 결과 ListItem + Dialog 또는 pane의 조합이기 때문이다. 부품이
전부 있어야 조합이 가능하다.

### 5-1. Search는 claim이 둘이다 (2026-10-12 보강)

"컴포넌트가 아니라 제품 흐름"이라는 위 문장이 **하나의 작업처럼 읽힌다.** 둘이다.

```txt
표면    search field · 결과 ListItem · 필터 칩 자리 · 결과가 앉는 Dialog 또는 pane
        axismundi가 소유. 도메인을 모른다.
흐름    무엇을 질의하고 무엇으로 거르는가
        각 도메인이 소유. /social/은 AS2, /contacts/는 JSContact.
```

`DECISION-FRONTEND-ROUTE-OWNERSHIP.md`가 그은 경계 그대로다 — 표면은 도메인을 모르고
도메인은 표면을 모른다. 그러므로 **표면은 흐름을 기다리지 않는다.** 부품 조건은 이제
충족되었고(Dialog·Sheet·List item·Menu), 표면 claim은 착수 가능하다.

흐름 쪽은 기다린다. 교차 소스 질의의 기반이 `PLAN-OBJECT-LIST-QUERY.md`의 A5
(`wp_ax_object_index`)이고 그것은 고의로 미구현이다. 커스텀 테이블과 표준 테이블을 한
결과로 묶는 일을 Search가 자기 파이프라인으로 다시 푸는 것은 **두 번째 인덱스를 만드는
것**이고, 그 문서가 식별자 수명·재구축·캐시 축출로 씨름한 문제를 그대로 복제한다.

같은 이유로 외부 검색 엔진도 지금 결정하지 않는다. 인덱스는 비공개 오브젝트를 포함하고
(§4-5), 인가는 서버가 강제하므로, 외부 인덱스는 아직 존재하지 않는 viewer 평가기(A4)를
그쪽에도 요구한다.

## 6. scroll 3-2절의 금지는 유지된다

**2026-10-12 보강: 이 절의 조건은 충족되었고 금지는 그대로다.** 아래가 요구한
"첫 overlay 컴포넌트"와 escape 계약 기록은 둘 다 생겼다 —
`DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`가 그 기록이고, Surface 세 행이 그 구현이다.
그리고 그 기록이 고른 답은 **top layer이므로 조상의 overflow가 무관하다**는 것이어서,
금지를 풀 이유가 생기지 않았다. `.ax-scaffold`가 overflow를 선언하지 않는 상태는 이제
"판정 전이라 보류"가 아니라 **채택된 결과**다. scroll 쪽 사정은
`DECISION-FRONTEND-SCROLL-OWNERSHIP.md` 3-6이 함께 본다.

프론트앱이 평행 Surface를 갖기로 했으므로, **그 "첫 overlay 컴포넌트"는 아직 만들어지지
않았다.** `axismundi-dialogs`의 출하는 다른 런타임의 기록이므로 금지를 해제하지 않는다.

```txt
.ax-scaffold, .ax-scaffold__content, .ax-scaffold__body
  visible 외의 overflow를 선언하지 않는다
```

위 금지는 5절 2번이 escape 계약을 기록할 때까지 그대로다. 해제든 유지든 그 기록이 한다.

## 7. 별건 결함 — 사이트 편집기에 dialog surface 영역이 보이지 않는다

소유자 관측이다. `site-editor.php?p=/pattern`에 dialog surface가 떠야 하는데 뜨지 않는다.

이 문서의 결정과는 무관하다 — 3절은 그 표면을 소비하지 않기로 했으므로, 결함이 고쳐지든
아니든 프론트앱 계획은 바뀌지 않는다. 그러나 `axismundi-dialogs`의 결함이므로 별도로 처리한다.

먼저 확인할 가설은 하나다. `surface.php`가 등록하는 것은 **영역**이지 파트가 아니므로, 그
영역에 속한 `wp_template_part`가 하나도 없으면 목록에 섹션이 생기지 않을 수 있다. 추측하지
말고 해당 영역의 파트를 질의해서 센다.

이미 기록된 두 사고도 같은 증상을 낸다. 사이트 편집기 저장본이 테마 파일을 가리는 경우와,
패턴 캐시가 비워지지 않아 새 파일이 등록되지 않는 경우다. 세 가설을 차례로 배제한다.

## 관련 기록

- `DECISION-FRONTEND-SCROLL-OWNERSHIP.md` — 3-2절의 금지와 그 해제 조건.
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — Scaffold와 Pane의 공개 어휘.
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` — breakpoint와 pane 전환.
- `products/styleguide/_data/surface.yml` — dialog / sheet 수치와 금지 조합의 공유 지점.
- `products/styleguide/_data/list.yml` — 4절에 따라 swipe 항목을 비채택으로 고쳐야 한다.
- `PLAN-FRONTEND-HOME.md` — Section 5의 swipeable 해석.
