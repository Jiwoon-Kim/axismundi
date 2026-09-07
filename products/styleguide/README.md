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

## 로컬 실행

```bash
python ../../tools/generators/sync_styleguide_fonts.py
bundle install
bundle exec jekyll serve
```

`baseurl`이 `/axismundi/styleguide`라서 로컬 주소는
`http://localhost:4000/axismundi/styleguide/`입니다.

첫 줄을 건너뛰면 사이트는 뜨지만 웹폰트 없이 시스템 폰트로 렌더됩니다. 폰트
파일과 `assets/css/fonts.css`는 Git에 없고 이 스크립트가 만듭니다.

## 이 사이트가 하지 않는 것

`axismundi-lab`의 specimen을 복제하지 않습니다. Lab은 컴포넌트가 실제로 어떻게
동작하고 측정됐는지를 보관하는 작업대이고, 이 사이트는 그 결과를 설명하고 근거를
Lab으로 링크합니다. 같은 컴포넌트를 두 번 구현하기 시작하면 둘은 반드시
어긋납니다.

디자인 토큰도 여기서 정의하지 않습니다. `assets/css/styleguide.css`의 `--sg-*`는
문서 사이트의 크롬(레이아웃, 내비게이션, 여백)일 뿐입니다. 색과 타입스케일
(`--md-ref-*`, `--md-sys-*`, `--wp--preset--*`)은 아직 들어오지 않았고, 그 전까지
비슷한 이름을 새로 만들지 않습니다.

## 폰트

폰트는 이미 정해졌습니다. 제품이 배포하는 파일을 빌드 때 가져옵니다.

```
themes/axismundi                    Roboto Flex / Mono   (theme.json)
plugins/…korean-font-provider       Noto Sans KR         (provider CSS)
        ↓ sync_styleguide_fonts.py
products/styleguide/assets/fonts/   복사본 (Git 제외)
products/styleguide/assets/css/fonts.css  생성됨 (Git 제외)
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
