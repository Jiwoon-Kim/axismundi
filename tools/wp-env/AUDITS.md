# WordPress Audit Runner

2026-10-30. 실행과 판정만 담당하며 감사 내부의 모델 단언은 변경하지 않는다.

## Commands

저장소 루트에서 실행한다. Python 표준 라이브러리와 Docker CLI가 필요하다.
기본값은 실행 중인 wp-env-axismundi-*-cli-1이 하나일 때만 자동 선택한다.
여러 환경이 있으면 --container를 명시한다.
같은 컨테이너의 별도 DB는 --database로 명시한다. WORDPRESS_DB_NAME 주입 후 실제
SELECT DATABASE() 결과가 지정값과 일치해야 한다. 스냅샷에도 DB명을 기록하며 복원 전후
컨테이너와 DB를 함께 대조한다. DB명 없는 이전 스냅샷은 자동 복원하지 않는다.
테이블 접두사는 wp config get table_prefix --type=variable로 읽는다. wp db prefix처럼
WordPress를 부팅할 수 있는 경로를 스냅샷 전제조건 검사에 사용하지 않는다.
격리 CLI에서 HTTP origin 감사는 거부한다. HTTP 서버까지 격리된 것은 아니기 때문이다.

```powershell
python tools/wp-env/run_audits.py list
python tools/wp-env/run_audits.py run --plugin axismundi-contacts
python tools/wp-env/run_audits.py run --audit audit-profile-separation.php --db-mode before-after
python -m unittest discover -s tools/wp-env -p test_run_audits.py -v
```

list는 모든 플러그인의 tests/*.php를 열거한다. 실행 대상은 audit-*.php와 명시한
standalone 테스트다. mutation driver와 fixture helper는 기본 스윕에서 제외하며 성공으로 세지 않는다.
다른 PHP 파일을 자동으로 실행하지 않는다. manifest의 대상·환경 선언을 리뷰한다.
--audit는 파일명 또는 상대 경로 glob, --plugin은 플러그인 디렉터리명이다.
제외된 실행 가능 감사를 정확한 파일명 또는 상대 경로로 --audit에 지정하면 실행한다.
넓은 glob은 제외를 덮지 않고, fixture helper처럼 실행 대상으로 분류되지 않은 PHP는
정확히 지정해도 실행하지 않는다. 변이 감사의 실패는 FAIL로 보존하며 자동으로 PASS로 뒤집지 않는다.
전체 대상은 run만으로 실행하지만 실제 DB를 변경할 수 있으므로 개발 환경 전용이다.

실행 제한은 명시한 --timeout이 최우선이며, 생략하면 audits.json의 timeouts 선언,
선언도 없으면 300초를 사용한다. 하위 감사를 두 라운드 반복하는 principal-fixture-cleanup은
900초를 선언하고 스윕에 유지한다. list와 results.json에 timeout_seconds를 기록한다.

결과는 시스템 임시 디렉터리에 저장한다. --output으로 checkout 밖을 지정할 수 있다.
전체 stdout/stderr 로그·results.json·results.md를 보존한다. DB dump와 로그는 개인정보·
비밀키를 포함할 수 있으므로 공개하거나 커밋하지 않는다.

## Verdict

- 종료 코드가 0이 아니거나, 치명적 오류·FAIL 행·실패 수가 있는 집계가 있으면 FAIL이다.
- [PASS] 체크 결과의 설명은 오류 탐색에서 제외한다. 오류 처리 감사를 설명하는
  uncaught/Fatal error 같은 단어를 금지하지 않는다. 실제 PHP·WP-CLI 오류 행과 [FAIL]은 탐지한다.
- 개별 [PASS]는 집계가 아니다. 인식한 최종 집계가 없으면 INCOMPLETE다.
- 0검사는 성공이 아니다. 환경 부족은 CONFIG_ERROR, 제한 시간 초과는 TIMEOUT이다.
- GNU timeout의 124는 TIMEOUT이다. BusyBox의 TERM(143) 및 KILL(137)은 실제 경과 시간이
  해당 실행 제한 이상일 때 TIMEOUT으로 분류한다. 제한 이전의 신호 종료나 근거 없는 143은
  FAIL로 남긴다. 모든 128+n을 타임아웃으로 일반화하지 않는다. 원래 종료 코드와 시간은 보존한다.
- 실행 전 판정기의 알려진 입력 sanity와 실제 JMAP engine.php 감사를 먼저 실행한다.
- 각 PHP는 컨테이너 내부 timeout으로 제한한다. 병렬 실행·git stash·자동 checkout을 하지 않는다.
- 종료 시 revision과 dirty/untracked 파일 내용 지문도 비교한다.
- 전체 PASS이며 코드가 그대로일 때만 종료 코드 0이다. 미인식 집계는 판정기를 검토해서
  지원한다. 실패 단언을 재해석해서 녹색으로 만들지 않는다.

HTTP origin은 audits.json이 감사별로 선언한다. AX_ADMIN_HTTP_ORIGIN과
AX_C2S_HTTP_ORIGIN은 대체하지 않는다. 외부 fixture가 필요한 URI 보존 감사 등은 별도
manifest에 환경변수와 컨테이너 내부 경로를 지정한다. 미설정은 CONFIG_ERROR로 남는다.
우연히 설정된 호스트 프로세스 환경에 기대지 않는다.

## Database Pairing

```powershell
python tools/wp-env/run_audits.py snapshot --scope plugins
python tools/wp-env/run_audits.py run --db-mode before-after --scope full --plugin axismundi-contacts
python tools/wp-env/run_audits.py run --state C:/TEMP/state/snapshot.json --audit audit-profile-upgrade.php
```

before-after는 실행 직전·직후 dump와 코드 지문·DB 스키마/마이그레이션 마커·컨테이너명·
dump SHA-256을 저장한다. 자동 복원하지 않으며, 전후 차이가 없다고 주장하지도 않는다.
기본 plugins 범위는 wp_ax_*와 wp_options다. options를 빼면 버전 마커와 시스템 Actor
binding이 돌아오지 않아 코드/DB 쌍을 복원할 수 없다.
현재 개발 DB에서는 이 범위도 약 94MB이므로 전체 dump보다 현저히 작다고 가정하지 않는다.
User·Post를 만드는 감사의 전체 상태를 복원해야 하면 full을 사용한다. plugins는 부분
복원점이지 WP Users/Posts까지 포함하는 전체 rollback이 아니다. 스냅샷 중에는 쓰기를 중단한다.

--state는 대응 코드 지문과 현 DB의 스키마·마커가 일치해야 실행한다.
코드만 stash/checkout한 상태나 다른 컨테이너의 스냅샷은 거부한다.
기계적인 쌍 검증이므로 처음부터 잘못된 코드/DB에서 만든 스냅샷까지 정당화하지는 못한다.
구버전 검증에는 변경 전에 확보한 올바른 스냅샷이 필요하다. 이행 후 dump에서 이행 전
상태를 복구할 수 있다고 주장하지 않는다.

명시적 복원:

```powershell
python tools/wp-env/run_audits.py restore --state C:/TEMP/state/snapshot.json --confirm-restore wp-env-axismundi-66f7e116-cli-1
```

대응 코드·컨테이너·dump checksum을 확인하고 현재 상태의 recovery dump를 먼저 만든다.
그 뒤 지정 범위의 현재 테이블을 삭제하고 import한 다음 쌍을 재검증한다.
코드 일치와 dump 검증은 복원 전제조건, DB 스키마·마커 일치는 import 후 사후조건이다.
따라서 대응 코드 지문이 있는 역사적 스냅샷도 복원할 수 있다. v14 수동 dump처럼
코드 지문이 없는 SQL 파일을 임의 메타데이터로 승격하지 않는다.
새 스키마에만 있던 테이블도 남기지 않는다. 실패하면 recovery dump와 로그를 보존한다.
해당 환경을 복원할 권한·승인을 받지 않았다면 이 명령을 실행하지 않는다.

복원 dump는 docker exec -i를 통해 wp db import -의 stdin으로 직접 전달한다.
docker cp가 root 소유 파일을 만들고 sticky /tmp에서 일반 exec 사용자가 삭제하지 못하는
문제를 피한다. snapshot export의 임시파일 삭제 실패는 경고로 남기며, 원래 오류를
덮어쓰거나 사후조건 검증을 건너뛰게 하지 않는다.

Docker는 shell 없이 argv로 호출하고 MSYS_NO_PATHCONV=1과 MSYS2_ARG_CONV_EXCL=*를
설정한다. /tmp가 Windows 경로로 변환되는 사고를 방지한다.

## Verification Scope

test_run_audits.py는 잘못된 HTTP 환경변수, 개별 PASS 오판, 선두 예외 누락,
집계 없음·0검사, 비정상 종료·timeout, 코드만 되돌린 쌍, DB 마커 불일치,
MSYS 인자 변환, dump 실패를 주입한다.
timeout은 GNU와 BusyBox 양쪽이 받는 -k 옵션을 사용한다.
DB 복원 가드는 모의 실행으로 검사한다. 별도 실제 왕복 검사도 아래처럼 제공하며,
어느 쪽도 개발 DB 전체를 덮어쓰지 않는다. 모의 검사와 실제 dump/import 검증을 구분한다.

명시적 실제 왕복 검사는 다음 명령을 사용한다. 고유한 ax_audit_roundtrip_* DB를 새로
만들어 작은 테스트 테이블만 넣고, 데이터·스키마·마커 변경 후 실제 runner 복원을 실행한다.
개발 DB는 읽어서 대상·마커가 유지되는지만 확인한다. 테스트가 만든 DB만 마지막에 삭제한다.
컨테이너를 재시작하거나 개발 테이블을 DROP하지 않는다. 테스트 계정에는 DB 생성 권한이 필요하다.

```powershell
python tools/wp-env/test_restore_integration.py --container wp-env-axismundi-66f7e116-cli-1
```

구형 v14 dump의 정확한 중간 코드 짝은 이 검사로 복원되지 않는다. 또한 DB 격리는
파일·네트워크까지 격리하는 실행 환경이 아니다. 과거 코드 감사는 별도 코드 경로도 필요하다.

2026-10-30 수정 검증: 판정·오류 주입 24검사, 임시 DB 실제 복원 7검사 통과.
실제 복원은 데이터 원복·추가 컬럼/테이블 제거·마이그레이션 마커·사후 쌍 검증과
개발 DB의 주요 마커 보존을 확인했다. 테스트 전용 DB는 종료 시 삭제했다.
기존 /tmp 백업들은 이 검사에서 변경하거나 삭제하지 않았다.
