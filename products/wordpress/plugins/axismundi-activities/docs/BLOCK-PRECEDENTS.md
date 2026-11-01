# Person Block 선례 조사

2026-11-01 소스 독해. 실행·서버 간 왕복 검증은 하지 않았다. 아래 커밋은 조사 시점의
upstream HEAD이며 운영 인스턴스의 배포 버전을 뜻하지 않는다. 우리 정책을 확정하거나
Group/Forum 제재를 구현하는 문서가 아니다. Person Follow 전이와 Block을 함께 다룰 때의
판단 자료다. 컴포저·Mail·Todo는 이번 조사 범위 밖이다.

## 1. 표준과 배포 관행을 구분한다

[AP §6.9](https://www.w3.org/TR/activitypub/#block-activity)는 차단된 사용자의 상호작용을
제한하도록 권고하며 Block을 그 대상에게 전달하지 않도록 권고한다. 그러나 아래의
Mastodon·Misskey는 원격 대상에게 Block을 전달한다. 표준의 SHOULD NOT과 배포 선례가
다르므로 전송 여부는 명시적으로 결정해야 한다. 상대 서버의 협조가 없어도 로컬 차단은
작동해야 한다.

## 2. Mastodon

커밋 `147bfc1f1784b173a1ce157830c480787dd13fe0`.

- [BlockService](https://github.com/mastodon/mastodon/blob/147bfc1f1784b173a1ce157830c480787dd13fe0/app/services/block_service.rb):
  양방향 accepted Follow를 UnfollowService로 해제하고, 상대가 보낸 pending 요청을
  RejectFollowService로 거절한다. Block 행을 만든 뒤 원격 AP 대상의 inbox로 Block을 보낸다.
  이 코드만으로 모든 방향의 pending 정리까지 확인했다고 일반화하지 않는다.
- [inbound Block](https://github.com/mastodon/mastodon/blob/147bfc1f1784b173a1ce157830c480787dd13fe0/app/lib/activitypub/activity/block.rb):
  대상이 로컬인지 확인하고 양방향 Follow를 끊는다. 중복 Block은 관계 URI를 갱신하고 반환한다.
- [AfterBlockService](https://github.com/mastodon/mastodon/blob/147bfc1f1784b173a1ce157830c480787dd13fe0/app/services/after_block_service.rb):
  홈·리스트 피드, 알림·알림 요청, 대화 목록을 정리한다. 관계 변경과 화면 정리는 별개 작업이다.
- [FollowService](https://github.com/mastodon/mastodon/blob/147bfc1f1784b173a1ce157830c480787dd13fe0/app/services/follow_service.rb):
  어느 방향으로든 차단 관계가 있으면 새 Follow를 허용하지 않는다.
- [UnblockService](https://github.com/mastodon/mastodon/blob/147bfc1f1784b173a1ce157830c480787dd13fe0/app/services/unblock_service.rb):
  차단을 해제하고 원격 AP 대상에게 Undo(Block)을 보낸다. 이전 Follow를 자동 복구하지 않는다.

## 3. Misskey

커밋 `3a43e05fa59012abfe6b6642c1eb4d6e3b48b801`.

- [UserBlockingService](https://github.com/misskey-dev/misskey/blob/3a43e05fa59012abfe6b6642c1eb4d6e3b48b801/packages/backend/src/core/UserBlockingService.ts):
  양방향 pending 요청 취소와 양방향 unfollow를 수행한다. outgoing pending은 Undo(Follow),
  incoming pending은 Reject(Follow)을 전송한다. 로컬 사용자가 원격 사용자를 차단하면
  Block을 그 inbox로 전송하고, 해제 시 Undo(Block)을 전송한다. 해제는 기존 Follow를
  자동 복원하지 않는다. 리스트·채팅방 초대 정리와 캐시·내부 이벤트 갱신도 수행한다.
- [UserFollowingService](https://github.com/misskey-dev/misskey/blob/3a43e05fa59012abfe6b6642c1eb4d6e3b48b801/packages/backend/src/core/UserFollowingService.ts):
  로컬 Follow는 양방향 차단을 검사한다. 로컬 수신자가 차단한 원격 발신자의 Follow에는
  Reject를 보낸다. 특이점: 원격 발신자가 로컬 수신자를 차단한 것으로 기록돼 있는데
  새 Follow를 보내면 그 원격 발신자 소유의 차단을 해제하는 분기도 있다. 이 정책은
  선례로만 기록하며 우리에게 자동 채택하지 않는다. inbound Block 파서 자체는 미검토다.

## 4. Lemmy: 개인 차단과 운영 제재가 다르다

커밋 `dd033a3d243e33d3fc51070c8dadf1477748e13f`.

- [개인 사용자 차단 API](https://github.com/LemmyNet/lemmy/blob/dd033a3d243e33d3fc51070c8dadf1477748e13f/crates/api/api/src/local_user/block.rs):
  자기 자신 차단을 거부하고 PersonActions의 block/unblock을 기록한다. 이 경로에는
  연합 Activity 전송이나 Follow 해제가 없다.
- [PersonActions 저장 구현](https://github.com/LemmyNet/lemmy/blob/dd033a3d243e33d3fc51070c8dadf1477748e13f/crates/db_schema/src/impls/person.rs):
  blocked_at을 기록·해제한다. 개인 차단을 연합 운영 제재와 동일시하지 않는다.
- [커뮤니티 차단 API](https://github.com/LemmyNet/lemmy/blob/dd033a3d243e33d3fc51070c8dadf1477748e13f/crates/api/api/src/community/block.rs):
  사용자 쪽 커뮤니티 차단 시 로컬 구독을 해제하며 federated unfollow 경로를 호출한다.
  이는 커뮤니티 운영자가 사용자를 ban하는 것과 다르다.
- [연합 BlockUser](https://github.com/LemmyNet/lemmy/blob/dd033a3d243e33d3fc51070c8dadf1477748e13f/crates/apub/activities/src/block/block_user.rs):
  actor는 제재 실행자, object는 제재 대상 사용자, target은 Site 또는 Community다.
  사유·만료·콘텐츠 제거 정책도 포함한다. 수신 시 target에 맞는 관리자·모더레이터
  권한을 검증하고 ban을 적용한다. 커뮤니티 ban 수신에서는 후속 unban을 받기 위해
  구독을 유지하는 코드와 사유 주석이 있다. 개인 Block의 양방향 unfollow 정책으로
  이 동작을 가져오면 안 된다.
- [UndoBlockUser](https://github.com/LemmyNet/lemmy/blob/dd033a3d243e33d3fc51070c8dadf1477748e13f/crates/apub/activities/src/block/undo_block_user.rs):
  위 운영 제재의 해제이며 개인 차단 해제와 별개다.

## 5. 우리 슬라이스에 대한 권고와 미결

아래는 조사 결과에서 도출한 권고이지 오너가 확정한 정책이 아니다.

1. Person Block과 Group/Site ban을 분리한다. 이번에는 Person Block만 다룬다.
2. Person Block 시 양방향 Follow와 pending 요청을 정리하는 방향은 Mastodon·Misskey
   선례가 있다. 우리 불변 원장에서는 행 삭제가 아니라 권한 있는 별도 Undo/Reject 등
   전이로 표현해야 한다. 원격 Actor를 사칭해 전이를 만들지 않는다.
3. 차단 중 새 Follow를 막고, Unblock이 과거 Follow를 자동 복원하지 않도록 검증한다.
4. **결정됨 (2026-11-01).** Block/Undo(Block)을 대상 Actor 의 inbox 로 전달한다.
   로컬 대상에게도 전달하되 `local` 방향 구분은 되살리지 않는다. AP §6.9 의
   SHOULD NOT 에서 의도적으로 벗어나는 것이므로 RELATIONS.md 2 에 NON-STANDARD 로
   이유와 함께 기록했다. 로컬 집행은 전달 성공 여부에 의존하지 않는다.
5. 차단 사실이 공개 outbox·로그·알림에 어떻게 노출되는지도 결정해야 한다. 단순히
   AP Block으로 기록했다는 이유로 Public 수신자를 붙이지 않는다.

필요한 검증: accepted/pending 양방향 정리, 차단 중 새 Follow·늦은 Accept 처리,
중복 Block/Undo의 멱등성, 권한 없는 해제 거부, Unblock 뒤 Follow 미복구,
로컬·원격 조합. 댓글·Like·알림·배달 필터 등 전체 상호작용 차단 범위는 따로 명시하고,
Follow만 막아 놓고 모든 상호작용을 막는다고 광고하지 않는다.
