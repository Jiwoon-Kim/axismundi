# 어휘 의미와 C2S 정책

## 구분

의미 스냅샷은 OWL 추론 선언을 보관하며 입력 스키마나 OWL 추론기를 구현하지 않는다.
domain/range는 타입 추론의 근거이지 자동 거부 조건이 아니다. Functional 선언은 여러
표기가 동일 개체라는 추론으로 이어질 수 있으며 JSON 배열 자체의 금지와 같지 않다.
독립적으로 다른 개체임을 선언한 경우에는 온톨로지 불일치가 될 수도 있다.

따라서 의미 기록, 제품 검증 정책, 실행 감사는 분리한다. 레지스트리에 값을 넣었다는
이유만으로 입력 거부가 구현되었다고 주장하지 않는다. 정책은 기존
`axismundi_op_register_normative_requirement()`로 등록하고 실제 검사와 감사 위치를
`enforced_by`에 기록한다. 제품 정책은 `LOCAL_POLICY`이며 AS2의 MUST로 표시하지 않는다.
특정 FEP의 필수 요구는 `FEP_MUST`로 기록한다. 예를 들어
`question-create-wrapping`은 [FEP-9967](https://codeberg.org/fediverse/fep/src/branch/main/fep/9967/fep-9967.md)
투표 발행에만 적용하며, AS2의 단독 Question 수신을 금지하지 않는다.

## 이번 보정

전체 63개 속성 중 단축형 58개였으며 actor·subject를 구조화하여 단축형 56개,
구조화 7개가 되었다. relationship은 기존 구조화 항목을 수정했다. 나머지 단축형의
`runtime_cardinality=unspecified`는 미조사 상태를 정직하게 남기는 의도이며 결함으로
일괄 처리하지 않는다.

| 속성 | 기록 | 실행 정책 변경 |
| --- | --- | --- |
| actor | Activity domain, Object/Link range, one-or-many, subproperty_of attributedTo | 없음 |
| subject | Relationship domain, Link/Object range, rdf_functional=true, subproperty_of rdf:subject | 없음. runtime_cardinality는 unspecified |
| relationship | Relationship domain, rdf:Property range, subproperty_of rdf:predicate, iri-or-object | 없음 |

`subproperty_of`는 완전한 IRI 배열이며 `semantic_family` 분류와 별도다. 새 필드가
없는 항목은 빈 배열이다. 컨텍스트 기여자가 기존 하위속성 선언을 덮어쓸 수 없다.
세 속성의 source에는 어휘 명세·OWL 문서·JSON-LD 컨텍스트의 주소를 함께 기록한다.
컨텍스트의 relationship `@type:@id`에 맞춰 value_shape를 수정했지만, 이 레지스트리
값이 런타임 JSON-LD 압축/확장을 수행하거나 요청을 검증하는 것은 아니다.

current/first/last/next/prev/partOf도 OWL에서 Functional ObjectProperty로 선언되어
있지만 이번 범위에서는 변경하지 않았다. subject의 선언을 기록한 것만으로 이들에
새 cardinality 정책을 적용하지 않는다. author는 OWL에서 deprecated이며
attributedTo 사용을 안내하므로 새 지원 용어로 등록하지 않는다.

## 행위자와 객체 귀속

`actor rdfs:subPropertyOf attributedTo`는 그 Activity 자체에 적용된다.
Create.actor가 A라면 그 Create의 attributedTo 관계를 추론할 수 있는 것이지,
Create.object의 귀속·영구 권한·공동저자 동의가 자동으로 증명되는 것은 아니다.
객체의 로컬 발행 계정과 공동귀속 집합 분리는 별도 제품 계약과 권한 검사로 결정한다.
복수 attributedTo의 순서로 주 계정이나 승인 여부를 추론하지 않는다.

## local-note-attribution

이 첫 운영 등록은 이미 존재하는 `axismundi_op_prepare_local_note()`의 정책을
기록한다. 함수의 입력 검사나 허용 범위를 바꾸지 않는다.

- 대상은 OP의 로컬 Note 작성 슬라이스다. WordPress post/attachment 공동귀속과 별개다.
- attributedTo가 생략되거나 null이면 기존 동작대로 outbox Actor URI를 넣는다.
- 명시 값은 outbox Actor URI와 정확히 같은 문자열이어야 한다.
- 다른 URI 또는 배열은 현재 슬라이스에서 `ax_op_local_author`, HTTP 403으로 거부한다.
  singleton 배열 거부도 현재 로컬 계약의 기록이지 AS2 일반 규칙이 아니다.
- C2S Create 정상 명령은 Note와 Activity를 저장한다. 위반은 준비 단계에서 끊겨 Object,
  Activity, 색인, 수신 기록, 배송 기록 쓰기와 commit 이벤트가 없어야 한다.
- 감사는 `axismundi-activities/tests/audit-c2s-create-note.php`다. 정상 명령에 명시적
  동일 귀속을 넣고, 잘못된 scalar·singleton 배열·공동귀속 배열이 구체 오류 코드로
  거부되는지 및 해당 테이블 쓰기/행 수/commit 이벤트를 검증한다.

이 정책은 OWL에서 새로 도출한 강제가 아니며, 레지스트리 기록과 기존 동작의
일치를 증명하기 위한 것이다. subject의 입력 정책은 Relationship 작성 경로가
생길 때 검토한다. 필요하면 정규화 후 최대 한 값을 계약으로 삼을 수 있지만,
현재 Note 전용 타입 게이트의 거부를 subject 검사 성공으로 세지 않는다.
이 작업은 [A] 복수 귀속의 선행 조건이 아니다.

## 근거와 검사

- [AS2 어휘](https://www.w3.org/TR/activitystreams-vocabulary/)
- [AS2 OWL](https://www.w3.org/ns/activitystreams-owl)
- [AS2 JSON-LD 컨텍스트](https://www.w3.org/ns/activitystreams)
- [OWL 2 Primer](https://www.w3.org/TR/owl2-primer/)

```sh
wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-vocabulary-registry.php
wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-vocabulary-context-registry.php
wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-create-note.php
```

입력 수락/거부 감사는 C2S 명령 서비스를 직접 호출한다. HTTP 인증이나 외부 피어의
배열 처리 호환성을 측정한 것으로 확대하지 않는다.
