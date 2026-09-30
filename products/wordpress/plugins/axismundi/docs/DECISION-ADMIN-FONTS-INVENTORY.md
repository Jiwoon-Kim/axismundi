# 결정 기록: Admin Fonts Read-only Inventory

## 상태

채택 및 구현됨. 2026-09-30.

## 문제

`Design > Assets > Fonts`는 WordPress가 가진 font resource를 Axismundi Admin에서
탐색할 수 있어야 한다. 그러나 Axismundi Frontend는 block theme가 아니므로 WordPress
Font Library의 install/global-styles activation을 Frontend enablement로 표시하거나
수정해서는 안 된다.

## 결정

Fonts route는 WordPress native font data의 read-only projection으로 시작한다.

```text
postType/wp_font_family
  -> Installed: WordPress에 등록된 local font family inventory

root/fontCollection
  -> Collections: WordPress에 등록된 available font source inventory
```

`Installed`는 WordPress resource 존재 상태만 뜻한다. Axismundi Frontend가 실제로
사용하거나 활성화한 상태를 뜻하지 않는다.

## 구현 범위

`/design/assets/fonts`는 다음을 표시한다.

- installed font family name, slug, CSS family, static/variable capability
- family에 embedded된 `font_faces`의 style, weight, stretch, variation settings,
  display, unicode range, source
- registered font collection name과 description
- loading, empty, error state

font family item을 선택하면 route를 변경하지 않고 같은 page의 detail panel을 바꾼다.
이는 resource inspection UI이며 editor navigation hierarchy가 아니다.

## 데이터 contract

Admin은 `@wordpress/core-data`를 직접 사용한다.

```js
useEntityRecords( 'postType', 'wp_font_family', {
    _embed: true,
    context: 'edit',
    orderby: 'title',
    order: 'asc',
    per_page: 100,
} );

useEntityRecords( 'root', 'fontCollection', {
    _fields: 'slug,name,description',
    per_page: 100,
} );
```

`wp_font_family.font_family_settings`와 `_embedded.font_faces[].font_face_settings`는
WordPress Font Library가 쓰는 same-origin Core data contract다. 별도 REST client나
Axismundi font registry는 만들지 않는다.

## 제외 범위

- font upload, install, delete
- `wp_global_styles` activation/deactivation
- Font Library의 `GlobalStylesProvider` 또는 private editor API import
- Axismundi Frontend enablement or `style.json` typography write
- font collection browse/search/install UI
- generic Asset catalogue abstraction

## 검증

2026-09-30 localhost에서 다음을 확인했다.

- direct `/design/assets/fonts` route가 Axismundi Fonts screen으로 복원된다.
- font data loading이 완료되고 browser console error가 없다.
- current site에는 installed `wp_font_family` record가 없어 empty state가 출력된다.
- `Google Fonts`, `Axismundi Emoji`, `Axismundi Korean Font Provider` collection이
  native `root/fontCollection` data로 출력된다.
- description/metadata text color는 white content surface에서 readable contrast를 유지한다.

## 후속

Admin Fonts는 여기서 잠시 동결한다. 다음 frontend checkpoint는 theme foundation-only
asset contract와 `style.json` color generation path를 구현한다. Font resource와
Axismundi Frontend typography policy를 연결하는 결정은 그 runtime contract 뒤에 다룬다.

## 레퍼런스

- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/src/apps/admin/routes/design/assets/fonts/index.js`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/src/apps/admin/styles/fonts-inventory.css`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/RESEARCH-FONT-LIBRARY-WP-ADMIN.md`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi/docs/DECISION-DESIGN-ASSETS-IA.md`
