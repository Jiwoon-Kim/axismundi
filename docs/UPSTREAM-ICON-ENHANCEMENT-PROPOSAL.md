# Draft — Gutenberg: icons, variable fonts and stateful controls

> 상태: **게시됨** 2026-09-14, Discussions › Ideas
> [#82830](https://github.com/WordPress/gutenberg/discussions/82830). 스크린샷 없이 게시(API로 첨부 불가) —
> Playground 링크가 증거 역할. 사실 확인 기준 trunk `edd6dcba5f`. 갓 올린 이 스레드에 논지를 덧붙이지 않는다.
>
> 게시 위치(결정 2026-09-14): Discussions › **Ideas** 우산 스레드 하나를 먼저 올린다. 이미 열린 스레드가
> 각 하위 질문을 소유하고 있으므로(아래 "이미 있는 스레드"), 이 글은 그것들을 **다시 쓰지 않고 연결**한다.
> issue 분리는 게시 뒤에 한다. 후보: 블록 typography의 가변 폰트 축 지원, `core/icon`의 칠하는 요소·wrapper `span`
> (둘 다 Feature request 템플릿, 문제 하나·해법 하나). 아이콘 폰트 스키마, `core/button` 동적 전환, buttons/group 구분은
> 합의 전까지 Discussion에 남긴다.
>
> 이 글의 사실은 Gutenberg `upstream/trunk` **edd6dcba5f**(2026-09-14)와 axismundi-dialogs 0.3.0에서 읽었다.
> 인용 번호는 전부 `gh api`로 확인했다(2026-09-14).

## 게시 전 확인 목록

- [ ] Dialogs Live Preview를 public으로 전환했다(사용자 결정: 테마 0.1.18 공개를 기다리지 않음)
- [ ] 본문 Playground 링크(wp.org 블루프린트, `rev=3694674`)를 열어 `/dialogs-vqa/`가 뜨는지 확인했다
- [ ] 테마가 0.1.17이면 Outlined가 Filled처럼 보인다(0.1.17에 `is-style-outlined` 일반 규칙이 없고 변형이 dialog-button에 연결 안 됨). 0.1.18 공개 뒤 자동 해결 — 스크린샷은 Outlined가 안 나오게 찍거나 0.1.18 뒤에 찍는다
- [ ] 스크린샷 4장: selected icon(font FILL / registry swap), hover preview, rotate-only, core/icon 대조 줄
- [ ] trunk를 다시 fetch해 파일·줄 번호가 그대로인지 확인했다
- [ ] 의도 추정 문장이 없다("nobody considered", "was meant to" 금지 → "its contract and tests do not cover")
- [ ] 갓 게시한 이 스레드에 논지를 덧붙이지 않는다

## 이미 있는 스레드 (이 글이 소유하지 않는 것)

| 번호 | 종류 | 소유하는 질문 |
|---|---|---|
| #82700 | issue (내 글, open) | 상태별 대체 아이콘 reference를 블록이 **저장**하는 계약 |
| #82229 | Ideas (내 글, 댓글 1) | 레지스트리가 SVG 없이 provider로 해석될 수 있는가. 거기서 `fontFamilies.kind = icon` 슬롯은 **일부러 넣지 않음** |
| #82228 | Developer Experience (내 글) | 아이콘만 있는 컨트롤의 접근 가능한 이름 |
| #82501 | Show and tell (내 글) | Theme Switcher 사례 |
| #16513 | issue (truchot, open) | `core/button`에 아이콘 |
| #81225 | issue (내 글, open) | 코어 내부 UI SVG를 레지스트리로 해석 |
| #80938 | issue (retrofox, open) | 위젯 파이프라인의 선언적 `icon` |
| #82062 | PR (Vrishabhsk, open, 미머지) | 코어 블록 UI 아이콘을 레지스트리 식별자로 해석, 테마는 SVG 재등록으로 교체. #81225에 대한 응답 |

**#82062 인용 주의:** "렌더러 재작성이 필요 없어진다" 같은 확대 해석 금지(이전에 지적받음). 쓰는 것은 두 사실뿐이다:
trunk에서 `wp_get_icon()`을 부르는 블록은 `core/icon`뿐이고, #82062는 열려 있으며 SVG 재등록 경로를 제안한다.

#82229에서 스키마를 넣지 않았던 판단을 이 글이 뒤집는다. 그래서 스키마 요구는 **VQA에서 막힌 지점을 근거로** 제시하고,
"그 스레드에서 보류한 이유(provider 질문을 흐리지 않기 위해)는 그대로 유효하고, 여기서는 별도 층위로 묻는다"고 밝힌다.

---

## Title

Icons are a language shared by Core blocks, themes and plugins — notes from building one outside Core

## Body

Gutenberg has no shared contract that connects icon sources, variable-font capabilities, and control state. Individual blocks can approximate it, but cannot interoperate or expose it consistently through themes, the Font Library, and the editor.

I reached that conclusion by building the pieces in order. First an icon block that takes its icon from either the Icon Registry or the theme's icon font. Then an icon button that renders that icon. Then copies of `core/button` and `core/buttons`, so a labelled button and a group of toggles could use the same icon. Each step ran into a question the previous one could not answer on its own: which fonts are icon fonts, which element an icon may be painted in, where a selected icon is stored, what state switches it. By the end it was clear this is not an Icon Registry problem alone. It is the icon language that Core blocks, themes and plugins all speak, with no shared grammar.

That work is now inspectable rather than described:

- Plugin: <https://wordpress.org/plugins/axismundi-dialogs/>
- [Live preview in Playground](https://playground.wordpress.net/?plugin=axismundi-dialogs&blueprint-url=https%3A%2F%2Fwordpress.org%2Fplugins%2Fwp-json%2Fplugins%2Fv1%2Fplugin%2Faxismundi-dialogs%2Fblueprint.json%3Frev%3D3694674%26lang%3Den_US) — installs the plugin and its companion theme from WordPress.org, creates ten template parts and one page, and lands on it.

This thread is an umbrella. Several of its questions already have their own threads, linked where they come up, and I would rather point at those than restate them here.

### What exists today

Read from `trunk` (edd6dcba5f):

- **`core/icon` is dynamic and paints into a `div`.** It has no `save`; `index.php` returns `sprintf( '<div %s>%s</div>', $wrapper_attributes, $svg )`. Its attributes are `icon`, `flipHorizontal`, `flipVertical` and `rotation`.
- **`core/button` is already a hybrid, and polymorphic.** It has `save.jsx` *and* `render_callback => render_block_core_button`, which walks the saved markup with `WP_HTML_Tag_Processor`. Its `tagName` attribute is `a` (default) or `button`.
- **An icon font can be declared, but not as an icon font.** `FONT_FAMILY_SCHEMA` accepts `fontFamily`, `name`, `slug` and `fontFace`; each face accepts `fontVariationSettings`. Nothing marks a family as icons.
- **Blocks cannot set variation axes.** `lib/block-supports/typography.php` handles font family, size, style, weight, letter spacing, line height, text align, columns, decoration, transform, indent and shadow. A theme can declare `"FILL" 0, "wght" 400` on a face; no block, including Paragraph, can move an axis without custom CSS.
- **`core/buttons` is a layout container.** It declares no attributes and a fixed flex layout. Nothing in it relates its buttons to each other — no selection, no shared state.
- **One registered icon is one SVG.** `WP_Icons_Registry::register()` allows `label`, `content` and `file_path` (detail in #82700).
- **Only `core/icon` asks the registry.** In `packages/block-library`, `icon/index.php` is the one block that calls `wp_get_icon()`; other blocks draw their UI icons inline. #82062 (open) proposes resolving those through the registry under stable identifiers, so a theme can replace one by re-registering an SVG. That helps a theme whose icon language is SVG; a theme whose icon language is a font would still have to convert each glyph to markup — the language spans more than the registry.

### What the plugin does differently, and what it cost

The plugin has a Dialog Icon, a Dialog Button, a Dialog Icon Button and a Dialog Button Group. They share one icon renderer for both sources, so the findings below are about the contract, not about one block.

1. **One paint element for both sources.** The icon is always `span.ax-icon`: a glyph for a font, `span > svg` for the registry. Size (`--md-icon-size`), colour, flip/rotate and the accessible label all land on the span, so no rule branches on the source.

   The `span` is also forced by the button. A `<button>` accepts phrasing content only, so `button > div > svg` is invalid, while `a` is transparent and `a > div` can be valid. A block that renders as either element — `core/button`'s `tagName` is `a` or `button` — needs inner markup valid in both, and that common subset is phrasing content. If an icon wrapper may be a `div`, the icon has to know its parent's `tagName`, or grow an inline rendering mode. Fixing it to `span` removes the question: the same icon markup is valid in a link, a button, a heading or a paragraph.
2. **Selection needs the button, not the icon.** A variable font fills the same glyph (`FILL 0 → 1`, interpolated because the axis is a registered custom property); a static font or the registry needs a second reference, rendered beside the first and chosen by `aria-pressed`. The icon primitive knows nothing of state — the storage half of this is #82700.
3. **State can be previewed and animated without new runtime.** Hover and focus can show the selected state before it is chosen (a Like filling, a Repost turning), and the swap can fade, rotate or scale. A rotate-only transition turns a single icon — a plus to a close at 45°, a chevron at 180° — which is the case `core/accordion-heading` handles today with a literal `+` and a CSS rotation.
4. **A button that opens something is stateful too.** A trigger whose surface is open carries `aria-expanded`, and the same selected icon applies.

In the VQA page: the "Icons" row puts `core/icon` beside both sources; "Icon states" has Like (fill), Repost (rotate-only), Expand (registry swap, fade) with hover preview; "Selection" has single, required and multiple groups.

### Proposed work, separable

Each of these can move without the others.

**1. Theme: declare an icon font as an icon font.**
A family-level flag (for example `icon: true`, default false) that the Font Library and editor can read, so an icon picker can offer the theme's icon font without a plugin hard-coding its class and ligature names. Deliberately *not* a glyph map: Material Symbols works by ligature and by codepoint, and enumerating glyphs is the cost #82229 asks to avoid. #82229 left a schema slot out on purpose, to keep the provider question narrow; this is the layer that thread declined to design, raised here on its own.

**2. `core/icon`: one source contract, `span` paint element.**
Let the block reference either a registry icon or a theme icon-font glyph, stored explicitly (`iconSource`), with the same flip/rotate/label behaviour on both. Consider `span` as the painted element. The block wrapper matters as much as the painted element: nested inside a `<button>` as an inner block, today's `div` wrapper would be invalid however the icon itself is painted, so a control should either call the icon renderer directly and place only its `span`, or the block's wrapper should be a `span` too.

**3. Variable-font axes as a typography capability.**
Not an icon-only inspector. If a face declares axes, blocks that support typography could expose them — Paragraph and Heading as much as Icon. Icons are simply where the gap is most visible, because `FILL` is how an icon font expresses selection.

**4. Button state primitives.**
Selected icon (#82700 for storage), fill or weight fallback ("If a filled version doesn't exist, increase the weight instead"), hover/focus preview, icon transitions, and rendering from `aria-pressed` / `aria-expanded` — including state held by a plugin's Interactivity store, as a Like or Repost button's is. Buttons are the natural first consumer because a toggle is where M3 specifies all of this, which is also why an icon proposal ends up improving the button's state model.

**5. `core/buttons` and a button group are probably different things.**
A hypothesis, not a request to change `core/buttons`: selection rules, required selection and shared state make a group an interaction container, while `core/buttons` is layout. Whether that is a new block, a support, or nothing, is a question for after the cases above are visible.

### A personal position on `core/button`

Personally, I would propose treating the current `core/button` as a legacy (deprecated) version and moving the block to server rendering. The goal is one renderer and one state contract shared with an icon button: icon resolution, selected icons and `aria-pressed` written at render time rather than frozen in saved HTML.

The layering is what leads there — a logical layering, not nested blocks. The icon is a primitive; an icon button is that primitive rendered in a button shell, with its name out of sight; a button is the same shell with an icon and a visible label. None of them contains another as an inner block: they share one renderer. `core/icon` already resolves its icon on the server. For the icon button and the button to place that same resolved `span` — and switch it by state — they need the same server step, not one block rendering at request time while the other replays saved HTML.

The plugin is a working reference for testing these API decisions, not code for Core to adopt. Dialog Icon, Dialog Icon Button and Dialog Button call one icon renderer, in the editor and on the page, and the VQA page shows that:

- the same icon reference is used consistently by a standalone icon, an icon button and a labelled button;
- selected icons, fill, rotation and hover preview derive from the button's state contract, not from the icon;
- the markup stays phrasing content, valid whether the control renders as `a` or `button`;
- so the icon button and the labelled button do not split into two markup contracts.

Whether this can be built is answered by the page. The question for Gutenberg is how to generalise the contract into Core APIs.

I considered the two ways that keep saved markup:

- **Save the icon's name, fill it on the server.** `WP_HTML_Tag_Processor` changes attributes, not an element's contents, so this becomes string surgery beside the existing pass, and a button and an icon button keep separate render paths.
- **Save the resolved SVG.** The icon is frozen at save time — the same reason `core/icon` is dynamic — and state markup is still tied to saved HTML.

Core's own blocks point the same way. On `trunk` (edd6dcba5f), no block in `packages/block-library` stores inline SVG in saved markup: no `save` or `deprecated` file contains `<svg`, `<SVG`, `<Path` or `@wordpress/primitives`. Every inline SVG is written at render time:

| Block | Saved as | SVG from |
|---|---|---|
| `search`, `social-link`, `page-list`, `navigation-overlay-close`, `icon` | dynamic, no `save` | `index.php` (`icon` via `wp_get_icon()`) |
| `navigation`, `navigation-link`, `navigation-submenu` | inner blocks only | `navigation/index.php`, `navigation-link/shared/render-submenu-icon.php` |
| `image` | static `figure > img`, plus a `render_callback` | the lightbox button, added in `image/index.php` |

The two exceptions to "dynamic" are instructive. `core/accordion-heading` is fully static and has a toggle icon, and it saves a literal `+` turned by CSS rather than an SVG. `core/image` keeps static markup and adds an SVG on the server, and it does so by string replacement: `preg_match( '/<img[^>]+>/', … )`, then `preg_replace()` with the image followed by a `<button>` holding the SVG — because the tag processor cannot insert content. A button's icon has to go *inside* the control rather than beside it, so the same approach would be harder for `core/button`, not easier.

I recognise the cost: the number of saved buttons, consumers that read `post_content` directly, and render overhead. `core/button` already runs a `render_callback` over its saved markup, so this extends an existing server path rather than introducing one. The plugin's buttons are fully dynamic, but that is an experiment's choice; I am offering it as a position to argue with, not as the conclusion.

### Non-goals

This proposal intentionally does not introduce alternate button labels for state changes. Label changes alter the accessible-name contract and need a separate stateful-command model, including product-owned localization for verbs such as Like/Unlike or Follow/Unfollow. "Stateful button labels" is a possible follow-up, and closer to the button's accessibility and string contract than to the icon API.

Also out of scope: changing the Icon Registry's registration shape (#82700 explains why it can stay), and uploadable site icons.

### Related

- #82700 — storing a state-specific icon reference
- #82229 — resolving registry icons through a provider
- #82228 — naming icon-only controls
- #16513 — icons on `core/button`; [an earlier comment there](https://github.com/WordPress/gutenberg/issues/16513#issuecomment-5607382626) gives the icon-only button markup this builds on
- #81225 — Core UI SVGs through the registry, and #82062, the open PR that [answers it](https://github.com/WordPress/gutenberg/issues/81225#issuecomment-5423688618) for SVG themes
- #80938 — a declarative `icon` in the widget pipeline
- #82501 — the Theme Switcher, an earlier consumer of the same icon language

---

## 메모 (게시하지 않음)

- **"span이 맞다"의 근거:** "아이콘은 인라인 문맥"이라는 일반론보다, 두 소스를 같은 요소에 칠해서 CSS 분기가 사라졌다는 **실제 결과**를 앞에 둔다(본문 1번).
- **동적 전환 입장:** 사용자 결정(2026-09-14)으로 개인 의견으로 제안한다. A/A′를 기각한 이유와 비용을 반드시 함께 둔다.
- **라벨 교체:** Non-goals 한 문단만. `aria-hidden` span 구조, 그룹 필터 제약 같은 세부는 넣지 않는다(`axismundi-dialogs/docs/BUTTON-STATE.md`에 있음).
- **Form:** 언급하지 않는다. VQA는 Surface/Button 증거용이다.
- **weight fallback 인용:** M3 buttons guidelines 원문 한 줄. 인용은 15단어 미만으로 유지했다.
- **아직 구현 안 된 것:** weight fallback은 플러그인에 없다(FILL만). 본문 4번은 "제안"으로만 쓰고 "플러그인이 한다"고 쓰지 않았다.
