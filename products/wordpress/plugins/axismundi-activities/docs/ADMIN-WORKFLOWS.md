# Community Review And Reply Composer

## Scope

관리자 UI 두 개를 기존 서비스 위에 얹는다. Tools의 C2S Composer는 공개 Note 답글만
저작하고, Community Moderation은 선택된 Person의 로컬 Community 중재 목록과 pending
제출을 보여준다. Topic 저작, moderator 명단 편집, 승인 후 철회는 포함하지 않는다.

기존 Community Review를 흡수하며 `tools.php?page=axismundi-community-review` URL은 유지한다.
메뉴는 `read` 권한으로 항상 등록한다. 선택 누락/불가, 비-Person 선택, 중재 커뮤니티 없음,
선택한 Community에 권한 없음은 내용을 유출하지 않는 별도 상태로 렌더한다.
비로그인 사용자의 인증을 우회하거나 없는 moderator 권한을 부여한다는 뜻은 아니다.

`DESIGN-C2S-COMPOSER.md`의 과거 전체 계획(Like/Follow/활동 로그 UI)보다 좁은 슬라이스다.
현재 인계의 답글 지원이 과거 문서의 "답글 제외"를 이 화면에 한해서 대체한다.

## Boundaries

- Account 선택은 Actors의 기존 선택기와 setter를 사용한다. UI의 선택은 권한 증거가 아니다.
- 선택 커널의 쿠키 기반 모드와 레거시 user meta 모드를 그대로 따른다. 화면이 별도 선택
  저장소를 만들지 않는다. 폼의 Person ID는 렌더 당시의 행위 주체이며 POST에서 현재 선택으로
  바꿔 읽지 않는다. 로그인 사용자와 그 Person의 현재 실행 권한은 다시 검증한다.
- 심사 목록과 POST 어댑터는 현재 실행 권한과 moderator 멤버십을 검사한다. 실제 결정은
  `axismundi_act_review_community_post()`만 수행한다. 명단 편집 서열 규칙은 복제하지 않는다.
- 멤버십 역조회는 Actors의 `axismundi_actors_local_communities_moderated_by()`가 소유한다.
  Activities는 그 결과에 실행 권한을 적용한다. Managed actors는 principal 기반 Account
  목록만 다루며 Forum moderator 함수나 moderator만을 위한 접근 예외를 사용하지 않는다.
- nonce는 moderator ID, Group ID, Object URI에 묶인다. 결정과 Announce의 원자성,
  mod log 및 재시도 계약은 기존 서비스의 트랜잭션을 그대로 따른다.
- 목록은 Group별 pending 행을 keyset으로 최대 20개씩 보여준다. 객체와 저자 텍스트는
  이스케이프하며 원격 본문의 이미지나 스크립트를 관리 화면에서 실행하지 않는다.
- 거부 사유 필수는 우리 로컬 심사 정책이다. UI/POST와 Actors 결정 writer가 검사한다.
  `ax_community_posts.review_reason`은 상태 및 mod log와 같은 트랜잭션에 저장한다.
  같은 결정 재시도는 같은 사유만 허용하고, 이미 확정된 사유를 덮어쓰지 않는다.
  이 사유를 원격 Reject/삭제 summary로 발신하지 않으며 공개 로그도 추가하지 않는다.
- 컴포저는 실제 Actor outbox에 REST nonce와 `Idempotency-Key`로 POST한다. 입력은 본문과
  부모 URI, 공개 범위이며 Group 주소지정은 서버가 한다. 저장 함수를 직접 호출하지 않는다.
- 네트워크/서버 오류 시 입력과 같은 요청의 멱등 키를 유지한다. 본문이나 부모가 바뀌면
  새 의도로 새 키를 만든다. 성공 후 입력을 비우고 응답 Location을 표시한다.
- Note 본체는 OP local objects에 저장된다. wp_posts와 wp_comments로 답글을 만들지 않는다.

## Verification

```sh
python tools/wp-env/run_audits.py run --audit audit-community-review-admin.php --audit audit-community-review-admin-mutations.php --audit audit-community-replies.php --audit audit-c2s-create-note.php --audit audit-community-moderators.php --audit audit-object-read-api.php
node products/wordpress/plugins/axismundi-activities/tests/audit-c2s-composer.cjs
```

심사 어댑터 감사는 기존 Community 승인/거부/로그 실패 롤백 계약을 폼 입력으로 실행한다.
변이는 어댑터 권한 재검사, nonce, 서비스 호출 누락, 선택 재독, 사유 필수 해제 및 조건부
메뉴 등록 복원을 검사한다. Forum 비활성에서 실행한다.
이 로컬 감사는 실제 Lemmy 왕복이나 승인/거부를 상대 UI가 표시하는지 증명하지 않는다.

Chrome의 승인/거부 확인용 임시 자료는 Bridge tests의 `community-moderation-ui-fixtures.php`를
명시적으로 setup/cleanup한다. localhost에서만 작동하고 운영 User를 새로 만들거나 선택을
변경하지 않는다. 임시 원격 Actor의 delivery endpoint를 제거해 외부 발송을 막는다.
