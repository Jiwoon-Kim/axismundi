# Axismundi Style Guide

Jekyll 기반 공개 문서 사이트. **이 디렉터리가 정본입니다** — 생성된 미러가
아닙니다.

```
정본   products/styleguide/
공개   https://jiwoon-kim.github.io/axismundi/styleguide/
```

두 경로가 다른 것은 배포가 하는 유일한 변환입니다. CI가 이 디렉터리를 빌드해
artifact의 `styleguide/` 아래에 놓습니다. `_site/`는 그 과정의 중간 산출물이고
Git에 들어가지 않습니다.

## 빌드

```bash
bundle install
python bin/build.py --serve
```

`baseurl`이 `/axismundi/styleguide`라서 로컬 주소는
`http://localhost:4000/axismundi/styleguide/`입니다.

`jekyll`을 직접 부르지 않는 이유가 있습니다. 이 사이트의 입력 두 가지가 Git에
없어서, 깨끗한 checkout에서 Jekyll만 돌리면 **폰트 없이 시스템 폰트로**
렌더됩니다. 실제로 확인한 결과입니다.

```
bin/build.py
  1. 폰트 동기화    제품의 woff2 복사 + fonts.css 생성
  2. 타이포그래피    _data/typography.yml → tokens.sys.typography.css
  3. check          생성 CSS가 생성기 출력과 일치하는가
  4. validate       그 값이 발행된 스펙과 일치하는가
  5. jekyll         build 또는 serve
```

```
--prepare   1-2 만
--verify    1·3·4 (아무것도 새로 쓰지 않음). CI가 쓰는 모드
--serve     5를 serve로
```

3과 4는 다른 질문에 답합니다. **check**는 파일이 생성기 출력과 같은지 묻고
손편집·미갱신을 잡습니다. **validate**는 그 값이 스펙과 같은지 묻고 생성기
자체의 버그를 잡습니다 — 그건 check가 통과시킵니다. 실제로 생성기에 오차를
주입해 확인했습니다: `check=0 validate=1`.

## 이 사이트가 하지 않는 것

`axismundi-lab`의 specimen을 복제하지 않습니다. Lab은 컴포넌트가 실제로 어떻게
동작하고 측정됐는지를 보관하는 작업대이고, 이 사이트는 그 결과를 설명하고 근거를
Lab으로 링크합니다. 같은 컴포넌트를 두 번 구현하기 시작하면 둘은 반드시
어긋납니다.

색도 여기서 정의하지 않습니다. 이 사이트는 **테마의 팔레트 그대로** 렌더됩니다.
`styleguide.css`에 리터럴 색은 0개이고, 남은 `--sg-*`는 레이아웃 치수와
"이 표면은 어느 role에 앉는가"를 정하는 별칭 둘뿐입니다.

## 색과 elevation

타이포그래피와 반대 방향입니다. 여기서는 **베끼지 않고 가져옵니다.**

```
themes/axismundi/assets/styles/
  tokens.ref.css                 리터럴 팔레트 (94개)
  tokens.sys.color.light.css     역할 32개 → ref
  tokens.sys.color.dark.css      같은 32개, 다크 매핑
  tokens.sys.elevation.css       shadow 공식 + shadow/scrim role
        ↓ sync_styleguide_assets.py (바이트 그대로)
assets/css/product/              Git 제외
```

typeface는 배포 테마에 아예 없어서 스펙을 옮겨 적었지만, **색은 테마에 최신
상태로 있습니다.** 스타일가이드가 따로 옮겨 적은 팔레트를 문서화하면 아무도
배포하지 않는 색을 설명하게 됩니다.

네 파일은 자족적입니다 — WordPress 선택자도, 외부 참조도 없고, dark 블록이
`:root:not([data-theme])`까지 덮으므로 이 사이트에서 손대지 않고 동작합니다.
실측 결과:

```
OS dark (속성 없음)   primary #D0BCFF   surface #141218   shadow none
data-theme="light"    primary #6750A4   surface #FEF7FF   shadow 2겹
data-theme="dark"     primary #D0BCFF   surface #141218   shadow none
```

다크에서 그림자가 사라지는 건 버그가 아니라 M3입니다. 그쪽에서는 elevation을
물리적 그림자가 아니라 **tonal surface 차이**로 읽습니다. 이 사이트에서
`<pre>` 하나가 `--md-sys-elevation-shadow-level1`을 쓰므로 그 동작을 직접 볼 수
있습니다.

## 타이포그래피

세 층입니다. 크기 값은 한 군데에만 나옵니다.

```
_data/typography.yml        발행된 M3 스펙의 정본 데이터   ← 여기를 고칩니다
        ↓ tools/generators/generate_styleguide_typography.py
tokens.sys.typography.css   --md-sys-typescale-*   생성물, 직접 편집 금지
tokens.ref.typeface.css     --md-ref-typeface-*    어떤 계열 (손으로 씀)
styleguide.css              역할을 요청 (크기를 쓰지 않음)
```

`tokens.sys.typography.css`는 생성물이지만 **커밋합니다.** PR에서 타이포그래피
변경을 데이터 diff가 아니라 그대로 읽을 수 있어야 하니까요. 대신 `--check`가
손편집을 막습니다.

Jekyll도 `_data/typography.yml`을 `site.data.typography`로 읽으므로, 나중에
타입스케일을 문서화하는 페이지가 CSS를 만든 것과 같은 숫자를 렌더할 수 있습니다.

M3 정식 이름을 씁니다. 프로젝트 전체에 개념당 이름 하나가 `--sg-` 병렬 집합보다
낫다는 판단입니다. 대신 specimen 페이지가 테마나 Lab의 토큰 CSS를 함께 로드하면
정의가 둘이 되므로, 그때는 specimen을 스코프로 격리해야 합니다.

이 사이트의 위계는 문서용이라 `display`(57px)를 쓰지 않습니다.

```
h1  headline-large    h3    title-large     nav       label-large
h2  headline-small     본문  body-large      캡션·푸터  body-small / body-medium
```

**행간은 문서 언어를 따릅니다.** M3는 세로 공간이 더 필요한 문자 체계를 위해
행간 세트를 네 개 발행합니다. 레이아웃이 `lang`을 보고 `data-language-height`를
정하며, 한국어·일본어·중국어는 `medium`, 나머지는 baseline입니다. 한국어 본문
행간이 24px 대신 27px가 되는 게 그 결과입니다.

## 폰트

폰트는 이미 정해졌습니다. 제품이 배포하는 파일을 빌드 때 가져옵니다.

```
themes/axismundi                    Roboto Flex / Mono   (theme.json)
plugins/…korean-font-provider       Noto Sans KR         (provider CSS)
        ↓ tools/generators/sync_styleguide_assets.py
assets/fonts/                       복사본 (Git 제외)
assets/css/fonts.css                생성됨 (Git 제외)
```

사본을 커밋하면 core 원본·제품 서브셋에 이어 **세 번째 사본**이 되고, 테마가
폰트를 다시 서브셋할 때 사이트만 조용히 옛것을 보여줍니다.

폰트 스택도 이 사이트가 지어내지 않습니다. 테마가 쓰는 그대로입니다.

```css
"Roboto Flex", var(--axismundi-cjk-sans, system-ui), sans-serif
```

라틴은 Roboto가 맡고 CJK 자리는 비워둡니다. 그 변수는 지역별 provider
플러그인이 `:lang()` 아래에서 채우고, `unicode-range` 덕분에 한글만 Noto로
갑니다. provider가 없으면 `system-ui`로 떨어지는데, 그건 provider 플러그인을
설치하지 않은 사이트가 실제로 보이는 모습입니다.

Roboto Serif와 Material Symbols는 아직 가져오지 않습니다. 세리프 면을 쓰는
페이지가 없고, Symbols는 3.8MB인데 아이콘을 다루는 문서가 아직 없습니다. 둘 다
스크립트에 한 줄이면 들어옵니다.

## 현재 상태

뼈대와 홈만 있습니다. `_foundations/`, `_components/`, `_wordpress/` 컬렉션은
비어 있고, 각 구역의 landing 페이지가 비었을 때 무엇이 들어올지 안내합니다.

Pages 배포는 아직 전환하지 않았습니다. 지금 공개되는 것은 여전히 루트
`styleguide/` 미러(`publish_styleguide.py`가 `axismundi-lab`에서 생성)입니다.
이 사이트가 `/styleguide/`를 넘겨받는 시점에 그 미러와 생성기를 제거합니다.
