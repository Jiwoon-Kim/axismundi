# Profile Audit Migration

2026-10-31 B 슬라이스. 과거 DB로 되돌리지 않고 현재 저장소에서 단언과 입력 경로를 이관했다.

## 실패 분류와 이관

기준선은 `audit-contacts` 335개 중 59개 실패, Actors `audit-admin` 28개 중 4개 실패였다.
Contacts 실패의 최초 입력 경로 분류는 다음과 같다. 개별 제품 결함 59개라는 뜻은 아니다.

| 경로 | 건수 | 조치 |
|---|---:|---|
| 프로필 ID를 ContactCard 함수에 전달 | 42 | 프로필은 명시적 profile API, 연락처 기능은 별도 ContactCard 픽스처 |
| 같은 행·개수·UI라는 옛 모델 | 5 | 독립 행과 수명, 별도 개수로 검사 |
| 프로필을 cards draft로 편집 | 5 | profiles draft와 해당 revision 사용 |
| 과거 마이그레이션 픽스처 | 7 | 현재 프로필 문서와 provenance_json에 과거 입력을 구성 |

이름·로마자·공개 동의 등의 실패를 새 정책으로 해결하지 않았다. 올바른 저장소로 입력과
조회를 옮긴 뒤 기존 단언이 통과했다. 프로필 ID를 ContactCard 삭제 목록에 넣던 정리 경로도
제거했다. 숫자 ID가 같다는 이유로 저장소를 추측하는 호환 함수는 만들지 않았다.

## 실제 운영 코드 수정

- identity 보정과 home service key 이관은 DB 15 이후 독립 프로필 저장소를 사용한다.
  분리 전 업그레이드는 기존 ContactCard 경로를 유지한다.
- 키 이동은 프로필 본문·보존된 provenance·공개 pointer를 같은 Contacts 트랜잭션에서 옮긴다.
  일반 ContactCard의 키 이동이 독립 프로필의 공개 pointer를 바꾸지 않는다.
- 고아 프로필 바인딩 판정은 독립 프로필의 ID와 owner_actor_id를 함께 조회한다.
  ContactCard 테이블과 조인하면 정상 바인딩을 고아로 오판할 수 있었다.
- Actor 관리자 판정은 확정된 `grant 없음 -> manage_options`, `grant 있음 -> read` 계약을
  적용한다. 기존 User activation 어댑터도 Actor가 있으면 같은 판정에 위임한다.
  원격·Tombstone 거부는 유지한다. 이는 관리자 프로필 판정 변경이며 JMAP 공유 권한을
  자동 부여하거나 principal grant를 생성하는 변경이 아니다.
- Accounts 화면 이전, 개인 Account의 principal 목록, 독립 프로필 삭제 수명을 단언하던
  관련 감사도 현재 모델로 이관했다. grant 작성 권한이나 Follow 정책을 새로 정하지 않았다.

## 검증 명령

```sh
python tools/wp-env/run_audits.py run --audit audit-contacts.php --audit audit-admin.php --audit audit-actor-axis.php --audit audit-actor-axis-callers.php --audit audit-managed-actors-surface.php --audit audit-remote-admin.php --audit audit-orphaned-owners.php --audit audit-profile-separation.php --audit audit-profile-upgrade.php --audit audit-follow-button.php --audit audit-principal-fixture-cleanup.php --timeout 600
```

관측된 핵심 결과: Contacts 335/335, admin 30/30, profile-separation 31/31,
profile-upgrade 3/3, actor-axis 46/46, principal-fixture-cleanup 24/24.
최종 위 명령은 exit 0이며 결과는 `C:/Users/thaum/AppData/Local/Temp/axismundi-audits-6_tf4nxv`에 있다.
전체 플러그인이 녹색이라는 뜻은 아니다.
전체 실행 로그: `C:/Users/thaum/AppData/Local/Temp/axismundi-audits-k3uo0opm`.

변이 두 개를 각각 적용해 실행한 후 원본 파일로 복구했다.

| 변이 | 감사 | 잡힌 단언 |
|---|---|---|
| profile_document를 get_card로 조회 | audit-profile-separation | Independent profile owns stable UID |
| 관리자의 무부여 기본 경로 제거 | audit-admin | an administrator may manage any actor |

## 별도 추적 항목

전체 실행에서 B 이관 외의 실패도 관측했다. 이전 기준선과 쌍으로 비교하지 않았으므로 전부를
선행 실패 또는 신규 회귀라고 단정하지 않는다.

- Actors: account-header, addresses, follow-vocabulary, identity-relations, managed-groups,
  user-deletion, webfinger.
- Contacts: account-access, card-writes의 SAVEPOINT fault injection.
- local-endpoints는 자체 7/7 출력이 있으나 러너가 요약 형식을 인식하지 못해 INCOMPLETE.
- actor-uri-preservation은 AX_ACTOR_URI_BASELINE이 없어 CONFIG_ERROR. 새 덤프로 과거 기준선을
  대신 만들지 않았다.
- principal-fixture-cleanup은 첫 전체 실행 당시 actor-axis의 옛 관리자 단언에서 중단됐으나
  해당 이관 후 재실행에서 24개를 통과했다. 보존 대상은 기존 principal 행이다.

데이터 관측: 기존 프로필 문서 5행 중 2행(ID 301, 7766)에 현재 바인딩이 없다.
`/tmp/ax-review-snapshot.sql`에는 두 바인딩이 있다. 수정 전 고아 판정이 이 상태를 만들 수
있지만 삭제 시점은 확정하지 못했다. 바인딩에는 공개 설정도 있으므로 임의로 재생성하거나
오래된 공개 설정을 복원하지 않았다. 오너는 admin2 프로필만 있으면 된다고 답했다.
admin2(Actor 199)의 기존 문서 301과 UID를 유지하고, 공유 꺼짐·published_json=NULL인 새 기본
바인딩만 연결했다. 백업의 공개 동의는 복원하지 않았으며 JSContact 응답을 확인했다.
다른 문서 7766의 바인딩은 만들지 않았다.

DB 전체 복원, 과거 버전 부팅, Follow 설정 변경, JSContact 전체 어휘 레지스트리 구현은
이번 슬라이스에서 하지 않았다.
