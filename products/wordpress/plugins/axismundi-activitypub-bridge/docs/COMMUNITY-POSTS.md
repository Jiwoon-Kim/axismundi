# Community 게시 수신

## 현재 범위: ④a

원격 Person이 로컬 Community의 Inbox에 직접 보낸 공개 `Create(Page|Article)`의
수신과 수동 심사를 Forum 없이 처리한다. 검증된 Person 저작은 기존 Activities
원장에, 원격 객체 표현은 기존 OP 캐시에, Group의 배포 결정은 Actors의
`ax_community_posts`에 둔다. 이 셋은 같은 상태가 아니다.

Bridge의 기존 서명 검증 및 차단 경계를 통과한 직접 수신만 대상으로 한다.
Group 주소와 실제 Group Inbox receipt를 모두 검사한다. 인라인 객체가 원장의
Create 저자와 일치하고 같은 origin을 사용해야 하며, 공개 캐시 관측이 있어야 한다.
cross-origin 객체 저작의 추가 증명은 이 슬라이스에서 지원하지 않는다.
원격 Group이 Announce만 한 경우에는 이 경로가 실행되지 않는다.

## 제출과 심사

기존 Forum의 루트 제출 정책처럼 승인된 Follow를 요구한다. `Page`와 `Article`을
받되 bare `Note`와 답글은 루트로 받지 않는다. 새 Community의 기본은 수동 심사이며
`posting_restricted_to_mods`나 Forum 정책 테이블을 가져오지 않는다. Channel과
Person은 제출 대상이 아니다. 기존 미설정 Group의 legacy Forum 수신은 변경하지 않는다.

중복 수신은 기존 원장과 하나의 pending 행을 재사용한다. 같은 Object URI에 다른
Create 증거를 덮어쓰지 않는다. 정책상 입장을 거부해도 이미 인증된 수신 Activity와
OP 관측을 삭제하지 않는다. admission 거부는 객체 자체의 무효화가 아니다.

```php
$result = axismundi_act_review_community_post(
    $user_id, $moderator_person, $community_group, $object_uri, true
);
// Pass false to record rejection instead of approval.
```

일반 운영 권한만으로 승인할 수 없다. 현재 moderators 구성원인 Person을 운영할
권한이 있어야 한다. 승인 시 기존 moderators 상태를 잠그고 현재 역할을 재검증한다.
Group Announce, approved 상태, 기존 moderation log의 `ApprovePost`는 한 트랜잭션이다.
거부는 `RejectPost`와 rejected 상태만 남기고 원격 Person의 Reject를 만들지 않는다.
log의 `person_uri`는 제출자이고 `activity_uri`는 원본 Create이므로 대상 Object를
원장에서 추적할 수 있다. 동일 검토자의 같은 결정은 재시도 가능하며 반대 결정은 거부한다.

Announce는 기존 Create payload를 그대로 중첩한다. 원본 저자를 Group으로 바꾸지
않으며, 출처 객체를 수정하거나 별도의 Person Activity를 합성하지 않는다.
실제 네트워크 배달은 기존 outbound queue가 처리한다.

## 부모 해소

OP의 `reply-parent-acquisition.php`는 공개 Note Create가 커밋된 뒤 미지의
`inReplyTo` 부모 하나만 비동기로 취득한다. 로컬/기존 캐시를 먼저 확인한다.
수신은 실제 Inbox receipt가 있어야 하고, 발신은 기존 outbound Create여야 한다.
조회·렌더·익스플로러는 이 취득을 시작하지 않는다.

기존 `ax_op_background_acquisition` opt-in을 예약과 실행 시 모두 존중한다.
기본 OFF이며 이 기능이 설정을 켜지 않는다. 기존 OP safe HTTPS fetch의 URL,
redirect, 크기 제한을 재사용한다. 최초 시도와 최대 두 번의 지연 재시도만 수행한다.
실패·인증 필요는 부모의 삭제로 보지 않고 기존 edge와 심사 상태를 유지한다.
부모 취득은 그 부모의 Create를 만들지 않는다. 부모의 부모 탐색이나 `replies`
컬렉션 수집은 하지 않는다. 목록/상세 표현 정책과 별개다.

## 인계문 주소지정 정정

[FEP-1b12 Final](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md)의
Threads and comments 예제는 답글의 `audience`를 Group으로 둔다. Announce 절은
수신 Activity를 그대로 감싸도록 요구하며, followers-only 제출이나 수동 심사를
허용한다. [FEP-7888 Draft](https://codeberg.org/fediverse/fep/src/branch/main/fep/7888/fep-7888.md)의
context는 이 발신 주소를 부모 저자 audience로 바꾸라는 규칙이 아니다.

기존 `audit-forum-thread-context.php`의 remote Comment 픽스처는 부모 저자 audience와
Group cc를 입력으로 사용하지만, 발신 단언은 Group to/audience와 Public·부모 저자 cc다.
④ 인계문은 이 입력과 출력을 혼동했다. 기존 발신 코드를 변경하지 않는다.

## 아직 완료하지 않은 ④b

- 로컬 Person의 Note 발신 및 원격/로컬 Community 답글 심사 연결
- 위 수신 주소 변형을 포함한 Forum thread context/배포 서비스 추출
- Forum을 로컬 Topic CPT 바인딩으로 축소하고 기존 Forum 감사와 함께 검증
- 심사 목록·관리 UI 및 외부 심사 API

따라서 이 슬라이스만으로 ④ 전체 또는 실제 Lemmy 왕복이 완료됐다고 하지 않는다.
기존 Forum 코드와 감사는 삭제하거나 느슨하게 하지 않았다.

## 검증

```text
python tools/wp-env/run_audits.py run --audit audit-community-posts.php --audit audit-community-posts-mutations.php --audit audit-actor-inbox.php --audit audit-collection-controls.php --audit audit-community-moderators.php
```

Forum 비활성에서 수행한다. 새 감사는 Forum 테이블 쿼리가 실행되면 실패하고,
principal/Follow 스냅샷이 바뀌지 않는지 확인한다. Bridge의 verified handoff부터
검사하며 실제 HTTP 서명 생성·원격 Lemmy 배달은 이 fixture의 범위가 아니다.
