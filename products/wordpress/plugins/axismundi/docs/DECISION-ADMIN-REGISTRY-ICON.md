# 결정 기록: Admin RegistryIcon Proof of Concept

## 상태

채택됨. 2026-09-30.

## 문제

Admin은 Core icon과 Axismundi-owned SVG asset을 동일한 registry identifier로
소비할 수 있어야 한다. 그러나 Icon Registry REST API는 authenticated editor API이고,
SVG resolution은 비동기다. `@wordpress/icons`를 단순히 다른 import로 바꾸면 이
loading, missing, accessibility contract를 잃는다.

## 결정

`src/apps/admin/components/registry-icon/index.js`에 Admin-owned `RegistryIcon`
primitive를 둔다.

- same-origin `@wordpress/core-data` entity `root/icon`만 읽는다.
- qualified identifier인 `name`을 input으로 받는다.
- resolution 중에는 final icon과 같은 고정 크기의 inert placeholder를 출력한다.
- resolver가 끝났는데 record 또는 valid SVG가 없으면 아무 markup도 출력하지 않는다.
- label이 없으면 SVG는 decorative (`aria-hidden`, unfocusable)다.
- label이 있으면 SVG가 `role="img"`과 `aria-label`을 소유한다.
- REST content는 Core registry가 sanitize한 same-origin record만 render한다. component는
  임의의 remote SVG 또는 caller-provided markup을 받지 않는다.

첫 consumer는 Design sidebar의 `Styles` resource icon이다.

```text
Styles
  core/styles -> RegistryIcon
```

이는 generic control icon migration이 아니다. back, close, chevron과 현재
`sidebar-icons.js` compatibility asset은 기존 `@wordpress/icons`/local path를 유지한다.

## 원본 레퍼런스

- `C:/Users/thaum/dev/gutenberg/packages/block-library/src/icon/edit.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/block-library/src/icon/components/custom-inserter/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/core-data/src/entities.js`
- `docs/RESEARCH-ICON-REGISTRY.md`

## 제외 범위

- Axismundi collection 또는 Material Symbols SVG registration
- Frontend RegistryIcon
- `sidebar-icons.js` 제거
- generic control icon migration
- registry picker/catalogue

## 검증 기준

- authenticated Admin에서 `core/styles`가 REST/core-data resolution 후 render된다.
- request 중 item layout의 icon slot 크기가 변하지 않는다.
- 존재하지 않는 qualified identifier는 error UI나 unsanitized fallback SVG 없이 빈
  결과로 끝난다.
- `npm run build`와 `git diff --check`가 통과한다.

## 검증 결과

2026-09-30 localhost에서 다음을 확인했다.

- `/wp-admin/admin.php?page=axismundi&p=/design`의 `Styles` item은
  `core/styles`를 `root/icon` entity로 resolve한 뒤 `data-state="ready"` SVG를
  출력했다.
- loading과 ready 모두 item width는 `266px`, icon slot은 `24px x 24px`였다.
- authenticated `GET /wp/v2/icons/core/not-an-icon`은
  `404 rest_icon_not_found`를 반환했다.
- browser console error 없이 `npm run build`와 `git diff --check`를 통과했다.
