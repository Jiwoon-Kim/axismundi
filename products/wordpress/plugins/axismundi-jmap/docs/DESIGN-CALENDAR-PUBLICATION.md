# Calendar 변경과 연합 발행 경계

2026-10-23 논의 기록. 방향 합의이며 Calendar 구현·스키마 변경은 이번 범위가 아니다.
JMAP Calendars의 구체적인 메서드·필드·지원 타입은 구현 시점의 공식 명세와 별도로 대조한다.

## 기준

- WP User는 인증된 작업자다. 로컬 Account와 Actor는 동일한 운영 엔터티의 표현이다.
- Account를 사용하는 작업과 Calendar 접근 권한을 각각 검사한다.
- JMAP 변경과 ActivityPub 발행을 독립된 이중 쓰기로 구현하지 않는다.
- 직렬이라는 말은 도메인 변경에서 선택적인 연합 발행으로 이어지는 처리 순서를 뜻한다.
  JMAP HTTP 요청을 ActivityPub C2S HTTP 요청으로 다시 전달한다는 뜻은 아니다.

```text
WP User 인증
-> 명시적인 Account 선택 및 인가
-> 대상 Calendar 인가
-> 일정 도메인 명령
-> 원본 저장 및 동기화 변경 기록
-> 필요한 경우 연합 발행 의도 기록
-> AS2 표현 투영
-> Actor의 Create/Update 및 연합 전달
```

JMAP과 실제 ActivityPub C2S를 모두 지원한다면 각 프로토콜의 입력 계약은 유지하고
공통 도메인 쓰기 서비스를 호출한다. 이미 저장된 변경을 다시 Create 명령으로 실행해서
원본이나 Activity를 중복 생성하지 않는다. ActivityPub S2S 수신 역시 별도 입력 계약이다.
비공개 일정이나 일반 연락처 수정은 자동으로 공개 연합 발행으로 이어지지 않는다.

원본·동기화 변경 기록·발행 의도의 원자적 저장과 이후 전달 재시도를 검토한다.
현재 Contacts의 commit 이후 hook은 durable outbox나 변경 원장을 대신하지 않는다.
공통 operations 테이블 또는 새 플러그인을 도입한다는 결정은 하지 않았다.

## 보존할 관계

| 축 | 의미 |
|---|---|
| 인증 User | 이번 작업을 실행한 사용자 |
| 작업 Account | 사용자가 선택한 운영 맥락 |
| 데이터 소유 Account | 레코드의 저장·동기화 경계 |
| Calendar 소속 | 일정이 배치되는 Calendar 관계 |
| attributedTo | 객체의 저작 귀속 대상, 복수 수용 |
| Activity.actor | 해당 발행 행위를 수행한 Actor |
| organizer / participant | 일정의 주최·참여 관계 |

이 축들이 같은 값을 가질 수 있지만 자동으로 동일시하지 않는다.
공유 Calendar를 편집하는 경우 작업 Account와 데이터 소유 Account의 차이를 특히 확인한다.
Calendar 소유자·주최자·참여자를 자동으로 attributedTo에 추가하지 않는다.
AS2 attributedTo는 복수값을 허용하지만 단일값도 유효하다.
Calendar의 복수 소속 가능 범위와 교차 Account 공유는 별도 명세·인가 검토 대상이다.

post_author는 WordPress 문서의 저작자 연결이며 변경 가능한 값이다.
모든 작업자의 역사적 감사 기록이나 Account·Calendar 관계 전체를 대체하지 않는다.

## 저장 방향

Calendar의 원본 일정은 WordPress 게시물 없이 존재할 수 있어야 한다.
블록 에디터가 필요한 상세 문서는 선택적인 wp_posts 연결로 제공하는 방향이다.

```text
Account -> Calendar -> 독립적인 일정 레코드
                         -> 선택적인 WordPress 블록 문서
```

시간·반복·상태·안정적인 일정 식별자는 Calendar 도메인에서 관리하고,
문서 본문은 Gutenberg로 편집할 수 있게 한다. 필드별 원본 소유권을 명시하여
JSCalendar와 게시물 양쪽에 독립적인 원본을 만들지 않는다.
Task를 Event CPT로 강제하지 않는다. JSCalendar의 Task 지원이 곧바로
JMAP Calendars 구현의 Task 지원을 뜻하는 것은 아니다.

## 현재 코드 근거

- Calendar includes/schema.php의 ax_events는 post_id가 기본키다.
  일반 Event 저장은 아직 게시물에 종속된다.
- includes/system-items.php는 공휴일·달 위상 등을 게시물 없이 별도로 저장한다.
  따라서 현재 모든 Calendar 항목이 CPT라는 설명은 정확하지 않다.
- includes/event-actor.php는 post_author, Calendar authority, acting Actor를 구분한다.
  다만 acting Actor를 attributedTo·Create.actor·organizer에 연결하는 현재 정책은
  각 관계를 독립적으로 표현할 때 재검토해야 한다.
- includes/projection.php는 현재 attributedTo에 단일 acting Actor URI를 투영한다.

## 후속 설계와 범위

1. 현재 일정·system item·외부 일정·참여·배치 관계를 조사한다.
2. 독립 일정 ID와 기존 post_id·JSCalendar uid·AS2 URI의 대응 및 URI 보존을 정한다.
3. 문서 연결의 필드 권위, autosave·revision 복원·삭제·공개 범위 동작을 정한다.
4. User·작업 Account·데이터 소유 Account·Calendar 인가 계약을 정한다.
5. 변경 원장과 발행 의도의 저장·재시도·중복 방지 경계를 정한다.
6. 현재 JMAP Calendars 명세와 기존 Activity 처리 경로를 대조한 뒤 구현 슬라이스를 나눈다.

이 문서는 Calendar 이전을 승인하거나 완료했다고 선언하지 않는다.
당장의 Contacts 후속 작업은 변경 경로 조사와 Account/type 변경 원장이다.
Self Card 때문에 비표준 필드·메서드·Principal 바인딩을 추가하지 않는 기준도 유지한다.

## 근거 문서

- [ActivityPub C2S](https://www.w3.org/TR/activitypub/#client-to-server-interactions)
- [AS2 attributedTo](https://www.w3.org/TR/activitystreams-vocabulary/#dfn-attributedto)
- [JSCalendar 객체 타입](https://www.rfc-editor.org/rfc/rfc8984.html#section-2)
- [JMAP 표준 및 초안 목록](https://jmap.io/spec/index.html)
