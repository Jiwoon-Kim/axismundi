# Twemoji COLRv1 font — build research

A reproducible build of Twemoji as one COLRv1 font, written the way WordPress Core could own it. It is research for [Core Trac #44001](https://core.trac.wordpress.org/ticket/44001): serving emoji locally as a single font file instead of thousands of images ([wordpress-develop#12252](https://github.com/WordPress/wordpress-develop/pull/12252) bundles 8,020 PNG and SVG files).

If it works, this folder could move almost as it is to `tools/emoji/` in wordpress-develop.

## Who builds, who serves

The font is built **once, by maintainers**, when Twemoji is updated. A release ships the WOFF2 and its manifest. WordPress sites never run this build: installing, updating or loading a page downloads no SVG and runs no Python, Docker or font compiler. They serve a static file.

The script and its pins are a supply-chain record: they show where the font came from and let anyone build it again and get the same bytes.

## Versions

| Version | Role |
|-|-|
| `v17.0.3` | The target. The font, manifest, coverage, sizes and license notes are built from it. |
| `v17.0.2` | What WordPress Core serves today (`s.w.org/images/core/emoji/17.0.2/`) and what #12252 bundles. Built once to compare with the image set. |

A Core change that ships a 17.0.3 font should update the image fallback to 17.0.3 too. Otherwise the font and the images draw different designs and cover different sequences.

## Files

| File | |
|-|-|
| `build-twemoji-colrv1.py` | Downloads the pinned Twemoji archive, checks it, builds with nanoemoji (`glyf_colr_1`), writes the attribution into the name table, compresses to WOFF2, measures coverage, writes the manifest. `--verify` builds again and requires identical bytes. |
| `sources.json` | Per version: tag commit, commit date, archive SHA-256, SHA-256 of the SVG set, SVG count. Also Unicode's `emoji-test.txt` 17.0 and its SHA-256. |
| `Dockerfile`, `requirements.in`, `requirements.lock` | The build environment: a base image pinned by digest, and every Python package pinned. |
| `out/<version>/` | The build writes `twemoji-colrv1.woff2`, `manifest.json`, `source.txt`, `LICENSE-GRAPHICS` and `aliases.txt`. This repository keeps everything but the WOFF2 for `v17.0.3`, and only `manifest.json` for the `v17.0.2` comparison build: the font belongs in the adopting project as a release asset, and the manifest records its SHA-256, so a rebuild can be checked against it. |
| `vqa/index.html` | The browser rendering check. |

## Build

```sh
docker build -t twemoji-colrv1-build .
docker run --rm -v "$PWD:/work" -v "<cache>:/cache" twemoji-colrv1-build v17.0.3 --cache /cache --out /work/out/v17.0.3
docker run --rm -v "$PWD:/work" -v "<cache>:/cache" twemoji-colrv1-build v17.0.3 --cache /cache --out /work/out/v17.0.3 --verify
```

`--record` pins the hashes of a version whose entries in `sources.json` are still `null`. After that, a changed archive or SVG set stops the build.

`SOURCE_DATE_EPOCH` is set to the Twemoji commit date, so the font's timestamps do not change between builds.

## Acceptance

The font is accepted on rendering in a browser, not on shaping:

1. **Every fully-qualified RGI sequence** in Unicode's `emoji-test.txt` (Emoji 17.0) renders in Chromium as one glyph in the font's colours, without the browser falling back to another font.
2. **The strings Core's emoji detection hands to the fallback** render the same way.

HarfBuzz shaping is supporting evidence only. The first build, without aliases, shaped all 3,944 fully-qualified sequences to one visible glyph in HarfBuzz, yet in Edge (Chromium) 960 of them rendered split: every sequence whose Twemoji SVG name includes FE0F. In Chromium, the GSUB mappings that include FE0F did not apply to them.

## Normalization

The build changes its input in two recorded ways. Neither is part of Twemoji; `manifest.json` and `source.txt` record the rules and hashes.

- **Aliases**: each fully-qualified sequence with FE0F gets a copy of its SVG under the spelling with every FE0F removed, and each minimally-qualified or unqualified spelling in `emoji-test.txt` gets one too. Without them, the Edge VQA of the first build rendered the sequences above split. Whether the aliases are enough for Chromium is tested by the Edge VQA of the build that includes them; until then, that they are required is a working hypothesis. The manifest records the rule, the count and the SHA-256 of the list.
- **Upstream source normalization**: files listed in `SQUARE_VIEWBOX` get their viewBox padded to a centered square. `1f349.svg` in `v17.0.3` (viewBox `0 0 36 25.22`) is a candidate case of a non-square SVG viewBox that does not match the advance width of the rest of the nanoemoji output: in the first build its advance was 1713, against 1275 for every other glyph. It is reported in [jdecked/twemoji#133](https://github.com/jdecked/twemoji/issues/133), with a fix open in [#102](https://github.com/jdecked/twemoji/pull/102). The manifest records each file's SHA-256 before and after. Any other SVG with a non-square viewBox stops the build.

Two kinds of evidence are kept apart:

- **Shaping**, measured with HarfBuzz and written to `manifest.json`: `visible` hides default-ignorable characters such as FE0F; `strict` counts only sequences shaped to exactly one glyph.
- **Rendering**, checked in a browser with `vqa/index.html`: each sequence is drawn on a canvas in this font and in the system emoji font, and passes when it is one glyph wide, is not plain black ink, and differs from the system font's drawing.

## Results

From `out/*/manifest.json` and the Edge VQA (Edge 153 on Windows):

| | `v17.0.3` | `v17.0.2` |
|-|-|-|
| WOFF2 | 657,364 bytes | 657,128 bytes |
| Rebuild | byte-identical | — |
| Edge: fully-qualified RGI | 3,944 / 3,944 | 3,944 / 3,944 |
| Edge: minimally-qualified, unqualified, component | 1,029, 243, 9 of 1,029, 243, 9 | same |

The two releases have byte-identical SVG sets; v17.0.3 changed only the JavaScript parser. For comparison, the v17.0.2 image sets are 4,009 PNG files (4,248,486 bytes) and 4,009 SVG files (10,121,593 bytes).

The font maps `#`, `*`, `0`-`9` (zero advance, as keycap bases), the space, and characters that default to text presentation such as `©` and `↔`. It has to be applied only to emoji a renderer has detected and wrapped, not placed in a general `font-family` stack.

## Pins

- The archive is downloaded by commit, not by tag, and its SHA-256 is checked. GitHub does not promise that archive bytes never change, so the SVG set has its own hash (each file's name and SHA-256, sorted): if GitHub recompresses the archive, that hash shows whether the content changed.
- The coverage count uses Unicode's `emoji-test.txt` for Emoji 17.0, pinned by SHA-256.

## License

Twemoji's code is MIT; its graphics are CC-BY 4.0, which requires attribution. The font carries it in its name table (copyright, license description and license URL), and `source.txt` and `LICENSE-GRAPHICS` travel with the font.

## References

- [googlefonts/nanoemoji](https://github.com/googlefonts/nanoemoji) builds the font.
- [googlefonts/color-fonts](https://github.com/googlefonts/color-fonts) has a `twemoji-glyf_colr_1` config; the settings here follow it (`glyf_colr_1`, `clipbox_quantization = 32`). Its Twemoji submodule is a 2020 `twitter/twemoji` commit, so its prebuilt font is not used.
