# Account Operations: B Server Slice

## 구현 범위

`includes/account-operations.php`에 프로토콜 비종속 서버 API를 추가했다.
Actors는 JMAP 없이 동작하며 Session 또는 HTTP 엔드포인트를 만들지 않는다.

- `create_account()`: 현재 인증 User에게 internal, handle-less Account 생성.
- `set_workspace()`: 기존 `ax_acting_actor_identity` 키를 재사용하는 선택 저장.
- `current_workspace()`: 접근 가능한 선택 → 명시적 workspace 기본값 → 기존 개인 Actor 순서.
- `set_default_account()` / `default_account()`: 용도별 User 기본값 저장·재검증.
- `workspace_publisher()`: 현재 작업 공간이 발행 가능할 때만 반환.

생성은 기본적으로 차단된다. 개발 검증에서만
`axismundi_actors_account_creation_enabled` 필터를 명시적으로 true로 설정한다.
현재 인증 User와 `edit_posts` 권한이 필요하고 다른 User의 소유권을 주입할 수 없다.
다섯 AS2 Actor 타입을 허용하며, Person은 personal 기본값 true, 나머지는 false다.
명시적 personal 선언은 타입과 독립적이다. 이는 검토 대상인 제품 기본 정책이지
JMAP이 요구하는 타입 분류가 아니다. 이름 제한은 현재 보수적으로 191 bytes다.

초기 운영 owner 관계는 개인 귀속 유무와 무관하게 Actor 생성 트랜잭션에 포함된다.
`local_user_id`를 채우거나 기존 User의 대표 Person을 교체하지 않는다.
생성이 자동 선택·기본값 설정·핸들 활성화를 수행하지 않는다.

## 선택과 기본값

선택과 기본값 변경은 현재 User 본인에게만 허용한다. 읽을 때마다 Account 접근을
재검증하며 오래된 preference를 읽기 과정에서 수정하지 않는다.
SQL 첫 번째 Account를 임의로 선택하지 않는다.
기본값은 `workspace`, `contacts` 같은 로컬 purpose별 독립 usermeta다.
기능 capability 검증을 아직 하지 않으므로 JMAP `primaryAccounts`로 직접 내보내면 안 된다.

기존 `acting_actor()`도 명시적으로 선택한 작업 공간에 접근할 수 있지만 발행할 수
없는 경우 null을 반환한다. 다른 Person으로 조용히 발행하는 것을 방지한다.
접근 회수·삭제된 선택에 대한 기존 개인 Actor fallback은 유지한다.
기존 발행 getter와 새 workspace 기본값 해석은 아직 완전 통합하지 않았다.
기존 switcher UI는 발행 가능한 Actor만 제공하며 새 workspace UI는 없다.
새 선택 변경 hook은 `axismundi_actors_workspace_changed`이며 기존 발행 전용 hook을
동시에 발생시키지 않는다. 소비자 전환과 두 hook의 최종 통합 여부는 후속 검토 대상이다.

## 검증

개발용 실제 WordPress 테스트:

```sh
wp eval-file wp-content/plugins/axismundi-actors/tests/audit-account-operations.php
wp eval-file wp-content/plugins/axismundi-actors/tests/audit-acting-actor.php
```

새 operations audit는 임시 User/Actor를 만들고 finally에서 fixture만 제거한다.
테스트는 생성 gate, 권한, 다중 Person, 다섯 타입, 초기 owner, 선택·기본값 격리,
접근 회수, 읽기 무변경, 발행 대체 방지를 검사한다.

## Opus 인계 / 미완료

B 전체가 아니라 서버 하위 슬라이스다. 아래 검토 전 생성 UI/REST를 열지 않는다.

- User 삭제 시 추가 개인 Account의 보존·양도·폐기 정책.
- 개인 귀속 이전, 마지막 운영 owner 보호 및 생성 한도.
- 타입별 personal 기본값과 생성 권한(`edit_posts`)의 제품 정책 승인.
- 새 작업 공간 선택을 사용하는 기존 UI와 발행 소비자 전환.
- Actors feature 할당과 JMAP capability provider를 연결하는 C 슬라이스.
- 별도 F 슬라이스: Actor별 Inbox 멤버십·URI 호환·서명키 및 수신 전송 이관.

이번 변경은 추가 Actor의 완전한 연합 지원을 의미하지 않는다.
