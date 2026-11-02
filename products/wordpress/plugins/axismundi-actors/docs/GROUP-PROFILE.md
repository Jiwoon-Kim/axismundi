# Local Group Federation Profile

Group의 로컬 계약은 `ax_actormeta`의 `policy:federation_profile`에 저장한다.
Actor 생성 트랜잭션에서만 기록하며 일반 메타 writer는 이 키를 거부한다.
`axismundi_actors_group_profile()`은 로컬 Group만 읽고 나머지는 null을 반환한다.

- `community`: 생성 가능. Lemmy 상호운용 프로필의 구현 자리이며 완성된 호환성 선언이 아니다.
- `channel`: 생성 폼에 비활성으로 표시하고 서버에서도 거부한다. 능력은 선언하지 않는다.
- 미설정: 기존 Group과 기존 내부 생성 호출을 그대로 유지한다. 자동 분류하거나 소급 할당하지 않는다.

새 관리화면의 Group 생성은 명시 선택을 요구한다. 생성 이후에는 미설정 Group에도
프로필을 추가할 수 없다. Actor 관리화면 요청과 `/axismundi/v1/actors/` REST 변경 요청은
프로필 변경 입력을 거부한다. 새 REST 생성/수정 endpoint를 만드는 슬라이스는 아니다.

프로필은 향후 발신 계약과 로컬 Group 수신·집행 정책 모두의 입력이다.
원격 플랫폼 추측, moderators의 역할 판정, principal 권한 부여에는 사용하지 않는다.
이번 구현은 `attributedTo` 등 와이어 속성이나 기존 Group 처리기를 바꾸지 않는다.

## Verification

Forum 밖 회귀 감사는 Actors의 `tests/audit-group-profile.php`에 둔다.
후속 moderators 및 Group 제출 감사도 Forum을 활성화하지 않고 실행할 수 있어야 한다.

```sh
python tools/wp-env/run_audits.py run --audit audit-group-profile.php --audit audit-group-profile-mutations.php
```

검사 범위: 실제 저장·재조회, 미설정 유지, 비-Group 거부, Channel 거부,
메타·관리화면·REST 변경 거부, 와이어 비노출. 변이는 타입·Channel·불변 입력 검사를
각각 제거하고 해당 거부 단언이 실패하는지 확인한다. 실제 브라우저 폼 검증은 별도다.
