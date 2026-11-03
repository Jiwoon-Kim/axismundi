# Legacy Community Migration

프로필 도입 전 로컬 Group을 보존하면서 Community 계약을 명시적으로 부여한다.
자동 배포 마이그레이션이 아니며 CLI가 없는 운영 환경에서도 관리자 화면으로 실행한다.

## Operator Path

Tools > Community Moderation에서 기존 선택기로 자기 Person을 선택한다.
Legacy Community migration을 펼쳐 대상 Group URI와 최초 moderator URI를 확인하고,
확인란을 선택한 뒤 Migrate Community를 실행한다. staging에서 실행할 대상은 운영자가 결정한다.

- 로그인 사용자는 관리자이며 Group과 명시된 Person 양쪽의 실행 권한이 있어야 한다.
- 대상은 프로필 미지정인 공개 로컬 Group, moderator는 공개 로컬 Person이다.
  양쪽 핸들이 잠겨 있어야 한다. 소유자나 현재 선택을 서버가 moderator로 추론하지 않는다.
- 폼은 Group/Person 쌍에 묶인 nonce와 명시적 확인을 요구한다. 제출 당시의 선택을 다시 읽지 않는다.
- 일반 프로필 편집 가드는 바꾸지 않는다. 이미 지정된 Community/Channel 등의 계약은 덮어쓰지 않는다.

## Commit Boundary

프로필 INSERT, Person의 Add, 최초 moderator 멤버십과 Initialize 로그는 같은 트랜잭션이다.
하나라도 실패하면 모두 롤백한다. 별도 principal이나 Follow 관계를 만들지 않는다.
기존 Group URI/UUID, 키, 핸들, 소유·실행 권한, Forum 콘텐츠와 바인딩은 변경하지 않는다.

Group의 Announce는 커밋 후 기존 제어 활동 배포 경로로 저작한다. 여기서 실패하면 이미 확정된
프로필과 moderator를 취소하지 않는다. 같은 Group/Person으로 Finish announcement를 재시도하면
기존 Add를 재사용한다. 이후 moderator가 변경되어도 재시도로 최초 명단을 복원하지 않는다.

## Hard Transition

공개 Community Actor의 attributedTo는 다음 컬렉션을 가리킨다.

```text
/wp-json/axismundi/v1/actors/{uuid}/moderators
```

빈 Community 컬렉션도 광고한다. Channel, Person, 프로필 미지정 Group에는 이 정책을 적용하지 않는다.
Forum의 이전 projection, 컬렉션 계약과 라우트 등록은 중단한다. 이전
`/wp-json/axismundi/v1/forum/groups/{uuid}/moderators`는 별칭이나 리다이렉트로 유지하지 않는다.
원격이 이전 참조를 캐시한 동안 실패할 수 있다는 하드 전환을 수용한다.
캐시 갱신을 흉내 내기 위한 Update나 임의 Announce를 새로 만들지 않는다.
원격이 언제 Actor를 재조회하는지, 실제 Lemmy 캐시가 언제 바뀌는지는 이 로컬 작업이 보장하지 않는다.

## Verification

```sh
python tools/wp-env/run_audits.py run --audit audit-community-migration.php --audit audit-community-migration-mutations.php --audit audit-community-review-admin.php --audit audit-community-moderators.php --audit audit-group-profile.php --audit audit-actor-projection.php
```

Forum 비활성에서 실행한다. 권한/nonce/확인, SQL 실패 롤백, 원본 신원 보존, 멱등 재시도,
Announce 복구, 신규 컬렉션 참조와 이전 라우트 부재를 검사한다.
Chrome용 localhost 한정 fixture는 Bridge tests의 community-moderation-ui-fixtures.php에서
AX_MOD_UI_LEGACY=1로 생성하고 같은 도구로 정리한다. 기존 운영 Group은 검증 대상으로 변경하지 않는다.

staging 배포·실행과 실제 Lemmy 왕복은 별도 수동 확인이다. 이 문서는 완료를 주장하지 않는다.
