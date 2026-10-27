# V 어휘 축소 구현

2026-10-27. `BRIEF-VOCABULARY-REDUCTION.md`의 V1과 V3 읽기 경로 전환.
기존 패키징 버전 변경은 보존했다. 커밋, ZIP 재생성, 스테이징 변경은 하지 않는다.

## 이번에 확정한 범위

소유자 정정: **`internal`은 Actor뿐 아니라 identity 레지스트리 전체에서 제거한다.**
Object의 비공개를 유지하려고 이 상태를 남기는 것은 잘못이다. WordPress 글은
`post_status = publish` 및 소스의 읽기 조건이 공개 여부를 결정한다. identity가
존재한다는 것은 읽기 권한이나 Create 발행 사실이 아니다.

폴더는 User의 것이며 Actor 전용 Collection과 다르다. 기존 폴더 AP 투영은 퇴역
대상으로 기록한다. 이번 변경은 폴더 공개 설정과 identity 상태의 동기화를 끊지만,
기존 URL 자체를 없애거나 Actor Collection 모델을 새로 만들지는 않는다.

## V1 구현

- Actor 생성, 일반 identity 등록의 기본값은 `public`. `internal` 입력은 거부한다.
- 상태 전이는 `public | disabled | tombstone`만 허용한다. tombstone의 복귀 금지는 유지한다.
- OP Object, Activities Question, 기존 폴더 identity 등록에서도 `internal`을 쓰지 않는다.
- 폴더 tier/gate/password가 identity 상태를 바꾸지 않는다. 소스의 기존 읽기 판정은 유지한다.
- Actor 관리 화면은 비공개 모드 대신 명시적인 Disable/Enable을 제공한다. disabled는
  선택·행동·서명이 불가능하지만, 살아 있는 직접 User principal은 명시적 identity URL에서
  프로필을 관리하고 다시 활성화할 수 있다. 관리자라는 이유만으로 권한을 부여하지 않는다.
- 핸들 없는 Actor는 public 상태여도 아직 공개 프로필이 아니다. 서명 스냅샷도 거부해서
  검증 키를 공개할 수 없는 주체가 서명하는 것을 막는다. 실제 DB 상태도 public으로 재검사한다.

`includes/vocabulary.php`의 마이그레이션은 기본 100행 단위이며, 정상 `set_status()`를
호출해 WebFinger/Forum 리스너를 우회하지 않는다. 완료 마커는
`ax_actors_vocabulary_version = 2`. 실패·미완료 배치는 다음 init에서 재시도한다.
완료된 전이는 재실행 시 다시 발화하지 않는다. 행 잠금 안에서 이전 상태가 internal인지
재검사하므로 동시에 비활성화된 identity를 다시 공개하지 않는다.
UUID·URI·귀속·Create를 재생성하지 않는다.

개발 DB를 변경하기 전에 CLI 컨테이너에 `/tmp/ax-actors-before-vocabulary-20261027.sql`로
백업했다. 백업은 패키지에 넣지 않는다. 개발 DB에서는 전역 internal 0을 확인했다.
원래 1,309라는 수를 Actor 행 수로 간주하지 않는다. Object identity도 같은 테이블에 있었다.
이 작업에서 기존 픽스처/묘비를 일괄 삭제하지 않았다.

## V3 읽기 경로 전환

- 접근·개인 귀속 판정의 site scope 가드를 제거했다. principal과 status가 판정한다.
- Group 디렉터리, Forum/Activities Group 판정, Calendar 백필과 관리 목록은 scope 대신
  principal 관계를 읽는다. 관계 존재 자체가 요청자의 권한을 부여하지는 않는다.
- 인스턴스 용도는 `ax_actors_instance_identity_id` 설정으로 가리킨다. 기존 단일 site 행을
  최초 한 번 이관하며, 둘 이상이면 추측하지 않고 실패한다. Application 타입과 혼동하지 않는다.
- `get_scope()`와 `is_managed()`는 구 클라이언트를 위한 파생 호환 어댑터다. 현재 production
  소비자는 더 이상 이를 권한에 쓰지 않는다. 폐기 예정 Note의 옛 표시/분기 호환은 남겼다.
- 프로필 폼의 반환 URL은 scope와 무관하게 identity를 고정한다.

**물리 컬럼 제거는 아직 하지 않았다.** 브리프의 순서는 읽기 전환 → 검사 → 별도 커밋의
컬럼/인덱스 삭제다. `actor_scope`, `scope_type`, 구 설치용 소유·언어 백필, 구 생성 인자의
호환 쓰기는 그 다음 변경에서 걷어낸다. 이번 diff와 함께 DROP했다고 보고하지 않는다.

## V2

`ensure_for_user()` 자동 생성 호출의 존재 자체는 별도 소유자 결정 항목이므로 바꾸지 않았다.
생성 상태는 다른 생성 경로와 같이 public이지만, 핸들 없는 상태에서 광고·서명은 열리지 않는다.
활성화 seed를 수동으로 실행하지 않았다.

## 검증과 한계

- `audit-vocabulary.php`: 32개 검사. public 기본값, 전역 internal 거부, 비공개 WP 글 5상태,
  서명/비활성화/복구, scope 무시, 인스턴스 설정, 재실행, 정상 리스너를 검사한다.
  새 감사는 **identities·actors·managers의 전체 행 내용**을 전후 비교한다.
- `audit-vocabulary-mutations.php`: 생성 기본값, internal 허용, site scope 가드 복원,
  비활성 서명 허용, principal 검사 무력화를 메모리 내 함수 복제로 검증한다.
- 축 감사 47개, 폴더 감사 17개, identity registry 14개, 소유 관계 29개,
  principal 동작 58개, Calendar 발행 11개를 영향 범위로 선정했다.
- 기존 principal 누수 회귀 목록에 새 감사를 추가했다. 그 회귀의 다른 감사들은 여전히
  managers만 전후 비교한다. 모든 옛 감사의 identities/actors 누수가 해결됐다는 뜻은 아니다.
- 기존 ownership 감사의 managed-only 기대값은 retired scope 계약이라 principal 기준으로
  갱신했다. 초기 scope 변이는 wrapper만 호출하는 감사 때문에 살아남았고, typed 정책 함수를
  직접 단언하도록 수정 후 검출했다. 이 초기 PASS를 검출 증거로 세지 않는다.
- 브라우저로 Disable/Enable UI를 실행한 검증은 아직 하지 않았다. 프로필 대상·권한·복구는
  CLI 통합 감사로 검증한다. 패키징 당시 ZIP은 이 V 변경 이전 것이며 그대로 유지한다.

최종 결과: V 감사 **32/32**, 변이 **5종 검출**, workspace 단위 검사 **15/15**,
principal 누수 회귀 **20/20**(10감사 × 2라운드, 기존 1,038행 보존).
변경·신규 PHP **48개 문법 검사** 및 `git diff --check` 통과.
새 감사 자신의 identities·actors·managers 전후 비교도 통과했다.

실행 중 기존 audience/notification 감사에서 internal fixture가 드러나 갱신했다.
그 외 feed/outbox/프로필/관리 화면의 옛 status 입력도 새 계약으로 수정했지만,
그 감사 전부를 실행했다는 뜻은 아니다. 위에 적은 집중 감사와 회귀 목록만 실행했다.
기존 감사 전체의 누수 정리나 전체 브라우저 회귀를 완료했다고 주장하지 않는다.
