# 결정 기록: Axismundi Icon Library

## 상태

채택됨. 2026-09-30.

## 문제

Admin은 Core icon과 Axismundi가 관리하는 Material Symbols subset을 같은 WordPress
Icon Registry에서 소비해야 한다. 그러나 registry의 `name`, `label`, `content` contract만으로는
asset provenance, licence, 검색어, 원본 glyph 이름을 보존할 수 없다.

## 결정

Axismundi가 소유하는 정적 SVG와 catalogue metadata는 다음 경로에 둔다.

```text
includes/images/icon-library/
  manifest.json
  *.svg
  LICENSE.md
```

`includes/icons.php`는 manifest를 읽어 `axismundi` collection과 각 `axismundi/*` icon을
자동 등록한다. registry identifier는 UI의 의미를 표현하고, Material Symbols glyph 이름은
manifest의 provenance metadata에 남긴다.

```text
axismundi/styles       <- Material Symbols: palette
axismundi/templates    <- Material Symbols: browse
axismundi/patterns     <- Material Symbols: brick
axismundi/components   <- Material Symbols: component_exchange
axismundi/icons        <- Material Symbols: interests
```

각 SVG는 file path로 등록한다. Core가 필요할 때 읽고 sanitize하므로 plugin은 모든 SVG
markup을 request마다 메모리에 올리거나 inline하지 않는다.

## 소유권과 경계

- `core/*`는 Core가 소유하며 Axismundi는 read-only consumer다.
- `axismundi/*`는 이 plugin이 소유하는 curated set이다. Google의 전역
  `material-symbols/*` namespace를 점유하지 않는다.
- `axismundi-contacts/*`는 Contacts plugin의 독립 product boundary를 유지한다.
- Admin `RegistryIcon`은 authenticated `root/icon` core-data entity를 소비한다.
  `/social/`은 registry REST를 직접 asset transport로 사용하지 않는다.
- back, close, chevron 같은 generic control icon과 `sidebar-icons.js`는 이번 결정의
  대상이 아니며 기존 `@wordpress/icons`/local compatibility path에 남긴다.

## 파일 정규화와 licence

Material Symbols export의 `fill="#e3e3e3"`, `width`, `height`만 제거한다. icon의
색상과 size는 rendering surface가 소유해야 하며, path와 `viewBox`는 바꾸지 않는다.
세부 provenance와 Apache-2.0 licence는 같은 directory의 `LICENSE.md`와 manifest에
기록한다.

## 의도적으로 미룸

- `Template Parts`는 충분히 정확한 semantic glyph 후보가 정해질 때까지 기존 icon을
  유지한다.
- `Design > Icons` catalogue route와 picker UI는 registry의 consumer이며 source of truth가
  아니다. catalogue는 별도 checkpoint에서 Google Fonts식 탐색 UX와 Core icon library를
  비교해 설계한다.
- full Material Symbols mirror와 generic chrome migration은 하지 않는다.

## 검증 기준

- authenticated `/wp/v2/icons/axismundi`가 manifest의 curated record만 반환한다.
- Design sidebar의 resource item이 `axismundi/*` qualified identifier를 resolve한다.
- registered SVG에는 hard-coded exported fill 또는 fixed width/height가 없다.
- manifest의 malformed entry나 collection collision은 registration을 중단하거나 skip하며,
  다른 plugin collection에 icon을 추가하지 않는다.

## 검증 결과

2026-09-30 localhost wp-env에서 authenticated `GET /wp/v2/icons/axismundi`가 `200`과
여섯 개의 curated record를 반환했다.

```text
axismundi/design
axismundi/styles
axismundi/templates
axismundi/patterns
axismundi/components
axismundi/icons
```

그 REST payload에는 exported `#e3e3e3` fill, `width="24px"`, `height="24px"`가 하나도
남지 않았다. `/wp-admin/admin.php?page=axismundi&p=/design`에서도 Styles, Templates,
Patterns, Components가 각자의 `axismundi/*` record를 resolve한 뒤 `ready` SVG로
render됐고 browser console error는 없었다.
