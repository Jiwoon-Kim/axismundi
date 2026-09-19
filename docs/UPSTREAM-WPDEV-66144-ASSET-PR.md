# Draft — wordpress-develop draft PR: Twemoji COLRv1 font asset for #66144

> 2026-09-20: Core 배치 `--verify`(flags 포함) byte-identical 통과 → 본문 Reproducibility를 "both fonts"로 갱신. 재실행 CI의 `Build WordPress` 타임아웃 5개는 댓글 생략(사용자 결정: 한 건은 Grunt 완료와 같은 초에 타임아웃이라 같은 원인 단정 불가, 새 정보 적음).
> 2026-09-20: flags subset 추가(follow-up commit). full 폰트 해시 불변(`0e8645e0…`, 이전 byte-identical `--verify` 통과), flags는 같은 full에서 세 번 잘라 모두 `108cd5e8…`(리팩터 전 2회·후 1회+빌드 1회), Edge flag profile 265/265. 새 파이프라인 전체 `--verify`는 미실행(사용자 결정: 시간 늦어 생략, 본문에 명시).
> 2026-09-20: CI 댓글(PHPUnit matrix 5개 `Build WordPress` 타임아웃, trunk에도 같은 오류) 뒤 **ready for review로 전환**.
> 상태: **draft 게시됨** 2026-09-20 — [WordPress/wordpress-develop#13618](https://github.com/WordPress/wordpress-develop/pull/13618), head `5dc7d39b08`(11 files), `--github` 일치. 2026-09-20 본문에 `Related: #44001` 한 줄 추가(사용자 결정: privacy/로컬 제공 맥락 연결). Core 배치에서 `--verify` byte-identical 통과 후 게시. [Core Trac #66144](https://core.trac.wordpress.org/ticket/66144)의 첫 PR. 자산만, 런타임 경로 없음.
>
> 범위(사용자 결정 2026-09-20):
> - 자산만: Twemoji 17.0.3 COLRv1 WOFF2, 재현 빌드 스크립트·Dockerfile·lock·입력/출력 manifest, CC-BY 4.0 표기, aliases 기록.
> - `@font-face` 등록도 넣지 않는다: 로드 경로가 없으면 네트워크 비용도 동작 변화도 없고, 자산 리뷰를 renderer 설계와 떼어 받을 수 있다.
> - renderer(감지 profile별 wrapper, 이미지 fallback handoff, 에디터 iframe VQA)는 합의 뒤 별도 PR.
> - 검토 포인트는 라이선스, Core 안 자산 위치, 빌드 재현성, binary hash로 한정.
>
> 브랜치: `C:/Users/thaum/dev/wordpress-develop-66144`(worktree) `add/66144-twemoji-colrv1-font`, `upstream/trunk` `870ca6a8cc`에서 시작. 원 checkout의 `fix/63451-flag-fallback`은 건드리지 않음.
>
> 확인(2026-09-20):
> - WOFF2 657,364 B, SHA-256 `0e8645e0fcbb7c98b18eeaa099122633a9129823336f6a76a944a31be07a4700` = manifest. research `out/v17.0.3`에서 복사, 복사 후 해시 동일.
> - Core는 `wp-includes/**`를 통째로 build에 복사(Gruntfile 23행) → `fonts/twemoji/` 추가에 Gruntfile 변경 불필요. `src/wp-includes/fonts/`에는 dashicons 폰트가 이미 있음.
> - Core 트리의 CC 라이선스 선례는 Twenty 테마 readme의 CC0 이미지뿐. CC-BY 그래픽은 선례 없음 → 첫 검토 포인트.
> - `src/license.txt`: 서드파티 코드는 코드 주석에 크레딧.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-WPDEV-66144-ASSET-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Emoji: Add the Twemoji COLRv1 font and its reproducible build

## Body (GitHub Markdown — paste as is)

This adds Twemoji 17.0.3 as one COLRv1 color font and a flags-only subset of it, with the tooling that builds them, as the first step of #66144. Nothing loads the fonts yet: there is no `@font-face` and no change in behavior. Using it for the emoji a browser cannot draw, and handing off to the image fallback, is follow-up work that depends on the design discussion in the ticket.

- `src/wp-includes/fonts/twemoji/twemoji-colrv1.woff2`: 657,364 bytes, SHA-256 `0e8645e0fcbb7c98b18eeaa099122633a9129823336f6a76a944a31be07a4700`.
- `src/wp-includes/fonts/twemoji/twemoji-colrv1-flags.woff2`: 108,888 bytes, SHA-256 `108cd5e892d9ca9cab788b573115d9662b4aa6fa24b48520cf4b792951fee649`. It is cut from the full font with the fontTools subsetter and covers country, subdivision, rainbow, transgender and pirate flags: for browsers that draw other emoji but not flags, such as Chromium on Windows, it is about a sixth of the full font.
- Next to them: `manifest.json` (Twemoji commit and hashes, tool versions, output hashes, coverage counts), `source.txt`, `LICENSE-GRAPHICS` and `aliases.txt`.
- `tools/emoji/`: the build script, a Dockerfile with the base image pinned by digest, and `requirements.lock` pinning every Python package. See `tools/emoji/README.md`.

The font is built by maintainers when Twemoji is updated; sites only serve the file. The build downloads the Twemoji release by commit, checks the archive and SVG-set hashes, builds with nanoemoji, writes the CC-BY 4.0 attribution into the font's name table, and with `--verify` rebuilds and requires byte-identical output. It normalizes its input in two recorded ways: aliases for spellings without FE0F, which Edge needed to render every fully-qualified sequence as one glyph, and a square viewBox for the watermelon (`1f349.svg`), reported upstream in jdecked/twemoji#133. `tools/emoji/README.md` has the details.

In Edge 153 on Windows, all 3,944 fully-qualified RGI sequences in Emoji 17.0 render as one glyph in the font's colors, and every sequence in the flag profile does with the flags subset (265 fully-qualified). `manifest.json` records those results against each font's SHA-256. The research behind this, including the browser check, is at https://github.com/Jiwoon-Kim/axismundi/tree/c690d2e/research/twemoji-colrv1.

Points for review:

- **License.** The Twemoji graphics are CC-BY 4.0, and this would put them in Core's tree; the only CC-licensed assets I found in Core today are CC0 images in bundled themes. The attribution is in the font's name table, `LICENSE-GRAPHICS` and `source.txt`. Is that enough, or should `license.txt` or the credits mention it?
- **Location.** The font and its records are in `wp-includes/fonts/twemoji/`, next to Dashicons, so they ship with every install; `aliases.txt` and `manifest.json` add 40 KB of text. They could live under `tools/emoji/` instead, with only the font and its attribution in `wp-includes`.
- **Reproducibility.** `docker run … --verify` rebuilds from the pinned inputs and compares bytes; it takes about 20 minutes. It passed in this branch's layout for both fonts.
- **The binaries.** Each WOFF2's SHA-256 is in `manifest.json`, so a rebuild can be checked against the committed files.

Trac ticket: https://core.trac.wordpress.org/ticket/66144
Related: https://core.trac.wordpress.org/ticket/44001

## Use of AI Tools

- AI assistance: Yes
- Tool(s): Claude Code, Codex
- Model(s): Claude Opus 5 (Claude Code)
- Used for: the build script, the Docker setup, the browser checks and this description. I reviewed the changes, the measurements and the output.

---
**This Pull Request is for code review only. Please keep all other discussion in the Trac ticket. Do not merge this Pull Request. See [GitHub Pull Requests for Code Review](https://make.wordpress.org/core/handbook/contribute/git/github-pull-requests-for-code-review/) in the Core Handbook for more details.**

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
