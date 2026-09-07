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
bundle install
bundle exec jekyll serve
```

`baseurl`이 `/axismundi/styleguide`라서 로컬 주소는
`http://localhost:4000/axismundi/styleguide/`입니다.

## 이 사이트가 하지 않는 것

`axismundi-lab`의 specimen을 복제하지 않습니다. Lab은 컴포넌트가 실제로 어떻게
동작하고 측정됐는지를 보관하는 작업대이고, 이 사이트는 그 결과를 설명하고 근거를
Lab으로 링크합니다. 같은 컴포넌트를 두 번 구현하기 시작하면 둘은 반드시
어긋납니다.

디자인 토큰도 여기서 정의하지 않습니다. `assets/css/styleguide.css`의 `--sg-*`는
문서 사이트의 크롬(레이아웃, 내비게이션, 본문 서체)일 뿐입니다. 설명 대상인
디자인 시스템(`--md-ref-*`, `--md-sys-*`, `--wp--preset--*`)을 어떻게 들여올지는
아직 정하지 않았습니다 — 이 제품의 assets에 넣을지, 빌드 때 테마에서 가져올지.
그 전까지 비슷한 이름을 새로 만들지 않습니다.

## 현재 상태

뼈대와 홈만 있습니다. `_foundations/`, `_components/`, `_wordpress/` 컬렉션은
비어 있고, 각 구역의 landing 페이지가 비었을 때 무엇이 들어올지 안내합니다.

Pages 배포는 아직 전환하지 않았습니다. 지금 공개되는 것은 여전히 루트
`styleguide/` 미러(`publish_styleguide.py`가 `axismundi-lab`에서 생성)입니다.
이 사이트가 `/styleguide/`를 넘겨받는 시점에 그 미러와 생성기를 제거합니다.
