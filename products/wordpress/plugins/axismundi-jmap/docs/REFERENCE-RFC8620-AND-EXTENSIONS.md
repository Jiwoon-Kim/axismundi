# JMAP Core And Extension Review

검토일: 2026-10-22. 규범적 근거는 RFC, IANA는 등록 이름의 근거,
Crash Course와 Server/Client Guide는 구현 참고로 구분한다.

## Core First

[RFC 8620](https://jmap.io/spec/rfc8620/) 기준으로 현재 구현한 것은 메서드 등록,
요청 검증과 순차 실행, 오류 분리, 결과 참조, 생성 ID 컨텍스트, Core/echo다.
JSON 객체와 배열을 구분하고, 요청 전체를 검증한 뒤 실행한다.
등록은 엔진 생성 시 동결한다. 알 수 없거나 요청하지 않은 capability의 메서드는 실행하지 않는다.
Account 처리기는 현재 User에 대해 매 호출 다시 해소한다.

생성 ID는 provider가 명시적으로 등록한다. 텍스트 안의 `#`를 전역 치환하지 않는다.
쓰기 provider의 원자성과 타입별 검증은 아직 구현하지 않았다. 예상하지 못한 예외를
무조건 serverFail로 바꾸지 않는다. 실패 시 변경이 없었다는 보장은 트랜잭션 구현이 필요하다.

공개 HTTP/Session, 엄격한 I-JSON 입력 검사, 동시 요청 제한, 복수·암시적 메서드 응답,
표준 CRUD/query/changes, Blob/copy, 바이너리 전송, Push는 남아 있다.
현재 PHP JSON 디코더는 중복 키와 모든 I-JSON 수치·문자 제약을 검사하지 못한다.
따라서 공개 endpoint·발견 경로를 등록하지 않고, Core 지원 완료를 광고하지 않는다.
Request/Response용 DB 테이블은 없다.

## Extensions

| RFC | 판단 | 이번 단계 |
| --- | --- | --- |
| [9404 Blob](https://www.rfc-editor.org/rfc/rfc9404.html) | 업로드·조회·역참조 확장. 기본 HTTP 바이너리 전송과 Blob/copy는 이미 8620 영역 | 검토만, capability 미등록 |
| [9670 Sharing](https://www.rfc-editor.org/rfc/rfc9670.html) | Principal 및 공유 계약. 도메인별 권한을 대신 결정하지 않음 | managed Actor 관계를 재사용하되 자동 Principal 변환은 보류 |
| [8621 Mail](https://www.rfc-editor.org/rfc/rfc8621.html) | Mailbox·Email·발송 등의 메일 모델 | Contacts/Core 의존성으로 도입하지 않음 |
| [8887 WebSocket](https://www.rfc-editor.org/rfc/rfc8887.html) | JMAP 전송 바인딩. 프레임 상관 ID·연결 인증·상태 통지 필요 | 요청 엔진은 전송과 분리, 서버·URL 광고는 보류 |

RFC Editor는 9404·9670을 8620 갱신 문서로 연결한다. 갱신 관계를 검토하는 것과
해당 capability를 지원한다고 선언하는 것은 구분한다.

### Binary And WordPress

WP Media REST 업로드를 JMAP uploadUrl로 그대로 내보내지 않는다. Account별 인가,
임시 Blob 수명, 원본 바이트 보존, 비공개 다운로드, 참조와 과금 정책을 먼저 정해야 한다.
JMAP의 임의 바이너리를 전부 공개 WP Attachment로 생성하는 방식은 기본값으로 삼지 않는다.
미디어 처리 함수 재사용 여부는 향후 WP Core·Media Library 코드 조사 후 결정한다.

### Sharing And Social Transport

Account는 로컬 Actor 단위, 인증 User는 WP User라는 프로젝트 정책을 유지한다.
Principal은 공유 권한을 표현하는 역할이므로 Actor 타입과 기계적으로 동일시하지 않는다.
RFC 8887의 WebSocket은 ActivityPub 연합 프로토콜을 대체하지 않는다. Social 화면에도
사용하려면 해당 로컬 데이터의 JMAP 모델과 변경 통지가 먼저 있어야 한다.
PHP 요청 안에 장기 연결 서버를 추가하지 않으며 배포·프록시·인증 만료 정책을 별도로 검토한다.

## Guidance And Registry

- [IANA JMAP registry](https://www.iana.org/assignments/jmap): 등록된 capability·오류·타입 이름의 기준.
  로컬 코드의 callable registry와 별개이며 요청 때 외부 등록부를 가져오지 않는다.
- [Crash Course](https://jmap.io/crash-course/index.html): 배치와 결과 참조 이해용, 준수 근거를 대체하지 않는다.
- [Guides](https://jmap.io/guides/): 아래 구현 자료의 목차.
- [Server Guide](https://jmap.io/server/): Mail 중심 테이블과 modseq 권고다.
  Contacts 스키마로 복제하지 않고 Account·타입별 상태 관리의 참고로 사용한다.
- [Client Guide](https://jmap.io/client/): 초기 조회·증분 동기화·캐시 운용 참고다.
  예제의 단일 기본 Account 가정은 이 프로젝트의 다중 Actor 정책에 적용하지 않는다.
- [RFC Editor status](https://www.rfc-editor.org/info/rfc8620/): 갱신 문서·errata 확인 출발점.

## Next Slice

1. 공개 HTTP 전 엄격한 입력·인증·제한 처리와 Session 계약을 완성한다.
2. Contacts 원본 저장 형식과 9610 provider, 모든 쓰기 경로의 변경 추적을 결정한다.
3. 바이너리·Push의 Core 요구를 채운 뒤 지원 capability를 노출한다.

원격 Account 동기화 클라이언트, JMAP Mail 백엔드, Calendar, WebFinger 재배치는 현재 구현하지 않았다.

## Verification

2026-10-22 실행 결과:

- `php products/wordpress/plugins/axismundi-jmap/tests/engine.php`: 64개 검사 통과.
- 실제 wp-env CLI 컨테이너의 `/tmp/axismundi-jmap`에 개발 코드를 복사한 뒤
  `wp eval-file /tmp/axismundi-jmap/tests/audit-accounts.php --path=/var/www/html`:
  13개 검사 통과. 사이트에 플러그인을 설치·활성화하지 않았고 테스트 fixture를 정리했다.
- 플러그인 내 PHP 9개 파일 `php -l`: 문법 오류 없음.
- `git diff --check`: 통과.

검증하지 않은 것: 공개 HTTP 인증·Session·실제 JMAP 클라이언트 상호운용,
엄격한 I-JSON, 장기 연결, Contacts 쓰기·증분 동기화. 테스트는 CI에 연결되지 않았다.
