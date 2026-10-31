# Legacy Install Fixtures

`audit-addresses.php`의 백필과 `audit-identity-relations.php`의 v10b 백필은 현재 설치의
버전 옵션만 낮춰서는 재현되지 않는다. `install()`은 통합 Actor 본체가 있으면 이전 경로를
건너뛴다. 실제 설치의 테이블이나 마커를 되돌리는 대신 `legacy-install-fixtures.php`가
무작위 접두사의 최소 pre-body 스키마를 만든다.

- 임시 identities·actors에 입력을 넣은 뒤 실제 `axismundi_actors_install()`을 호출한다.
- 옵션 테이블과 프로세스 로컬 객체 캐시도 별도로 사용한다. 외부 객체 캐시 환경에서는
  조용히 공유 캐시를 사용하지 않고 명시적으로 실패한다.
- `finally`에서 원래 DB 접두사·options 포인터·캐시·FK 검사 설정을 복구하고 임시 테이블을
  제거한다. 실제 Actor 옵션 행이 그대로인지도 비교한다.
- 현재 데이터에 백필을 재적용하는 제품 변경이나 과거 버전 부팅은 하지 않는다.

일반 등록은 전체 주소 행 수가 아니라 해당 Actor의 `local_handle/primary` 행을 골라
정확히 하나인지 검사한다. 행 순서에 의존하지 않고 정상적인 `acct` 행을 허용한다.

```sh
python tools/wp-env/run_audits.py run --audit audit-addresses.php --audit audit-identity-relations.php
python tools/wp-env/run_audits.py run --plugin axismundi-actors
```

두 대상 감사의 정상 결과는 각각 5/5와 10/10이다. 전체 목록의 다른 실패를 숨기거나
이 두 감사의 통과를 전체 플러그인 통과로 해석하지 않는다.

## Negative Controls

다음은 각각 exit 1이어야 하는 음성 대조군이다. 러너의 일반 전수 실행에서는 mutation
파일이 제외된다. 제품 파일을 수정하는 대신 해당 CLI 프로세스의 query 필터로 INSERT만
억제하므로 중단되더라도 디스크에 변이된 제품 코드가 남지 않는다.

```sh
docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-actors/tests/audit-addresses-mutations.php
docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-actors/tests/audit-identity-relations-mutations.php
```

첫 대조군은 local_handle INSERT를 억제한다. `register_handle records a primary
local_handle address`와 `install() backfills a primary address for a pre-existing handle`이
실패해야 한다. acct 동기화는 그대로 실행되므로 acct 행만 보고 통과하는 약한 단언을 잡는다.

둘째는 임시 접두사에서만 identity relation INSERT를 억제한다. 일반 원격 upsert 단언은
통과하고 `v10b upgrade backfills claims from existing remote payload snapshots`만 실패해야 한다.
