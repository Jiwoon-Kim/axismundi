# Article 목록 표현 정책

## 경계

이 정책은 OP의 Article 스트림/목록 응답에만 적용한다. 코어 Object 엔진의
일반 표현 모드나 미디어 embedded 정책으로 통합하지 않는다. FEP-b2b8의
본문 생략 규칙을 구현한 것이 아니며, 그 FEP의 준수 여부를 주장하지 않는다.

`includes/integrations/article-list.php`를 제거해도 기존 상세 읽기와 객체
투영은 그대로 동작한다. 부트스트랩의 해당 require가 유일한 연결이다.

## 호출

```php
// Already authorized, sanitized Object document; future feed consumers use this boundary.
$list = axismundi_op_article_response( $document, 'list' );
$with_media = axismundi_op_article_response( $document, 'list', 'include' );
$detail = axismundi_op_article_response( $document, 'detail' );

// Application consumers use existing session-authorized reads, without remote fetching.
$list = axismundi_op_read_application_article_list( $object_id );
```

목록에서는 최상위 `content`와 `contentMap`을 함께 제외한다. 최상위
`attachment`는 기본 제외하고 `include` 요청이면 기존 값을 그대로 유지한다.
첨부 선택은 본문 포함 여부를 바꾸지 않는다. 상세는 전달받은 문서를 그대로 반환한다.

이는 필드 허용 목록이 아니다. `image`, `summary`, `preview` 등 나머지 값은
재작성하지 않는다. 특히 `preview.attachment`를 재귀적으로 제거하지 않는다.
Note와 Tombstone 등 Article 이외 타입은 변경하지 않는다. 어떤 표현에서도
객체 ID를 바꾸거나 별도 목록 객체를 저장하지 않는다.

이 함수 자체는 읽기 권한이나 HTML 정규화를 제공하지 않는다. 일반 호출자는
기존 권한·정규화 경계를 통과한 문서만 전달한다. 애플리케이션용 함수는 기존
`axismundi_op_read_application_object()`를 먼저 호출하므로 거부 결과를 유지한다.
첨부를 포함한다고 비공개 자산 접근, 다운로드 또는 바이너리 캐싱 권한이 생기지 않는다.

## 이번 범위 밖

- `/feed/as2/` 등록 및 full/list 설정 UI
- social stream의 실제 응답 연결과 외부 요청 파라미터
- 미디어 캐싱, 썸네일 선취득 및 라이선스 정책
- 나머지 카드 필드 확정 및 중첩 preview 크기 최적화
- 클라이언트 캐시 병합: 목록 응답의 속성 부재로 기존 상세 본문을 지우지 않는 보장

속성 부재는 부분 표현이라는 표준 와이어 신호가 아니다. 이번에는 별도 플래그를
내보내지 않으며, 클라이언트는 자신이 호출한 읽기 표면의 계약을 기준으로 판단한다.
이 정책만으로 전체 목록 응답의 크기가 제한된다고 주장하지 않는다.

## 검증

```text
python tools/wp-env/run_audits.py run --audit audit-article-list.php --audit audit-article-list-mutations.php --audit audit-application-object-read.php --audit audit-object-read-api.php
```

새 감사는 본문 쌍 생략, 선택적 첨부, 중첩 preview 보존, 동일 ID, 입력 불변,
무저장, 비-Article 보존 및 미등록 객체 거부를 검사한다. 변이는 본문 생략 제거,
contentMap 잔류, ID 변경, 중첩 첨부 제거, 본문/첨부 선택 결합을 각각 주입한다.
기존 상세 경로는 변경하지 않으며 별도 기존 읽기 감사로 회귀를 검사한다.
PHP 감사는 클라이언트 캐시 규칙을 증명하지 않는다.
