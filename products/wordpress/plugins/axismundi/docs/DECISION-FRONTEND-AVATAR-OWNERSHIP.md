# 결정 기록: Avatar — 무엇을 컴포넌트로 두고 무엇을 호스트에 남길 것인가

## 상태

**제안됨. 2026-10-05.** 구현 경계(2~5절)는 이미 채택된 기록에서 따라 나오므로, 뒤집으려면
그 기록들을 같이 바꿔야 한다. 6절의 미결은 소유자 판정이다.

아직 `Avatar` 컴포넌트는 없다. 이 문서는 만들기 전에 경계를 적어 두는 것이고, 7절이 *언제*
만들지를 다룬다.

## 1. 왜 이 문서가 있는가

**M3는 Avatar를 발행하지 않는다.** Figma 키트에 에셋이 있지만 그것은 Lab이나 VQA와 같은
급이다 — 구조 참고는 되고 수치 출처는 아니다. 그러므로 크기·모양·fallback은 **전부 우리
것**이고, 발행값이 없다는 사실 자체를 기록해 두지 않으면 나중에 누가 Figma에서 숫자를
옮겨 적고 그것을 스펙으로 읽는다.

그리고 용처가 처음부터 둘이다.

```txt
app bar의 trailing          acting actor — 지금 어느 정체성으로 행동 중인가
팔로잉 / 팔로워 목록         어떤 Actor의 묘사 — 프로필로 가는 링크이거나 비상호작용
```

## 2. 두 용처는 두 가지다

같은 이름으로 부르지 않는다. 하나는 **컨트롤**이다 — 눌리고, switcher를 열고, 포커스를
받고, 상태를 갖는다. 다른 하나는 **묘사**다 — 어떤 Actor가 이렇게 생겼다는 것 외에 아무
주장도 하지 않는다.

이 구분을 놓치면 어떻게 되는지는 이 저장소가 방금 비싸게 배웠다. navigation item에서 outer
target과 active indicator를 한 단어로 부르는 바람에 세 가지 결함이 났고, 그때마다 당시의
근거로는 그럴듯한 수치로 보였다(`DECISION`이 아니라 `navigation_item.yml`의 `structure`에
기록돼 있다). 아바타는 지금 그 자리에 있다 — **아직 두 번째 호스트가 없어서 경계가 공짜로
보이는 상태**다.

## 3. 묘사만 컴포넌트로, 행동은 호스트가

```txt
Avatar                 이미지 / fallback / 크기. role 없음, tabindex 없음, 상태 없음
app bar의 호스트        Avatar를 switcher를 여는 버튼으로 감싼다
목록 행의 호스트         Avatar를 프로필 링크로 감싸거나, 그냥 둔다
```

`AvatarButton`과 `AvatarLink`를 형제로 만들지 않는다. 그렇게 하면 크기·모양·fallback 규칙을
두 벌로 복제하게 되고, 그것이 `DECISION-FRONTEND-COMPONENT-CONVENTIONS.md`가 `IconLink`를
따로 만들려다 철회한 이유와 같다 — "둘은 하는 일이 다르지만 보이는 것은 같은 컨트롤이라,
나누면 사이즈·폭·색 규칙을 전부 복제해야 한다".

Card의 `as` 허용 목록이 정한 방향도 같다. 상호작용 의미를 **컨트롤에** 얹는 것은 위험하고,
**컨테이너에서는 위험이 반대로 흐른다**. Avatar는 후자다.

## 4. Actor 객체를 받지 않는다

컴포넌트는 **해소된 값**만 받는다.

```jsx
<Avatar src={ … } name={ … } size="…" />
```

Actor를 넘기면 컴포넌트가 Actor를 해소하는 법을 배우게 되고, 그 지식은 다음 컴포넌트로
퍼진다. 이것은 `icon`을 이름이 아니라 노드로 받는 규약과 같은 종류의 경계이고,
`DECISION-FRONTEND-CORE-PRESENTATION-ISOLATION.md`가 지키는 선이기도 하다.

**이미지의 출처는 이미 Actors가 소유한다.** 2026-10-05 확인:

```txt
axismundi-actors/includes/avatar.php       로컬 Person actor의 avatar_attachment_id를
                                           WordPress get_avatar에 얹는다. Person 한정
axismundi-actors/includes/asset-cache.php  원격 Actor의 avatar/header 바이너리 캐시.
                                           "network fetches happen only from cron
                                           workers; render-time helpers read
                                           already-generated local derivatives"
```

그러므로 **원격 아바타를 핫링크할지 캐시할지는 미결이 아니다** — 이미 캐시된다. (핫링크가
미결로 남아 있는 것은 수신 객체의 *첨부 미디어* 쪽이고, 이 컴포넌트와 무관하다. 이 문단은
그 둘을 혼동한 구두 판단을 정정한다.) 남는 질문은 좁다: 렌더 시 **어느 파생본 크기를
요청하는가**, 그리고 그 선택을 누가 하는가.

## 5. 수치는 우리 것이므로 그렇게 이름 붙인다

크기·모양·fallback에 발행값이 없으므로, 토큰처럼 보이는 이름을 주지 않는다.

```css
@layer axismundi.theme {
	.ax-avatar {
		--ax-avatar-size: …;
	}
}
```

`--md-comp-`는 Material 토큰으로 읽힌다. 이 세션에서 nav bar의 horizontal 상한을
`--md-comp-nav-bar-item-horizontal-max-width`로 두었다가 같은 이유로
`--ax-nav-bar-item-horizontal-max-width`로 옮기고 `axismundi.theme` 레이어에 둔 선례가 있다.
M3가 나중에 Avatar를 발행하면 그때 토큰으로 교체하고 컴포넌트는 건드리지 않는다.

## 6. 미결 — 소유자 판정

- **fallback이 무엇인가.** 이미지가 없을 때 이니셜인지, 생성된 도형인지, 글리프인지.
  이것은 모양 문제가 아니라 **정체성 표현** 문제다 — 이니셜은 handle인가 display name인가,
  Group과 Person이 같은 fallback을 쓰는가, 원격 Actor에 display name이 없으면 무엇인가.
- **acting actor 진입점이 자기 컴포넌트인가.** app bar의 그 자리는 "누구로 행동 중인가"를
  보여주고 switcher를 연다. Avatar를 감싼 IconButton으로 충분한지, `ActingActorButton` 같은
  것이 필요한지는 switcher 표면이 생길 때 드러난다. Actors의 acting Actor switcher는 이미
  있다.
- **파생본 크기 선택을 누가 하는가.** 컴포넌트가 `size`로 고르는가, 호출자가 해소된 `src`에
  이미 담아 오는가. 후자가 4절과 일관되지만, 그러면 모든 호출자가 크기 대응표를 알아야 한다.
- **어느 플러그인의 것인가.** `Avatar`가 axismundi 프론트앱의 컴포넌트인지, Actors가 제공하는
  표현인지. 지금 제안은 전자다 — 묘사일 뿐이고 Actor를 모르기 때문이다.

## 7. 언제 만드는가 — 기능이 끌 때

**지금 만들지 않는다.** 두 호스트가 다 없는 상태에서 만들면 어느 경계가 하중을 받는지 모른 채
짓게 된다. navigation item은 **두 호스트의 토큰 표를 나란히 놓고 나서야** 무엇이 공유이고
무엇이 호스트의 것인지 갈랐고, 그 순서를 뒤집었다면 bar에서 맞고 rail에서 틀린 컴포넌트가
나왔을 것이다.

acting actor 표면과 팔로우 목록 중 **먼저 오는 쪽이 생길 때** 만들고, 두 번째 호스트가
도착할 때 그것이 무엇을 다르게 요구하는지 **재서** 경계를 다시 긋는다. 첫 호스트만으로
그어진 경계는 추론이고, 그 사실을 그때 기록한다.

레이아웃 순서와도 충돌하지 않는다. 다음 작업은 window size class 관측 → canonical layout →
Surface이고(`DECISION-FRONTEND-NAVIGATION-COMPONENT-SLICING.md` §5의 연장), Avatar는 그
사슬에 들어 있지 않다.

## 관련 기록

- `DECISION-FRONTEND-COMPONENT-CONVENTIONS.md` — 노드로 받기, `IconLink` 철회, slot 계약
- `DECISION-FRONTEND-CORE-PRESENTATION-ISOLATION.md` — 표현 층이 무엇을 몰라야 하는가
- `products/styleguide/_data/navigation_item.yml` `structure` — 두 상자를 한 이름으로 부른 대가
- `axismundi-actors/includes/avatar.php`, `includes/asset-cache.php` — 이미지의 출처
