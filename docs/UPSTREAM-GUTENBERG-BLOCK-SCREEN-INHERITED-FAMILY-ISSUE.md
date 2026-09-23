# Draft — Gutenberg issue: the Blocks screen does not resolve the inherited font family

> 상태: **초안.** 올릴 곳: [WordPress/gutenberg](https://github.com/WordPress/gutenberg/issues) 새 이슈.
> 사용자 승인(2026-09-24): 이슈와 draft PR 모두 지금 만들어도 된다. 다만 ready 전환은
> [#83456](https://github.com/WordPress/gutenberg/pull/83456) 흐름을 본 뒤.
>
> **범위:** Styles → Blocks → <block> 화면 **하나**. 블록 인스펙터는 이미 루트를 해소하므로
> 제외한다. [#83456](https://github.com/WordPress/gutenberg/pull/83456)의 디스크립터 파싱과도
> 무관하다 — 그쪽은 faces를 **어떻게 읽는가**, 이쪽은 faces를 **찾느냐 마느냐**.
>
> **측정(2026-09-24, trunk `4b9625ffc3`에서 엔진 함수 직접 호출):** 루트에 `typography.fontFamily`가
> 있고 `core/paragraph`에는 없는 Global Styles로 두 해소기를 호출한 결과 —
>
> ```
> getStyle( gs, 'typography', 'core/paragraph' )      → { lineHeight: "1.6" }
> resolveStyle( gs, { blockName: 'core/paragraph' } ) → { fontFamily: "var:preset|font-family|probe-mono",
>                                                         lineHeight: "1.6" }
>                                        sources[ 'typography.fontFamily' ] = { layer: "root" }
> ```
>
> wp-env는 이 환경에서 못 띄웠다(`dns.resolve('WordPress.org')`가 막혀 wp-env가 오프라인으로
> 오판 → 코어 미다운로드). 그래서 UI 스크린샷이 아니라 엔진 함수 측정으로 증거를 잡았다.
> Gutenberg 23.7.1 사이트에서는 인스펙터도 일반 목록을 보였는데, `resolveStyle` 기반 상속이
> 그 버전에 없기 때문으로 보인다 — 이슈 본문에는 trunk 사실만 쓴다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-BLOCK-SCREEN-INHERITED-FAMILY-ISSUE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

### Description

In **Styles → Blocks → <block>**, the typography panel builds its Appearance options from the block's own font family. When the block has none and inherits one from the root styles, no family is resolved, and the control falls back to the built-in list of ten weights and their italics.

That list is not what the block renders in. A theme whose root font declares `"fontWeight": "100 700"` still gets `Extra Bold`, `Black` and `Extra Black` offered on this screen, and choosing one saves a weight the face does not provide.

This is the contract #49090 asked for and #61915 implemented: offer the weights and styles the selected font actually has. The block inspector already resolves the inherited family, so the two surfaces disagree about the same block: Text and Blocks propose different Appearance options for text that renders identically.

### Step-by-step reproduction instructions

1. Use a theme that declares a font family whose face covers part of the weight range, and sets it as the root font:

```json
{
	"settings": {
		"typography": {
			"fontFamilies": [
				{
					"slug": "probe-mono",
					"name": "Probe Mono",
					"fontFamily": "\"Probe Mono\", monospace",
					"fontFace": [
						{
							"fontFamily": "Probe Mono",
							"fontStyle": "normal",
							"fontWeight": "100 700",
							"src": [ "file:./assets/probe-mono.woff2" ]
						}
					]
				}
			]
		}
	},
	"styles": {
		"typography": { "fontFamily": "var:preset|font-family|probe-mono" }
	}
}
```

2. Open **Styles → Typography → Text** and note the font and the Appearance options, which come from the declared range.
3. Open **Styles → Blocks → Paragraph**, leaving its own font unset, and open **Appearance**.

### Expected results

The options come from the family the block resolves to, so they stop at `Bold`, as they do on the Text screen.

### Actual results

The font control reads `Default` and Appearance offers the built-in list, `Thin` through `Extra Black` and an italic for each, including three weights Probe Mono does not declare.

### What differs

The two surfaces read Global Styles through different resolvers. Given a root `typography.fontFamily` and a `core/paragraph` that sets only `lineHeight`:

| Resolver | Used by | `typography` it returns |
| --- | --- | --- |
| `getStyle( gs, 'typography', 'core/paragraph' )` | `screen-block.tsx`, via `useStyle( prefix, name, 'merged' )` | `{ lineHeight }` |
| `resolveStyle( gs, { blockName: 'core/paragraph' } )` | the block inspector, via `useResolvedStyle()` | `{ fontFamily, lineHeight }`, the family attributed to `{ layer: 'root' }` |

`getStyle()` reads `styles.blocks.<name>.<path>` and does not fall back to `styles.<path>`, so a block that inherits its font resolves none. `resolveStyle()` carries a `root` layer, which is why the inspector sees it.

### Why this is about the editor, not the cascade

A paragraph inherits the root font on the front end either way; that part works. What is missing is the editor resolving the *effective* family before deciding which capabilities to advertise. Falling back to a generic list turns "this block has no font of its own" into "this block can use any weight", which is the case #49090 was filed about.

Either the effective family should be resolved and its faces used, or, where it genuinely cannot be resolved, the control should say so rather than substitute a list no font backs.

This would use the effective inherited family only for capability lookup. It would not change the block's declared typography values, whether the Font control displays `Default`, or the provenance shown for inherited values.

### Environment info

- Gutenberg trunk (`4b9625ffc3`)

### Please confirm that you have searched existing issues in the repo.

Yes

### Please confirm that you have tested with all plugins deactivated except Gutenberg.

Yes
<!-- end of body -->
