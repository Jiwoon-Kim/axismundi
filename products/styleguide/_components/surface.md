---
title: Surface
description: Dialog와 Sheet를 하나로 묶는 표면 계약 — 네 가지 presentation, breakpoint별 전환, 템플릿 파트 콘텐츠
kind: axismundi
order: 50
lang: ko
---

Material 컴포넌트 하나가 아닙니다. M3의 **Dialog**, **Bottom sheet**, **Side sheet**
세 컴포넌트를 하나의 계약으로 묶은 Axismundi의 설계입니다. 셋은 트리거가 열고,
콘텐츠를 담고, 모달 여부·스크림·크기·위치를 가진다는 점에서 같습니다. 다른 것은
**보이는 방식**뿐이고, 이 페이지는 그 차이를 `presentation`이라는 하나의 축으로
다룹니다. Button과 Icon button이 같은 `<button>` 위의 두 anatomy인 것처럼, Dialog와
Sheet는 같은 `<dialog>` 호스트 위의 다른 presentation입니다.

> **구현 중인 계약입니다.** [`axismundi-dialogs`]({{ site.repository_url }}/tree/main/products/wordpress/plugins/axismundi-dialogs)에
> `dialog-surface` area, 시작 패턴 5개, `axismundi/dialog` 호스트 블록이 들어갔고, 아래 표본은 그 블록의
> 실제 stylesheet를 입습니다. 호스트는 `core/group`을 복제해 요소를 `<dialog>`로 고정한 블록이라,
> 배경·여백·모서리·그림자 같은 기본값을 블록 설정이나 Site Editor의 스타일에서 바꿀 수 있습니다.
> 트리거로 여는 동작과 페이지 끝 렌더는 아직입니다.
> 레거시 `axismundi/dialogs`·`axismundi/sheet`·`axismundi/dialog`(현재 `blocks/dialog-legacy`)는
> 등록을 내린 폐기 대상이고 구현 참조로만 남겨둔 것입니다. Post Quick View와 Object Media
> Dialog는 초기 구현 그대로라 구조를 바꿀 수 있습니다. 인터랙티브 데모는 나중에 추가합니다.

값은 전부 [`_data/surface.yml`]({{ site.repository_url }}/blob/main/products/styleguide/_data/surface.yml)에서
옵니다. 계약 CSS는 Dialog 블록의
[`style.css`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi-dialogs/blocks/dialog/style.css)
하나뿐이고, 이 사이트는 빌드할 때 그 파일을 그대로 복사해 씁니다(`sync_styleguide_assets.py`).
그 CSS와 데이터의 색 역할·elevation은 `tools/validators/validate_styleguide_surface.py`가 테마 토큰과 대조합니다.

## 한 계약, 네 가지 presentation

```
Surface 계약        공통 모델: 열림, 닫힘, focus, scrim, layout, content

글·템플릿           Dialog Button · Dialog Icon Button
                    Action: Open dialog surface → 파트를 이름으로 가리킴

Site Editor         템플릿 파트 (area: {{ site.data.surface.host.area.slug }})
  └─ {{ site.data.surface.host.block }}   HTML <dialog> 호스트
       presentation   dialog-basic | dialog-full-screen | sheet-bottom | sheet-side
       modality       modal | standard            (sheet만 선택)
       attachment     docked | detached           (side sheet만)
       edge           start | end                 (side sheet만)
       dismissal      closedby: any | closerequest | none
       role           dialog | alertdialog
       content        template-part (기본) | dynamic
     └─ core/group header · 본문 · footer

Global Styles       컨테이너 색, 모서리, 스크림 등 기본 토큰
```

| Presentation | 컴포넌트 | 모달 | Scrim | 컨테이너 | Elevation | Shape |
|---|---|---|---|---|---|---|
{% for p in site.data.surface.presentations -%}
| `{{ p.name }}` | {{ p.component }} | {% if p.modal %}항상{% else %}선택{% endif %} | {% if p.scrim == true %}있음{% elsif p.scrim == false %}없음{% else %}modal만{% endif %} | {% if p.spec.container.role %}`{{ p.spec.container.role }}`{% else %}modal `{{ p.spec.container.modal.role }}` · standard `{{ p.spec.container.standard.role }}`{% endif %} | {% if p.spec.elevation.level %}{{ p.spec.elevation.level }} ({{ p.spec.elevation.dp }}dp){% else %}modal {{ p.spec.elevation.modal.level }} · standard {{ p.spec.elevation.standard.level }}{% endif %} | {% case p.name %}{% when "sheet-bottom" %}위 {{ p.spec.shape.start_start }} · 아래 {{ p.spec.shape.end_end }}{% when "sheet-side" %}docked {{ p.spec.shape.docked }} · modal 페이지 쪽 {{ p.spec.shape.docked_modal.facing_page }} · detached {{ p.spec.shape.detached }}{% else %}{{ p.spec.shape }}{% endcase %} |
{% endfor %}

스크림은 모든 모달 행이 공유합니다. `{{ site.data.surface.scrim.role }}` 역할에
불투명도 {{ site.data.surface.scrim.opacity }}이고, 필요한 동작이 있는 표면이 아니면
스크림을 누르면 닫힙니다. Full-screen dialog와 standard sheet에는 스크림이 없습니다.

**List dialog와 Scrollable list dialog는 행이 아닙니다.** Figma에는 별도 컴포넌트로
있지만 토큰 표가 Basic dialog와 같고, M3 guidelines도 목록·날짜 선택·시간 선택을
basic dialog가 담는 레이아웃으로 설명합니다. 스크롤은 콘텐츠의 동작입니다.

## 표본

각 프레임은 창 하나를 뜻합니다. 좁은 프레임은 compact, 넓은 프레임은 expanded입니다.

<div class="sg-surface-specimens">
<figure class="sg-surface-specimen">
<div class="sg-surface-frame sg-surface-frame--compact" inert>
<div class="sg-surface-frame__page" aria-hidden="true"></div>
<div class="sg-surface-frame__scrim"></div>
<dialog open class="wp-block-axismundi-dialog" id="sg-surface-basic" data-presentation="dialog-basic" role="alertdialog" aria-labelledby="sg-surface-basic-headline">
<div class="wp-block-group" style="display:flex;flex-direction:column;gap:16px;padding:24px 24px 0"><h2 class="wp-block-heading" id="sg-surface-basic-headline">초안을 삭제할까요?</h2><p>삭제한 초안은 휴지통으로 가지 않고 바로 사라집니다.</p></div>
<footer class="wp-block-group" style="padding:24px"><div class="wp-block-buttons" style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px"><div class="wp-block-button is-style-text"><button type="button" class="wp-block-button__link wp-element-button">취소</button></div><div class="wp-block-button is-style-text"><button type="button" class="wp-block-button__link wp-element-button">삭제</button></div></div></footer>
</dialog>
</div>
<figcaption><code>dialog-basic</code> · <code>role="alertdialog"</code> · 확인 동작이 끝쪽 가장자리에 옵니다.</figcaption>
</figure>
<figure class="sg-surface-specimen">
<div class="sg-surface-frame sg-surface-frame--compact" inert>
<div class="sg-surface-frame__page" aria-hidden="true"></div>
<dialog open class="wp-block-axismundi-dialog" id="sg-surface-full" data-presentation="dialog-full-screen" aria-labelledby="sg-surface-full-headline">
<header class="wp-block-group" style="display:flex;align-items:center;gap:8px;min-height:56px;padding:0 24px 0 8px"><button type="button" class="wp-block-axismundi-icon-button is-style-standard"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span><span class="screen-reader-text">닫기</span></button><h2 class="wp-block-heading" id="sg-surface-full-headline" style="flex:1 1 auto">새 일정</h2><div class="wp-block-buttons"><div class="wp-block-button is-style-text"><button type="button" class="wp-block-button__link wp-element-button">저장</button></div></div></header>
<div class="wp-block-group" style="padding:24px"><p>제목, 날짜, 장소, 시간처럼 여러 단계를 거치는 작업을 담습니다.</p></div>
</dialog>
</div>
<figcaption><code>dialog-full-screen</code> · compact 전용 · 스크림 없음 · 앱 바의 탐색은 닫기 하나뿐입니다.</figcaption>
</figure>
<figure class="sg-surface-specimen">
<div class="sg-surface-frame sg-surface-frame--compact" inert>
<div class="sg-surface-frame__page" aria-hidden="true"></div>
<div class="sg-surface-frame__scrim"></div>
<dialog open class="wp-block-axismundi-dialog" id="sg-surface-bottom" data-presentation="sheet-bottom" data-modality="modal" aria-labelledby="sg-surface-bottom-headline">
<header class="wp-block-axismundi-dialog__header"><button type="button" class="wp-block-axismundi-dialog__drag-handle" aria-label="시트 높이 바꾸기"></button></header>
<div class="wp-block-group" style="padding:24px"><h2 class="wp-block-heading" id="sg-surface-bottom-headline">공유</h2><p>링크를 복사하거나 공개 범위를 바꿉니다. 첫 높이는 창의 절반을 넘지 않습니다.</p></div>
</dialog>
</div>
<figcaption><code>sheet-bottom</code> · modal · drag handle은 높이 단계가 있을 때만 버튼으로 렌더합니다.</figcaption>
</figure>
<figure class="sg-surface-specimen sg-surface-specimen--wide">
<div class="sg-surface-frame" inert>
<div class="sg-surface-frame__page" aria-hidden="true"></div>
<div class="sg-surface-frame__scrim"></div>
<dialog open class="wp-block-axismundi-dialog" id="sg-surface-side-modal" data-presentation="sheet-side" data-modality="modal" data-attachment="docked" data-edge="end" aria-labelledby="sg-surface-side-modal-headline">
<header class="wp-block-group" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 12px 12px 24px"><h2 class="wp-block-heading" id="sg-surface-side-modal-headline">필터</h2><button type="button" class="wp-block-axismundi-icon-button is-style-standard"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span><span class="screen-reader-text">닫기</span></button></header>
<div class="wp-block-group" style="padding:0 24px"><p>초안 포함, 작성자, 기간.</p></div>
<footer class="wp-block-group" style="padding:16px 24px 24px"><div class="wp-block-buttons" style="display:flex;flex-wrap:wrap;justify-content:flex-start;gap:8px"><div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button">적용</button></div><div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link wp-element-button">취소</button></div></div></footer>
</dialog>
</div>
<figcaption><code>sheet-side</code> · modal · docked · <code>edge="end"</code> · 페이지를 향한 모서리만 둥글게 처리합니다.</figcaption>
</figure>
<figure class="sg-surface-specimen sg-surface-specimen--wide">
<div class="sg-surface-frame" inert>
<div class="sg-surface-frame__page" aria-hidden="true"></div>
<dialog open class="wp-block-axismundi-dialog" id="sg-surface-side-standard" data-presentation="sheet-side" data-modality="standard" data-attachment="docked" data-edge="end" aria-labelledby="sg-surface-side-standard-headline">
<header class="wp-block-group" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 12px 12px 24px"><h2 class="wp-block-heading" id="sg-surface-side-standard-headline">사진 정보</h2><button type="button" class="wp-block-axismundi-icon-button is-style-standard"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span><span class="screen-reader-text">닫기</span></button></header>
<div class="wp-block-group" style="padding:0 24px 24px"><p>촬영일, 카메라, 위치.</p></div>
</dialog>
</div>
<figcaption><code>sheet-side</code> · standard · 스크림 없음 · 페이지 본문이 시트 폭만큼 줄어듭니다.</figcaption>
</figure>
</div>

표본의 스크림은 요소로 그렸습니다. 정적인 `<dialog open>`은 top layer에 올라가지
않아서 `::backdrop`이 없기 때문입니다. 실제 계약에서 스크림은 `::backdrop`입니다.

## Breakpoint에 따른 전환

**presentation은 인스턴스마다 하나가 아니라 breakpoint마다 하나입니다.** M3는
창이 커지면 표면이 다른 행으로 바뀌도록 합니다. 표면은 breakpoint별 presentation을
저장하고, 값이 없으면 한 단계 작은 breakpoint의 값을 따릅니다.

| 원래 | 적용 breakpoint | 바뀌는 것 | 시작 | 근거 |
|---|---|---|---|---|
{% for r in site.data.surface.adaptive -%}
{% if r.becomes -%}
| `{{ r.presentation }}`{% if r.modality %} ({{ r.modality }}){% endif %} | {{ r.breakpoints | join: ", " }} | `{{ r.becomes }}` | {% if r.from %}{{ r.from }}{% else %}—{% endif %} | {{ r.source }} |
{% endif -%}
{% endfor %}

{% assign basic_position = site.data.surface.adaptive | where: "presentation", "dialog-basic" | first -%}
Basic dialog는 기본으로 가운데에 놓이고, {{ basic_position.position.custom_from }}부터
위치를 옮길 수 있으며, {{ basic_position.position.edge_margin_from }}에서는 창 가장자리에서
{{ basic_position.position.edge_margin }}dp를 떼어야 합니다.

마크업에서는 breakpoint마다 데이터 속성 하나입니다. 기본값은 가장 작은 창의 값이고,
큰 breakpoint는 바뀔 때만 적습니다.

{% raw %}
```html
<dialog class="wp-block-axismundi-dialog"
        data-presentation="dialog-full-screen"
        data-presentation-medium="dialog-basic">
```
{% endraw %}

**theme.json으로는 이 전환을 저작할 수 없습니다.** WordPress 7.1의
`settings.viewport`는 `@mobile`(기본 480px)과 `@tablet`(기본 782px) 두 상한 구간뿐이고,
가장 큰 구간 위(`min-width`)는 표현하지 못합니다. M3 경계는 600dp와 840dp라서 값도
맞지 않습니다. 그래서 전환은 블록의 CSS가 리터럴 미디어 쿼리로 맡고, 모달 여부가
바뀌는 전환(standard → modal)은 CSS만으로 되지 않으므로 런타임이 창 크기를 보고
`show()`와 `showModal()`을 다시 고릅니다.

## 마크업

세 부분으로 나뉩니다. 트리거는 글이나 템플릿의 버튼, 표면은 Site Editor의 템플릿
파트, 렌더는 페이지 끝에 한 번입니다.

### 트리거

버튼의 **Action** 축에 `Open dialog surface`가 추가됩니다. navigation overlay 때와
달리 트리거가 표면을 렌더하지 않고, 파트 slug에서 만든 안정적인 id로 표면을
가리킵니다. 트리거가 여럿이어도 표면은 하나입니다.

{% raw %}
```html
<!-- wp:axismundi/dialog-button {"action":"dialog","actionTarget":"discard-draft","text":"삭제"} /-->

<div class="wp-block-button wp-block-axismundi-dialog-button">
  <button type="button" class="wp-block-button__link wp-element-button"
          commandfor="dialog-surface-discard-draft" command="show-modal"
          aria-haspopup="dialog">삭제</button>
</div>
```
{% endraw %}

### 표면 (템플릿 파트)

`dialog-surface` area의 파트입니다. 최상위 블록이 `<dialog>` 호스트이고, 머리·본문·
꼬리는 새 클래스가 아니라 `core/group`의 `tagName`입니다. 여백은 호스트가 아니라 각
그룹이 블록 속성으로 가집니다. Material 3 Design Kit이 컨테이너 대신 구역마다 여백을
두는 구조를 따른 것이고, 그래서 List dialog의 목록이 가장자리까지 닿을 수 있습니다.
슬롯은 Header, Content, Actions 세 가지이고, presentation마다 필요한 슬롯만 씁니다. Basic은
Content와 Actions, List dialog는 Header·Content·Actions, bottom sheet는 Content에 핸들을
켜면 호스트가 Header를 더하고, side sheet는 Header·Content·Actions, full-screen은
Header와 Content입니다. Content는 모두 `Inner blocks use content width`를 켠 채로
시작합니다. 좁은 표면에서는 차이가 없고, 표면이 넓어지면 읽기 좋은 폭으로 제한됩니다. 파트는 Site Editor에서
편집하고, 테마는 같은 이름의 파트 파일로 덮어쓸 수 있습니다.

{% raw %}
```html
<!-- wp:axismundi/dialog {"presentation":"dialog-basic","role":"alertdialog","dismissal":"closerequest"} -->

  <!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"0","left":"24px"},"blockGap":"16px"}},"layout":{"type":"constrained"}} -->
  <div class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:0;padding-left:24px">
    <!-- wp:heading {"anchor":"discard-draft-headline"} -->
    <h2 class="wp-block-heading" id="discard-draft-headline">초안을 삭제할까요?</h2>
    <!-- /wp:heading -->
    <!-- wp:paragraph --><p>…</p><!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"tagName":"footer","metadata":{"name":"Actions"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}}} -->
  <footer class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px">
    <!-- wp:axismundi/dialog-button-group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","justifyContent":"right"}} -->
    <div class="wp-block-axismundi-dialog-button-group wp-block-buttons">
      <!-- wp:axismundi/dialog-button {"action":"surface-close","text":"취소","className":"is-style-text"} /-->
      <!-- wp:axismundi/dialog-button {"action":"surface-close","text":"삭제","className":"is-style-text"} /-->
    </div>
    <!-- /wp:axismundi/dialog-button-group -->
  </footer>
  <!-- /wp:group -->

<!-- /wp:axismundi/dialog -->
```
{% endraw %}

### 렌더 (페이지 끝에 한 번)

페이지의 무언가가 이 표면을 가리키면, 플러그인이 페이지 끝에서 파트를 한 번
렌더합니다. 가리키는 것이 없으면 렌더하지 않습니다.

{% raw %}
```html
<dialog id="dialog-surface-discard-draft" class="wp-block-axismundi-dialog"
        data-presentation="dialog-basic"
        role="alertdialog"
        aria-labelledby="discard-draft-headline"
        closedby="closerequest">
  <div class="wp-block-group">…</div>
  <footer class="wp-block-group">…</footer>
</dialog>
```
{% endraw %}

## 설계 결정

### 1. presentation은 행이고, 변환은 행을 고르는 일입니다

Dialog와 Sheet를 별도 블록으로 두면 크기·스크림·닫힘·포커스 설정이 두 번 구현되고,
둘 사이의 변환은 속성을 옮겨 적는 일이 됩니다. 한 표면의 행으로 두면 변환은
`presentation` 값 하나를 바꾸는 일이고, 행마다 받아들이는 설정만 걸러내면 됩니다.
버튼의 블록 변환이 공유 속성 목록으로 동작하는 것과 같은 방식입니다.

### 2. 호스트는 모든 행에서 native `<dialog>`입니다

top layer, `::backdrop`, Escape, 모달일 때의 inert 배경, 포커스 복원을 브라우저가
줍니다. 직접 구현하면 이 모든 것을 JS로 다시 만들어야 합니다. 예외는 **navigation
overlay** 하나이고, 그것은 코어 Navigation과 똑같이 동작해야 해서 코어처럼
`div role="dialog"`로 둡니다.

### 3. 표면은 글 안의 블록이 아니라 Site Editor의 템플릿 파트입니다

코어는 Navigation 블록과 navigation overlay 파트를 나눕니다. 헤더에 놓인 블록이
무엇을 열지 고르고, 열리는 레이어 자체는 Site Editor에서 관리합니다. dialog도
같습니다. 글 본문에 쓰기보다 헤더나 푸터처럼 사이트 전체에 걸린 시스템 요소라서,
표면을 글에 두면 모양과 동작의 결정이 글마다 흩어집니다.

그래서 글과 템플릿에는 **트리거만** 놓습니다. 표면은 `dialog-surface` area의 파트이고,
그 파트의 최상위 블록이 `<dialog>` 호스트입니다. 트리거는 파트 slug에서 만든 id
(`dialog-surface-{slug}`)를 가리키고, `wp_unique_id()`처럼 렌더 순서에 따라 바뀌는
값은 쓰지 않습니다. 그래야 트리거 여럿이 표면 하나를 안정적으로 공유합니다.

트리거가 표면을 매번 함께 렌더하는 지금의 navigation overlay 방식은 새 계약에서
쓰지 않습니다. navigation overlay는 코어와 똑같이 동작해야 하는 예외로 남습니다.

### 4. 닫힘 정책은 `closedby` 하나로 표현합니다

M3의 "필요한 동작이 없으면 스크림을 눌러 닫힌다"는 규칙이 HTML의 `closedby`와
그대로 겹칩니다.

| M3 | `closedby` |
|---|---|
| 스크림과 Escape로 닫힘 | `any` |
| 필요한 동작이 있음 — Escape만 | `closerequest` |
| 버튼으로만 닫힘 | `none` |

### 5. area는 `dialog-surface`이고, 기본 내용은 플러그인 패턴입니다

**이름.** `dialog`는 sheet도 이 area에 들어간다는 사실을 가리고, `surface` 단독은 M3
색 역할(`surface`, `surface-container-*`)과 겹칩니다. `dialog-surface`는 "dialog 호스트가
렌더하는 시스템 표면용 파트"라는 용도가 그대로 읽힙니다.

**누가 무엇을 가지는가.** 코어의 navigation overlay와 같은 세 층입니다.

| 층 | navigation overlay | dialog surface |
|---|---|---|
| 코어 / 플러그인 | area 상수와 기본 패턴 5개 | area 등록과 시작 패턴 |
| 테마 | `parts/`의 파트 파일 | 원하면 파트 파일로 덮어씀 |
| 사용자 | Site Editor에서 파트 편집 | Site Editor에서 파트 편집 |

등록된 패턴은 코드라서 수정할 수 없지만, 수정하는 대상은 패턴이 아니라 패턴에서
만들어진 파트입니다. 새 파트를 만들 때 Design 목록에 나오는 것이 이 패턴입니다.

**WordPress 7.1 코드로 확인한 것** (Site Editor 화면은 열어보지 않았습니다):

- Design 목록은 `blockTypes`에 `core/template-part/` + area 이름이 있고, `source`가
  제외 목록 `["core", "pattern-directory/core", "pattern-directory/featured"]`에 없는
  패턴을 보여줍니다. 플러그인 패턴의 source는 `plugin`이라 통과합니다.
- 코어는 `navigation-overlay` 파트만 이름으로 일반 inserter에서 숨깁니다
  (`blocks/template-part.php`). 플러그인 area는 이 예외를 받지 못하므로
  `get_block_type_variations` 필터로 직접 숨깁니다.
- 플러그인 템플릿 등록(`register_block_template()`, 6.7)은 `wp_template`만 받습니다.
  파트는 이 방법으로 제공할 수 없어서 패턴을 씁니다.

표면이 아는 파트의 구조는 `header`, 본문, `footer` 세 행뿐입니다. 크기·방향·스크림·
닫힘은 파트의 HTML이 아니라 호스트 블록의 설정입니다.

### 6. 기본값은 토큰 표, 나머지는 사용자가 바꿉니다

기본값은 M3 토큰 표를 그대로 씁니다. M3 문서끼리 어긋나는 곳과 XR Dialog가
`surface-container-highest`까지 쓰는 것처럼 문서가 범위를 여는 곳은 고정하지 않고
사용자가 바꿀 수 있게 둡니다. 컨테이너 색은 블록의 색 지원으로, 폭은 공개된 범위
안에서 고릅니다(side sheet 256–400dp).

### 7. role은 인스턴스가 고릅니다

M3 접근성 페이지는 웹의 basic dialog를 모두 `alertdialog`로 봅니다. 그러나 목록이나
날짜 선택을 담은 dialog는 긴급한 알림이 아니고, M3의 웹 구현인 material-web도
`type="alert"`를 선택 사항으로 둡니다. 그래서 role은 행의 속성이 아니라 인스턴스
설정입니다.

### 8. 닫기 버튼은 Icon button의 Action이고, 파트에서 지울 수 없게 잠급니다

Side sheet에는 닫기 수단이 항상 있어야 합니다. 표면이 닫기 버튼을 직접 렌더하면
작성자가 위치를 정할 수 없고, 파트에만 맡기면 빠질 수 있습니다. 그래서 navigation
overlay close를 우리 버튼의 Action으로 옮긴 것처럼 `surface-close` Action을 두고,
기본 파트에서는 그 버튼을 `"lock":{"remove":true}`로 잠급니다.

{% raw %}
```html
<!-- wp:axismundi/dialog-icon-button {"action":"surface-close","icon":"close","text":"닫기","lock":{"remove":true},"className":"is-style-standard"} /-->
```
{% endraw %}

### 9. Drag handle은 높이 단계가 있을 때만 렌더합니다

M3의 drag handle은 장식이 아니라 버튼입니다. 포커스를 받고, 라벨이 있고,
Space/Enter로 높이를 바꿉니다. 바꿀 높이가 없는데 손잡이만 보이면 사용자를 속이는
모양이 됩니다.

### 10. full-screen dialog 위에는 dialog가 올라올 수 있습니다

M3는 저장하지 않은 full-screen dialog를 닫을 때 그 위에 basic dialog를 띄워
확인합니다. 지금 구현은 전부 이 패턴을 막습니다. 레거시 블록도, Post Quick View와
Object Media Dialog도 열기 전에 다른 dialog를 모두 닫습니다. 새 호스트는 겹침을
허용하고, 닫을 때는 위에서부터 닫습니다.

### 11. 폼이 필요합니다

material-web은 `<form method="dialog">`로 dialog를 닫고, 닫은 버튼의 `value`를
`returnValue`로 돌려줍니다. 확인과 취소를 표면 밖에서 읽는 가장 표준적인 방법이지만,
WordPress에는 아직 안정된 폼 블록이 없습니다. 폼 블록이 필요할 가능성이 높고, 아직
정하지 않았습니다.

## 동적 콘텐츠

Post Quick View와 Object Media Dialog는 **presentation이 아니라 표면을 쓰는 사례**입니다.
quick view도 결국 basic dialog나 full-screen dialog로 보입니다. 다른 것은 콘텐츠를
채우는 방식입니다.

| 호스트와 lifecycle | presentation | 콘텐츠 |
|---|---|---|
| 공통 | dialog-basic · dialog-full-screen · sheet… | `template-part` (기본) 또는 `dynamic` |

| provider | 지금 구현 (초기 구현이라 구조 변경 가능) |
|---|---|
{% for d in site.data.surface.content_providers.dynamic -%}
| `{{ d.name }}` | {% case d.name %}{% when "post-quick-view" %}고정 id `ax-post-quick-view` 한 개. 트리거가 `aria-controls`로 가리키고, 열 때 글 fragment를 받아오며 댓글은 `wp/v2/comments`로 보냅니다.{% when "object-media" %}고정 id `ax-object-media-dialog` 한 개, basic/fullscreen. 트리거는 다른 플러그인의 Object Attachments 블록이고, 그 블록의 이미지를 복제해 캐러셀과 옆 패널을 만듭니다.{% endcase %} |
{% endfor %}

스키마에는 `contentProvider: template-part | dynamic`만 예약하고, 데모는 템플릿 파트
기반 네 가지 presentation으로 제한합니다. 템플릿 파트와 fetch를 지금 같은 구현으로
묶으면 서버 렌더와 클라이언트 fetch의 lifecycle이 섞이기 때문입니다.

**제안: provider는 호스트 블록의 속성, provider가 채울 자리는 잠긴 slot 블록.**
파트는 Site Editor에서 편집되므로, provider 선언이나 "미디어가 들어갈 자리"를 일반
마크업으로 두면 사용자가 지우는 순간 hub가 깨집니다. 닫기 버튼처럼
`lock: {"remove": true}`로 잠급니다.

두 동적 표면은 `dialog-surface` area의 시작 패턴으로 제공할 가능성이 높습니다.

## 접근성

| 항목 | Dialog | Bottom sheet | Side sheet |
|---|---|---|---|
| role | `dialog` 또는 `alertdialog` (결정 7) | `dialog` | `dialog` |
| 이름 | 헤드라인 (`aria-labelledby`) | 헤드라인. drag handle에도 별도 라벨 | 헤드라인 |
| 첫 포커스 | 첫 인터랙티브 요소, `autofocus`로 지정 가능 | drag handle이 탭 순서에 있음 | 헤드라인, 닫기, 동작 순 |
| 키보드 | Tab·Shift+Tab 순환, Space·Enter 실행, Escape 닫기 | Tab으로 handle, Space·Enter로 높이 단계 | Tab으로 아이콘 버튼, Space·Enter 실행 |
| 닫기 | `closedby`, 동작 버튼 | 항목 선택, 스크림, 아래로 밀기, 닫기 버튼 | 닫기 버튼 **항상** |

드래그로 할 수 있는 모든 동작에는 한 번 누르는 대안이 있어야 합니다. 텍스트를
200%로 키웠을 때 헤드라인이 잘리지 않도록 짧게 씁니다(M3는 Android 기준으로 적었고,
웹에서는 WCAG 1.4.4에 해당한다고 이 프로젝트가 해석합니다).

## 측정값

{% for p in site.data.surface.presentations -%}
### {{ p.title }}

| 항목 | 값 |
|---|---|
{% for m in p.measurements -%}
| `{{ m[0] }}` | `{{ m[1] | jsonify }}` |
{% endfor %}

{% endfor %}

## M3 문서끼리 다른 곳

M3의 페이지끼리 어긋나는 곳이 {{ site.data.surface.conflicts.size }}곳 있습니다.
어느 한쪽을 조용히 고르지 않고 양쪽을 데이터에 적었습니다.

- **Full-screen dialog 컨테이너 색** — 토큰 표는 `surface`, 같은 페이지의 색 역할
  목록은 `surface-container-high`. 값이 있는 토큰 표를 기본값으로 씁니다.
- **Full-screen dialog 폭** — 측정표는 최대 560dp, guidelines는 화면 전체이고
  compact 전용. compact는 599dp까지라 560dp 제한이면 빈틈이 생깁니다. 미해결.
- **Basic dialog의 웹 role** — 결정 7.
- **Dialog 겹침** — 결정 10.
- **Side sheet divider 색** — 토큰 표는 `outline`, standard 색 역할 목록은
  `outline-variant`. 토큰 표를 기본값으로 쓰고 바꿀 수 있게 둡니다.
- **Side sheet 닫기 버튼** — guidelines는 선택, 접근성 페이지는 필수. 접근성을 따릅니다.
- **Bottom sheet 스크롤 방향** — "가로로 스크롤" 문장 바로 아래 문장이 세로
  스크롤을 설명합니다. 세로로 읽습니다.
- **Bottom sheet 라벨** — "drag handle에만 라벨"이지만 웹의 모달 시트는 dialog이므로
  표면에도 이름이 필요합니다.

## 레거시와의 차이

레거시 블록은 폐기 대상이지만, 이미 검증된 동작을 잃지 않도록 차이를 기록합니다.
레거시 `axismundi/dialog` 블록의 감싸는 요소에는 이미 코어 기본 클래스
`wp-block-axismundi-dialog`가 붙습니다. 새 호스트와 클래스가 같으므로, 새 블록이 이
이름을 가져가기 전에 레거시 등록을 내려야 합니다.


{% for p in site.data.surface.presentations -%}
- **{{ p.title }}** — {% if p.legacy.bound %}맞음{% else %}아직 다름{% endif %}. 
{%- case p.name -%}
{%- when "dialog-basic" %} 컨테이너 색이 한 단계 낮고(`surface-container`), elevation이 두 단계 낮습니다(level1). 모서리와 폭은 맞습니다.
{%- when "dialog-full-screen" %} 모서리와 elevation은 맞고, 컨테이너 색과 스크롤 시 변화는 아직 재지 않았습니다.
{%- when "sheet-bottom" %} 위 여백은 맞습니다. drag handle이 `aria-hidden` 장식이고, 첫 높이를 창의 절반으로 제한하지 않습니다.
{%- when "sheet-side" %} 폭 preset(256/320/400)은 공개 범위 안이고 기본값만 다릅니다(320). 페이지 밀기는 맞습니다. 닫기 버튼을 보장하지 않습니다.
{%- endcase %}
{% endfor %}

## 열린 질문

- 우리 버튼이 아닌 트리거(Object Attachments)의 렌더 요청 — provider가 렌더될 때
  표면이 필요하다고 등록할지, 사이트가 footer 파트에 표면을 한 번 놓을지.
- 테마가 플러그인 area의 파트 파일을 실을 때 테마 검사가 문제 삼는지 (0.2.2에서
  자체 area를 걷어낸 이력).
- 폼 블록 (결정 11).
- `command`/`commandfor`와 `closedby`의 Chrome 외 브라우저 지원.
- Full-screen dialog의 560dp 제한.
- compact에서 basic dialog의 좌우 여백 — M3가 발행하지 않았습니다.

