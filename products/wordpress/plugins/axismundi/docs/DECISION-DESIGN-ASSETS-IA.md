# 결정 기록: Design Assets Information Architecture

## 상태

채택됨. 2026-09-30.

## 문제

Axismundi Frontend는 block theme가 아닌 독립 React application이다. Fonts, icons,
emojis는 모두 Frontend presentation에 필요하지만 서로 같은 registry나 data model을
공유하지 않는다. Admin은 각 native system을 대체하지 않으면서 관리와 탐색의 중심
surface를 제공해야 한다.

## 결정

Design 아래에 Assets hierarchy를 둔다.

```text
Design
  Assets
    Fonts
    Icons
    Emojis
```

각 항목은 URL route를 가진다.

```text
/design/assets
/design/assets/fonts
/design/assets/icons
/design/assets/emojis
```

Assets는 Design root에서 실제 sidebar drilldown screen으로 전환한다. Assets child의
Back은 URL을 유지한 채 Assets screen으로 돌아가고 focus를 원 trigger로 복원한다. Assets
root의 Back은 `/design`으로 이동하며 Design root의 Assets trigger에 focus를 복원한다.

## 책임 경계

Assets는 새로운 universal registry가 아니다. native authority는 유지한다.

```text
Fonts   WordPress Font Library / Font APIs
Icons   WordPress Icon Registry + Axismundi local icon manifest
Emojis  Unicode capability + custom emoji catalogue + rendering policy
```

Admin Assets는 이 authority들을 discovery, normalization, management UI, 그리고
Frontend delivery policy로 연결하는 hub다. `/social/`이 authenticated Admin REST
endpoint를 직접 소비한다는 뜻은 아니다.

## 이번 checkpoint 범위

- route와 sidebar hierarchy만 추가한다.
- Font API, Icon Registry query, emoji runtime 또는 custom emoji data를 fetch하지 않는다.
- Assets route에는 Frontend preview iframe을 강제하지 않는다.

## 후속 순서

1. Icons catalogue: `root/iconCollection`과 `root/icon`의 read-only projection.
2. Axismundi icon manifest의 optional catalogue enrichment join.
3. Font APIs research와 Fonts catalogue.
4. Unicode/custom emoji/rendering policy research와 Emojis control panel.

`shared/assets` 또는 generic asset catalogue abstraction은 Fonts와 Icons의 실제
consumer가 만들어진 뒤 공통점이 확인될 때만 추출한다.

## 검증 결과

2026-09-30 localhost에서 다음을 확인했다.

- `/design/assets`는 Fonts, Icons, Emojis link를 가진 Assets nested sidebar screen을
  출력하며 Frontend preview iframe을 렌더하지 않는다.
- `Design -> Assets -> Icons -> Back -> Back`에서 첫 Back은 Assets의 Icons trigger에,
  두 번째 Back은 Design의 Assets trigger에 keyboard focus를 각각 복원한다.
- 해당 interaction 중 browser console error가 없었다.
