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

## ④b: C2S local Object 답글

답글은 `wp_posts`나 `wp_comments`가 아니라 OP local Objects의 Note다.
Activities의 Community reply 정책이 기존 공개 부모의 `audience`/`to`/`cc`에서
Group을 해소한다. 원격 소프트웨어 이름이나 NodeInfo로 플랫폼을 판정하지 않는다.
로컬 Group은 Community 프로필이고 부모가 승인됐거나 유효한 Group Announce가
있어야 한다. 부모 자신의 Group 주장은 로컬 게시 승인 근거가 아니다.

공개 답글은 Group을 `to`와 Object `audience`에, Public과 부모 저자를 `cc`에 둔다.
비공개 답글은 공개로 넓히지 않는다. 다중 Group 맥락은 임의로 하나를 고르지 않는다.
원격 Group 제출에 Follow를 강제하지 않는다. 로컬 Community는 기존 승인된 Follow
정책을 재사용하며, 답글도 pending 심사 후 moderator 승인으로 Announce한다.

일반 C2S Note의 URI형 Create는 유지한다. Group 답글에 한해서 Create에 생성 당시
Note를 인라인으로 보존하므로 Group이 원본 Person 저작을 그대로 감쌀 수 있다.
본문 저장과 원장 기록은 기존 C2S 트랜잭션을 사용한다. 수신 receipt/admission은
그 커밋 뒤 처리하며 동일 Idempotency-Key 재시도는 본문을 복제하지 않고 연결을 복구한다.
로컬 receipt는 Bridge의 기존 recipient 차단·수신 정책을 통과해야 한다. 정책 구현이
없거나 거부되면 심사 큐에 넣지 않는다. Activities가 transport 검사를 대신하지 않는다.

Bridge는 wire의 `cc`를 보존하되 실제 직접 제출은 Community Inbox 하나로만 보낸다.
부모 저자나 Person 팔로워에게 Create를 별도 배송하지 않는다. 배포는 Group Announce의
책임이다. 개인 공개 Outbox에서도 Group 답글 Create를 제외한다.
저장된 Group 답글의 Delete도 직접 Group 배송을 유지한다. 로컬 Group의 Delete
재배포·철회 정책은 이번 Create/심사 이식과 별개다.

Forum의 기존 Note 주소지정·객체 확장·답글 Announce·개인 Outbox 필터와 원격
제출 관측 훅은 해제했다. 옛 함수와 감사는 참고 자료로 남기며 CPT-Note를 되살리지 않는다.
Forum의 로컬 Topic 바인딩과 그 의존 코드는 유지한다. 기존 Forum 관리 UI·테이블을
통째로 삭제하거나 마이그레이션한 것은 아니며 이 작업과 구분한다.

## C2S Audience

Composer의 Visibility는 공개 범위를, Audience는 선택한 Community Group URI를 뜻한다.
Visibility의 기본 to/cc는 입력란에 표시하고, 프리셋을 바꿔도 별도로 입력한 주소는 남긴다.
Audience 선택기는 현재 발행 Person이 수락된 Follow를 가진 Group과 현재 사용자가
운영할 수 있는 로컬 Community를 표시한다. 직접 URI 입력도 같은 제출 정책을 적용한다.
로컬은 Community 프로필, 원격은 캐시된 컬렉션 타입 선언 또는 검증된 모더레이터
스냅샷을 근거로 삼는다. 후보를 표시하거나 제출할 때 원격 역참조를 하지 않는다.

Audience가 명시된 Note는 Public 또는 Quiet public이어야 하며, 부모가 있으면 같은
Community에 속해야 한다. 로컬 최상위 Note는 Forum 없이 Inbox 수신과 심사를 거쳐
승인된 후 Announce된다. 원격 최상위 Note는 해당 Group Inbox로만 보낸다.
기존의 원격 수신 루트 Page/Article 정책과 Forum Topic 편집 정책은 그대로다.

## 남은 표면

- 심사 목록·관리 UI는 Activities의 Community Moderation으로 제공한다
  (`docs/ADMIN-WORKFLOWS.md`). 외부 심사 API와 승인 후 철회는 별도 범위다.
- Forum 레거시 관리 표면·저장소 정리 (로컬 Topic 바인딩과 의존성을 별도 점검)
- 기존 CPT-Note 감사는 컷오프 상태에서 실행 불가하므로 단언 계약을 새 저장소 감사에 이전

따라서 이 슬라이스만으로 실제 Lemmy 왕복이 완료됐다고 하지 않는다.
기존 Forum 감사는 삭제하거나 느슨하게 하지 않았다.

## 검증

```text
python tools/wp-env/run_audits.py run --audit audit-community-posts.php --audit audit-community-posts-mutations.php --audit audit-actor-inbox.php --audit audit-collection-controls.php --audit audit-community-moderators.php
python tools/wp-env/run_audits.py run --audit audit-community-replies.php --audit audit-community-replies-mutations.php --audit audit-c2s-create-note.php
```

Forum 비활성에서 수행한다. 새 감사는 Forum 테이블 쿼리가 실행되면 실패하고,
principal/Follow 스냅샷이 바뀌지 않는지 확인한다. Bridge의 verified handoff부터
검사하며 실제 HTTP 서명 생성·원격 Lemmy 배달은 이 fixture의 범위가 아니다.

[FEP-1b12](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md)의
원본 활동 보존은 루트 Create 승인 경로에서 검사한다. 수신 fixture의 JSON과 Bridge
스풀에 기록된 요청 본문의 Announce.object를 비교한다. 객체 키 순서만 무시하며
id, 확장 속성, 중첩 객체, 배열 순서, 배열형 context, proof 값은 보존돼야 한다.
proof를 제거한 변이가 비교에서 실패하는지도 확인한다. proof 자체의 유효성은
검사하지 않는다. 테스트 배송 예약을 차단하고 워커는 실행하지 않는다.

블라인드 주소가 있다는 이유만으로 Inbox 수신을 거부하지 않으며 원장도 수정하지
않는다. 송출 시 Bridge는 Announce.object가 수신 원장 활동과 일치하는 경우에만
원본 보존을 요청한다. 최종 렌더러는 자체 Announce의 bto/bcc를 제거하고 원본
Create 및 그 안의 객체는 그대로 둔다. [ActivityPub의 발신 규칙](https://www.w3.org/TR/activitypub/#client-to-server-interactions)과
FEP-1b12의 원본 보존을 별개로 적용하는 제품 정책이다. 감싸인 주소는 새 배송
대상을 만들지 않지만, 기존 Announce 수신자는 감싸인 문서의 해당 값을 볼 수 있다.

Inbox·공개 활동 조회의 표시용 복사본은 중첩 bto/bcc도 제거한다.
`manage_options`가 필요한 Activity Log는 원문 진단을 위해 그대로 표시한다.
감사는 원본 필드 보존, 바깥 주소 제거, 실제 큐 수신자, 표시용 생략을 각각 검사한다.
