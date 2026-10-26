# Principal API 이행 경계

2026-10-26. canonical 정책과 소유 범위의 production 소비자는 typed principal로 교체했다.
모든 플러그인의 옛 공개 함수 삭제나 제외된 managed 화면의 디자인 완료 선언은 아니다.

## 공개 정책 API

- `principal_can_manage(account, kind, id, minimum)`는 정확한 직접 관계만 읽는다.
- `account_principals(account)`와 `accounts_for_principal(kind, id)`는 활성 관계를 열거한다.
- 역할 어휘, 순위, `principal_owner_count(account, kind)`도 `principals.php`가 소유한다.
- `create_principal_account(args, kind, id)`는 초기 live WP User owner를 요구한다.
  생성 트랜잭션 안의 `seed_account_owner`는 초기 직접 로그인 경계이며 기존 grant를 갱신하지 않는다.
- `set_principal_role(account, kind, id, role)`는 신뢰된 로컬 호출자의 정책 쓰기다.
  HTTP 인증 API가 아니며 호출자가 운영자 권한을 별도로 확인해야 한다. NULL role은 철회다.
- `promote_user_principal(account, user, selected_account)`는 명시한 Account만 추가한다.
  User 관계를 삭제하지 않으며 기존 Actor 역할이 다르면 충돌을 반환한다.

`wp_user`와 `local_account`는 공존한다. 역할 합산 및 재귀 접근은 없다.
원격 grant 생성/갱신은 닫혀 있다. 예외적으로 trusted local writer의 NULL role은
존재하지 않거나 disabled/tombstone인 remote 주체의 정확한 행도 철회할 수 있다.
remote 인증이나 HTTP 경로를 여는 것이 아니며 호출자가 운영자 권한을 별도로 검증한다.
마지막 **살아 있는 직접 WP User owner** 보호는 로컬/원격 owner나 삭제된 User 행으로 대체되지 않는다.
삭제된 User 행은 그대로 두되 복구 가능한 owner 수에는 포함하지 않는다.
쓰기 잠금은 typed writer 및 그 writer를 사용하는 기존 add/remove adapter 사이에서 공유한다.
기존 `write_manager_row`는 더 이상 lifecycle에서 호출하지 않고 안전한 typed writer로 위임한다.
초기 생성은 canonical 생성 API 및 seed 경계가 처리한다.

## is_managed 분류

| 위치 | 판정 의미와 처리 |
|---|---|
| Notifications deliveries | mailbox 접근 및 사람 대상 fan-out. typed 직접 User 관계로 이전. Actor 관계에서 User를 재귀 유도하지 않음 |
| Actors admin: can_manage, 선택 화면, visibility, delegation | 직접 WP User kind로 typed 조회/쓰기. managed 화면 선택·쓰기에는 기존 scope 필터를 명시적으로 유지. 레이아웃 변경 없음 |
| Actors admin: management_back_url | 기존 편집 화면으로 돌아가는 URL 선택. principal 보유로 치환하지 않음 |
| Actors routing: can_preview | 비-site local Account의 직접 WP User role 사용. 기존 User/site override 경계 유지 |
| Actors managed-groups: can_manage, list_manageable | 범위 밖 Calendar의 과거 API 경계. typed 조회로 위임하되 scope 필터 유지. 소유 범위 production 호출 없음 |
| Actors managed-groups: add/remove/roster/count/role aliases | 과거 감사 호환용 이름만 남음. 소유 범위 production 호출 없음. canonical 구현은 principals.php |
| Actors managed-groups: list_all, claim | 로컬 managed Group 복구 제품 정책. 모든 principal Account로 넓히지 않음 |
| Forum admin/repository/topics/inbound-topics/memberships | 로컬 managed Group이라는 커뮤니티 제품 정책. 개인 Account도 principal을 가지므로 단순 보유 치환은 오동작 |
| Activities local-social | 이 사이트가 처리하는 Group Follow 대상 정책. Account 권한으로 치환하지 않음; 파일 변경 없음 |
| 폐기 Note | 제외; 변경 없음 |

## 남은 경계

managed 생성/Group 복구/Group 디렉터리는 실제 제품 의미가 있어 유지한다.
생성은 canonical API를 호출하고 복구는 admin capability 확인 후 명시적 WP User grant를 쓴다.
`wp_user` discriminator는
인증된 로그인, 개인 귀속, 초기 owner 생성, 마지막 직접 owner 집계, 사람 대상 알림에서 필요하다.
따라서 production에서 그 문자열을 전부 없애는 것은 완료 기준으로 사용할 수 없다.

Forum의 공개 moderator 컬렉션은 explicit `local_account` principal만 Actor로 투영한다.
User grant에서 `get_for_user`로 Actor를 선택하지 않는다. 기존 WP User grant는 보존되지만
그 자체로 공개 moderator Actor를 만들어 주지 않는다. 공개 Person 제한은 Forum 제품 정책이다.
Forum repository/topics/moderators/admin 및 bootstrap는 옛 managed 권한 함수를 호출하지 않는다.
moderator 승격/철회는 명시한 Actor의 `local_account` 행만 쓴다. 그 Actor의 WP User 접근은
추론하거나 생성하지 않는다. 로그인에서 moderation activity 발신 Actor를 고르는 기존 경계는
별도 Actor-axis 작업이며 grant 승격을 수행하지 않는다. Activity type 정책은 바꾸지 않았다.
제외된 managed 화면은 여전히 WP User 로그인 편집 화면이며 typed multi-kind 화면 디자인은 미완료다.

전역 공개 함수 삭제는 범위 밖 Calendar 때문에 아직 완료가 아니다:
`calendar-acl.php`는 `managed_actor_can_manage`, `calendar-list.php`와 `admin-calendars.php`는
`list_manageable_actors`를 호출한다. Calendar 파일은 이 작업의 수정 권한 밖이므로 변경하지 않았다.

typed 목록의 inactive target 및 deleted/disabled/tombstone source 제외는 의도한 강화다.
옛 raw `group_managers`는 삭제된 User 행을 보여 주었고 옛 manageable SQL은 inactive target을
걸러내지 않았다. 새 active roster/list는 이를 제외하지만 저장 행을 정리하지 않는다.

schema/scope 변경, remote grant HTTP/UI/signature, C3 예약 발행은 이 변경에 포함하지 않는다.
집중 행동 검사는 `wp eval-file wp-content/plugins/axismundi-actors/tests/audit-principal-behavior.php`다.

## 이번 검증

최종 정상 실행: principal 행동 58건, Forum moderation 32/32 통과. 변경 PHP lint 및 diff check 통과.
Notifications deliveries 영향 감사도 14/14 통과했다.
`audit-principal-mutations.php`는 `AX_PRINCIPAL_MUTATION=kind`와 `deleted-owner`를 받아
프로세스 메모리 안에서만 함수를 복제·변이한다. 전자는 같은 숫자의 Actor grant가 User에게
전파되는 검사, 후자는 삭제된 owner 행이 마지막 live owner 강등을 허용하는 검사에서 잡혔다.
두 변이 후 정상 행동 검사를 다시 통과했으며 production 파일은 변이하지 않았다.
schema 변경 검사는 실행하지 않았다. Forum 행동 감사의 install 호출도 readiness 확인으로 교체했다.
직접 WP User 관계 1,022쌍의 역할을 consumer 수정 전후로 수집했고 차이는 0건이다.
기존 resolver와 exact-kind SQL은 보존했다. 역할 어휘 조회 이름은 canonical API로 옮겼다.
`snapshot-principal-verdicts.php`는 역할/access/actas/personal/managed 경계를 읽기 전용으로 덤프한다.
제공된 baseline의 SHA-256은 일치했다. `compare-principal-baseline.php`로 전체
35,183 pair verdict와 151 User accounts/manageable 목록 행을 비교했고 차이는 각각 0건,
해석하지 못한 행도 0건이다. 이 baseline에 나오지 않은 inactive/dead 목록 차이는
집중 fixture 검사로 의도한 제외를 확인한다. 전체 비교는 baseline을 STDIN으로 전달한다.
Forum 감사는 옛 암묵적 fixture에서 9/28 통과로 영향을 확인한 뒤, 명시적으로 owner Actor를
선택하는 fixture와 Actor manager가 User에게 위임 권한을 주지 않는 검사를 추가해 32/32 통과했다.
삭제된 User의 관계나 거부 판정을 정리하지 않았다.
