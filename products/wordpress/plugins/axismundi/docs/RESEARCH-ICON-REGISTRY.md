# 조사 기록: WordPress Icon Registry와 Axismundi Admin

## 상태

조사 완료. 2026-09-30.

이 기록은 Admin 아이콘을 즉시 교체하는 구현 결정이 아니다. WordPress 7.1.2에서
Icon Registry가 Axismundi의 Admin visual infrastructure로 쓸 수 있는지, 그리고
어떤 경계에서만 써야 하는지를 확인한 research spike다.

## 결론

WordPress Icon Registry는 **Admin에서 registry-backed domain/resource icon을
소비하기에 적합하다.** Core icon, Axismundi가 소유한 Material Symbols SVG,
Axismundi 고유 SVG를 동일한 qualified identifier로 다룰 수 있다.

그러나 registry는 모든 아이콘 import를 대체하는 동기 icon component API가 아니다.
REST 요청은 `edit_posts` 또는 REST 공개 post type의 edit capability를 요구하고,
React 소비는 비동기 data resolution을 거친다. 따라서 이번 spike에서는 기존
`sidebar-icons.js`와 `@wordpress/icons` 기반 generic chrome을 교체하지 않는다.

## 조사 대상

### 실행 환경

- Axismundi plugin: `Requires at least: 7.1`
- 실행 중인 wp-env Core: `7.1.2`
- Gutenberg plugin: `23.7.1`

`wordpress-develop` checkout에는 이미 7.2 예정 `public` property가 보인다. 이
문서는 실행 중인 7.1.2 contract를 기준으로 하며, 7.2 API에는 의존하지 않는다.

### 원본 레퍼런스

- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/icons.php`
- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/class-wp-icons-registry.php`
- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/class-wp-icon-collections-registry.php`
- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/rest-api/endpoints/class-wp-rest-icons-controller.php`
- `C:/Users/thaum/dev/wordpress-develop/src/wp-includes/rest-api/endpoints/class-wp-rest-icon-collections-controller.php`
- `C:/Users/thaum/dev/gutenberg/packages/block-library/src/icon/edit.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/block-library/src/icon/components/custom-inserter/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/core-data/src/entities.js`
- `C:/Users/thaum/dev/gutenberg/packages/widget-primitives/src/icon-resolver/icon-resolver.ts`
- `C:/Users/thaum/dev/axismundi/products/wordpress/plugins/axismundi-contacts/includes/icons.php`

## Core contract

### Collection과 identifier

icon은 먼저 등록된 collection에만 등록할 수 있다.

```php
wp_register_icon_collection(
	'axismundi',
	array(
		'label' => __( 'Axismundi', 'axismundi' ),
	)
);

wp_register_icon(
	'axismundi/person',
	array(
		'label'     => __( 'Person', 'axismundi' ),
		'file_path' => __DIR__ . '/assets/icons/person.svg',
	)
);
```

Collection slug와 icon name은 lowercase letter/digit로 시작하고 끝나야 하며,
내부에는 lowercase letter, digit, hyphen, underscore만 허용된다. icon의 public
identifier는 `collection/icon-name` 형식이다. collection 또는 identifier가 이미
등록되어 있으면 registration은 실패한다.

`core/*`는 읽기 위한 Core collection이다. Axismundi는 그 namespace에 icon을
등록하지 않는다.

### Collection ownership

`material-symbols/*`는 문법상 가능한 collection slug지만, Axismundi가 Google의
전역 namespace를 소유한다는 뜻으로 사용하면 안 된다. Axismundi plugin이 들여와
관리하는 Material Symbols subset은 다음처럼 Axismundi-owned collection에 둔다.

```text
axismundi/person
axismundi/widgets
axismundi/federation
```

asset의 실제 provenance와 Apache-2.0 licence는 asset manifest 또는 `LICENSE.md`에
기록한다. 이미 독립 제품 경계를 가진 Contacts는 기존처럼
`axismundi-contacts/*` collection을 계속 소유한다.

### SVG와 server-side rendering

`wp_register_icon()`은 `content` 또는 `file_path` 중 정확히 하나를 요구한다.
`file_path`의 SVG는 첫 필요 시 읽히며 registry singleton 안에서 그 request 동안
cache된다. 등록/읽기 과정에서 Core의 허용 tag/attribute 목록으로 `wp_kses()`
sanitization을 거친다.

PHP에서는 다음 helper가 size, class, accessible label을 SVG에 적용한다.

```php
wp_get_icon( 'axismundi/person', array( 'size' => 24 ) );
```

따라서 server-rendered block이나 PHP surface는 registry를 직접 소비할 수 있다.

## REST와 React contract

### Routes와 권한

Core 7.1은 다음 read-only routes를 등록한다.

```text
GET /wp/v2/icon-collections
GET /wp/v2/icon-collections/<slug>
GET /wp/v2/icons
GET /wp/v2/icons/<collection>
GET /wp/v2/icons/<collection>/<icon>
```

모든 route는 `edit_posts` 또는 `show_in_rest` post type의 edit capability를 요구한다.
비인증 `GET /wp/v2/icons`는 실제 wp-env에서 `401 rest_cannot_view`를 반환했다.
그러므로 이 REST API는 anonymous `/social/` asset transport가 아니다.

### 실제 wp-env 결과

다음 명령으로 현재 컨테이너에서 확인했다.

```text
npx --no-install wp-env run cli wp core version
# 7.1.2

npx --no-install wp-env run cli -- wp eval 'wp_set_current_user(1); ...'
```

Admin user request의 실제 응답은 다음이었다.

```json
[
	{
		"slug": "core",
		"label": "WordPress",
		"description": "Core icon collection."
	},
	{
		"slug": "axismundi-contacts",
		"label": "Axismundi Contacts",
		"description": "Material Symbols used by the contacts screens."
	}
]
```

`GET /wp/v2/icons/axismundi-contacts`는 각 icon에 `name`, `label`, sanitized SVG
`content`, `collection`을 반환했다. 이는 `core/icon`이 기대하는 data contract와
같다.

### Core editor의 소비 방식

`core/icon`은 별도 private icon client를 쓰지 않는다. `@wordpress/core-data` entity
`root/icon`의 key인 qualified `name`으로 record를 읽고 `content`를 render한다.
picker는 `root/iconCollection`과 `root/icon` entity를 collection query로 읽는다.

```js
const { getEntityRecord } = select( coreDataStore );
const icon = getEntityRecord( 'root', 'icon', 'core/arrow-left' );
```

따라서 Admin의 첫 client는 새 REST wrapper보다 `@wordpress/core-data` entity를
재사용하는 것이 맞다. Core data store가 해당 browser session의 resolution/cache를
소유한다.

`@wordpress/widget-primitives`의 `registerIconResolver()`도 registry reference를
renderable icon으로 바꾸는 hook을 제공한다. 하지만 package 자체가 experimental이고
application-wide singleton resolver를 사용한다. Axismundi Admin의 기본 icon API로
채택하지 않는다.

## Security, cache, and surface boundary

- REST SVG는 Core registry가 sanitize한 markup이지만, Admin `RegistryIcon`은
  same-origin `core-data` record만 render한다. arbitrary remote SVG string을
  `dangerouslySetInnerHTML`에 넣지 않는다.
- registry의 `file_path` content cache는 PHP request-local이다. persistent cache와
  invalidation policy는 Core API가 제공하지 않는다. Admin은 client-side core-data
  cache로 충분한 작은 icon set만 요청한다.
- Admin은 authenticated wp-admin request와 WordPress REST nonce/bootstrap을 이미
  갖는다. `/social/`은 이 endpoint를 직접 소비하지 않는다. Frontend에 registry
  icon이 필요해지면 server-rendered `wp_get_icon()`, allowlisted bootstrap payload,
  또는 별도의 public asset policy를 새 결정으로 다룬다.
- React icon component의 visual contract는 surface-owned다. registry client/data는
  shared infrastructure가 될 수 있어도 Admin과 Frontend가 같은 React icon component를
  공유하지 않는다.

## 채택 후보와 제외 범위

다음 후속 prototype은 가능하다.

```text
includes/icons.php
  Axismundi-owned collection과 제한된 SVG asset registration

src/apps/admin/components/registry-icon/
  @wordpress/core-data record를 읽고 WPDS/Admin 문맥에서 render하는 primitive
```

그러나 다음은 이 spike의 범위 밖이다.

- `sidebar-icons.js` 제거 또는 chevron 교체
- `@wordpress/icons` generic control icon의 일괄 migration
- full Material Symbols catalog 등록
- `/social/`에서 authenticated icon REST API 사용
- `material-symbols` collection slug의 전역 소유

다음 구현 전에는 Admin `RegistryIcon`의 loading, unresolved icon, decorative/labelled
accessibility state를 component contract로 별도 기록해야 한다.

## 7.2 주의

local `wordpress-develop` source는 future 7.2에서 icon `public` property를
추가하는 중이다. 7.1.2 plugin contract는 그것에 의존하지 않는다. registry-backed
icon을 `core/icon` picker에 노출하지 않아야 하는 요구가 생기면, 7.2 minimum version
상향 또는 별도 collection/client policy를 다시 결정한다.
