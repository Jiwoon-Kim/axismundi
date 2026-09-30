# Axismundi Schema

이 디렉터리는 Axismundi의 versioned, machine-readable contract canonical source다.
WordPress product 구현체가 아니라 모노레포가 소유한다.

## 구조

```text
schemas/
  style/
    <major>.json
  component/
    <major>.json
```

발행된 schema는 immutable public URL과 일대일로 대응한다.

```text
schemas/style/1.json
  -> https://schemas.axismundi.dev/style/1.json

schemas/component/1.json
  -> https://schemas.axismundi.dev/component/1.json
```

`latest.json`은 나중에 browsing convenience로 발행할 수 있지만 manifest는 numbered URL을
참조해야 한다. Product code는 이 contract를 소비하며 canonical schema 복사본을 소유하지
않는다.

이 디렉터리 setup은 schema 내용이나 hosting workflow를 정의하지 않는다. 둘 다 해당
specification이 합의된 뒤에만 추가한다.
