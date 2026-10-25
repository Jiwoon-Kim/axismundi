# 어휘 선언 레지스트리

2026-10-25. `BRIEF-VOCABULARY-REGISTRY.md` 구현, 리뷰 대기.

## 경계

기존 `includes/vocabulary-registry.php`의 snapshot/type/property/binding/requirement
API는 유지한다. `vocabulary-context-registry.php`는 그 의미를 대체하지 않고
네임스페이스 및 조건부 context 메타데이터를 확장한다. 기존 코어 의미와 다른 IRI,
kind, container, 명시된 value shape를 등록하면 거부한다.

- `axismundi_op_register_namespace(prefix, args)`: base_iri와 reference를 분리한다.
  중복 prefix 등록은 WP_Error이며, 다른 기여자는 `axismundi_op_namespace()`로 참조한다.
- `axismundi_op_register_vocabulary_term(term, args)`: 명시적 전체 IRI, kind,
  provenance, source, contributor와 용어별 context를 등록한다. 동일 정의의 다른
  contributor는 합칠 수 있지만 정의 변경은 거부한다.
- `axismundi_op_vocabulary_term(term)`: 기존 의미 메타데이터와 선언 메타데이터를
  함께 조회한다. inbox와 orderedItems의 IRI를 이름에서 유도하지 않는다.
- 등록은 `axismundi_op_register_vocabulary` action에서 요청당 한 번 수행한다.
  기본 등록 실패는 예외로 중단하며, 실패한 로딩은 부분 레지스트리를 남기지 않는다.

context가 없는 등록은 문서에 아무것도 추가하지 않는다. context를 출력하는 등록에는
명시적 `when` 조건이 필요하다. product 용어가 as/schema/toot 등 남의 namespace를
차용해 새 용어를 주조하는 것은 거부한다. source/reference는 출력 context에 섞지 않는다.

## 조건과 출력

직렬화는 문서를 한 번 순회해 키·타입·경로를 수집한다. 입력 `@context`는 무시한다.
키의 존재와 null 아닌 키를 구별하고, 타입 배열과 scalar-only 조건도 구별한다.
조건은 수집 결과만 읽는다. Emoji의 compact/prefixed/전체 IRI는 명시적 등록 IRI로
연결하며, 문서가 제공한 접두사나 원격 context를 신뢰하거나 fetch하지 않는다.

`when`은 조건절의 AND이며, 각 조건절의 names와 일치 경로는 OR다. 경로는 root가
빈 문자열이고 object/tag/0처럼 표현한다. 키의 slash는 인코딩해 경로 위장을 막는다.
이 경로 정보로 기존 범위를 유지한다.

- QuoteRequest: root 또는 직접 object, scalar type.
- QuoteAuthorization: root type 또는 root formerType.
- quote/quoteAuthorization: root 키, 같은 inline-map group으로 병합.
- Miscellany·Security·Event: V1과 같은 재귀 키 검사; Security는 publicKey만 트리거.
- Media: root의 scalar media type. usedIn은 root에 null 아닌 값이 있을 때만 추가.
- Emoji: tag 배열과 object 체인의 tag만 검사한다. preview나 standalone type으로
  범위를 넓히지 않는다.

안정적인 priority/등록 순서와 group으로 기존 context 배열 순서·inline map 경계를
유지한다. 동일 선언은 한 번만 출력한다. 기존 `axismundi_op_jsonld_context` 필터는
호환용 후처리 seam으로 남지만, 프로젝트의 네 기여자는 등록 action으로 이관했다.
이 호환 필터까지 등록 검증으로 봉쇄한 것은 아니다.

기존 usedIn의 GitHub IRI는 바꾸지 않는다. `axisLegacy`는 내부 등록 이름일 뿐 출력에
새 접두사를 추가하지 않는다. w3id.org 등록·발행과 IRI 전환은 별도 작업이다.

## 검증 범위

`wp eval-file wp-content/plugins/axismundi-object-projections/tests/` 아래:

- `audit-context-iris.php`: V1의 조건부 선언 회귀 검사.
- `audit-vocabulary-context-registry.php`: 충돌·별칭·기여자·기존 context 순서와 범위.
- `audit-vocabulary-registry.php`: 기존 snapshot/의미/바인딩 API 회귀 검사.
- `audit-object-assembly-baseline.php`: 승인된 wire/view SHA-256 fixture, 갱신하지 않음.

접두사 충돌 허용, 무조건 선언, 타입 수집 제거, IRI를 as+이름으로 유도하는 네 변이는
각각 등록 충돌, QuoteRequest context, QuoteRequest context, inbox alias 검사에서 실패했다.
변이는 모두 복원했다. 추가 회귀 감사 결과는 브리프의 구현 기록에 남긴다.

이 구현은 전체 AS2 context 수집기나 JSON-LD expander가 아니다. snapshot의 기존
범위와 현재 발행하는 확장 선언만 다룬다. 원격 어휘 해석, React 렌더러, V2의
PropertyValue/value/size, Calendar 저장 모델은 변경하지 않는다.
