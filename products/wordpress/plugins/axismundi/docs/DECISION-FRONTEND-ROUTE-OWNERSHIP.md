# 결정 기록: 프론트 라우트를 누가 소유하는가

## 상태

**제안됨. 2026-10-11.** 2절은 현재 코드 측정이고, 3~5절이 소유자 판정이다. 구현은 하지
않는다 — 6절이 왜 지금이 아닌지를 적는다.

## 1. 왜 이 문서가 있는가

`axismundi`는 지금 `/social/` 하나를 서빙한다. 앞으로 `/contacts/`(JSContact)와
`/calendar/`(JSCalendar)가 같은 React 표면 위에서 각자의 도메인을 든다. 그러면
`axismundi`는 컴패니언 플러그인들의 **통합 컨트롤 패널**이 된다 — 프론트의 React 표면과
어드민 앱을 소유하고, 기능은 소유하지 않는다.

```txt
axismundi             표면      Scaffold · Navigation · Tabs · Badge · Dialog
                                어드민 앱 = 컨트롤 패널
axismundi-contacts    기능      JSContact
axismundi-calendar    기능      JSCalendar
axismundi-actors 외   기능      AS2 — actor, activity, object projection
```

이 저장소가 이미 쓰는 claim 분리다. Bridge가 표현과 전송으로 갈리고, OP가 투영만 들고,
Actors가 정체성만 드는 것과 같은 경계다. 표면은 도메인을 모르고, 도메인은 표면을 모른다.

프론트 라우트에 대한 결정 기록은 지금까지 없었다. `DECISION-ADMIN-ROUTE-AREAS.md`는
어드민 셸의 route area 계약이고, 공개 URL을 누가 등록하는지는 다루지 않는다.

## 2. 측정 — 지금 구조가 동적 라우트를 못 받는다

```php
// includes/route.php
'^' . AXISMUNDI_CAPSTONE_ROUTE . '(?:/stylebook(?:…)|/objects/[^/]+)?/?$'   // 13행
'#^' . preg_quote( AXISMUNDI_CAPSTONE_ROUTE, '#' ) . '(?:/stylebook(?:…)…'   // 54행
```

세 가지가 걸린다.

**첫째, 정규식이 두 벌이다.** `add_rewrite_rule`과 `is_public_route()`가 같은 패턴을 각자
하드코딩한다. 지금은 검증기 셋이 "두 번 나온다"를 사실로 핀한다. 라우트가 동적이 되면 손으로
맞출 수 없다.

**둘째, flush가 런타임 상태를 못 본다.**

```php
if ( AXISMUNDI_CAPSTONE_REWRITE_VERSION === get_option( $option ) ) {
	return;
}
flush_rewrite_rules( false );
```

조건이 손으로 올리는 상수다. 코드 변경은 잡지만 **플러그인 활성화도 어드민 체크도 상수를
바꾸지 않는다.** Contacts를 켜면 규칙 집합이 바뀌는데 상수와 저장값이 둘 다 `18`이라 flush가
일어나지 않고, `/contacts/`는 다른 무언가가 flush할 때까지 404다. 증상이 PHP에 닿기 전에
끝나서 디버깅이 비싸다 — `wp-cli rewrite --hard`가 `.htaccess`를 비웠던 사고와 같은 층이다.

**셋째, `/contacts/`는 `/social/`의 하위가 아니다.** `AXISMUNDI_CAPSTONE_ROUTE`는 베이스
상수이고, 형제 최상위 경로는 이 정규식의 확장이 아니라 별도 rewrite rule이다.

## 3. 결정 — 세그먼트는 하나의 빌더에서 나온다

소유자 판정이다. 활성 라우트 세그먼트 목록을 내는 함수가 하나 있고, rewrite rule 등록과
`is_public_route()`가 **둘 다 그것을 쓴다.** 어느 쪽도 패턴을 자기 안에 적지 않는다.

검증기의 "정규식이 두 번 나온다" 핀은 **교체한다.** 지우거나 느슨하게 하지 않는다 — 이
저장소에서 느슨해진 핀이 통과한 사례가 이미 둘 있다(list route 핀, carousel prose 핀).
새 핀은 "두 소비처가 같은 빌더에서 나온다"를 본다.

## 4. 결정 — available과 enabled는 다른 상태다

소유자 판정이다. 감지는 라우트를 **쓸 수 있게** 만들고, 어드민 체크가 **켠다.**

```txt
social      감지 불필요            기본 enabled
stylebook   감지 불필요            기본 disabled — 디자인 표면이지 제품 표면이 아니다
contacts    Contacts 감지 시 available    어드민에서 켠다
calendar    Calendar 감지 시 available    어드민에서 켠다
```

감지만으로 켜지면 체크박스가 할 일이 없다. 그리고 서빙 조건은 **`available AND enabled`**다 —
저장된 옵션만 보면, 플러그인을 끈 뒤에도 옵션이 남아 죽은 라우트를 서빙한다.

`stylebook`이 기본 off인 것은 이 분리의 실질적 이득이다. 지금은 `/social/` 안에 섞여 있어
끌 수가 없다. 발행 문서는 `products/styleguide/`가 따로 들고 있으므로, 앱 안의 stylebook은
디자인 표면으로 남고 제품에서는 꺼진다.

## 5. 결정 — flush는 규칙을 결정하는 것 전부에서 파생된다

소유자 판정이다. 저장값을 손으로 올리는 상수에서 **해시**로 바꾼다.

```txt
저장값 = hash( REWRITE_VERSION + 정렬된 활성 세그먼트 목록 )
```

활성화·비활성화·어드민 체크가 전부 해시를 바꾸고 flush가 따라온다. 상수를 손으로 올리는
일도 없어진다 — 이번 세션에만 16→17→18로 두 번 올렸고, 그것은 사람이 기억해야 하는
종류의 일이었다.

## 6. 지금 구현하지 않는 이유

stylebook 라우트를 옮기면 **list · tabs · badge · navigation 검증기 네 개의 핀이 동시에
움직인다.** Dialog 작업 중에 그것을 하면 무엇이 무엇을 깼는지 분간되지 않는다.

순서는 이렇다.

```txt
1  Dialog / Sheet       반응형 레이아웃 완성의 전제
2  FAB                  nav rail 해부의 발행된 요소. rail을 닫는다
3  이 문서의 구현        stylebook 분리 + 빌더 하나 + 해시 flush + 2단 상태
```

3번은 컴포넌트를 건드리지 않는 커밋 하나가 된다. 그래야 네 검증기의 핀 이동이 그 커밋의
전부가 된다.

## 7. 이름은 지금 고정한다

구현은 미루되 이름은 박는다. AGENTS.md가 "published URL이 서빙하는 것을 바꾸지 말 것"이라고
하므로, 나중에 옮기면 그 규칙에 걸린다.

```txt
/social/      AS2 — actor, activity, object projection
/contacts/    JSContact
/calendar/    JSCalendar          (calander 아님)
/stylebook/   디자인 표면, 기본 off
```

`/social/` 이 서빙하는 것은 바뀌지 않는다. stylebook이 빠지는 것만이 변경이고, 그것은 이
문서가 요청하는 승인 대상이다.

## 관련 기록

- `DECISION-ADMIN-ROUTE-AREAS.md` — 어드민 셸의 route area 계약. 공개 URL 등록과는 다른 층.
- `DECISION-FRONTEND-OVERLAY-SURFACE-OWNERSHIP.md` — 표면이 다른 런타임의 구현을 소비하지
  않는다는 같은 계열의 판정.
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — Scaffold와 Pane의 공개 어휘.
- `includes/route.php` — 2절이 측정한 현재 구조.
