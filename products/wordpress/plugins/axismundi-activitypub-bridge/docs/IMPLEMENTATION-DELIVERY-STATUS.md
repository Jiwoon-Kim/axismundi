# Inbox Delivery Status

2026-11-07. Bridge 배송 상태의 로컬 구현. 실제 외부 발신, 스테이징 변경,
기존 대기 작업 재예약, 보존 기간 변경은 하지 않았다.

## 경계

- Activity 원장은 변경하지 않는다. Bridge의 기존 spool에 nullable
  delivery_status_json을 추가했다. 스키마 버전은 2이며 dbDelta 후 컬럼을 재검사한다.
- 기존 inboxes_json / pending_inboxes_json, 작업 상태 및 재시도 정책을 유지한다.
- inbox별 상태는 accepted / queued / failed / unknown.
- HTTP 2xx는 accepted다. 원격 게시, 표시, 읽음을 증명하지 않는다.
- httpStatus, inbox별 관측 시도 횟수, UTC 관측 시각, WP_Error 코드만 새 맵에 저장한다.
  응답 본문과 에러 메시지 원문은 새 맵에 복사하지 않는다.
  기존 last_error 진단은 그대로 유지한다.
- 실행 중 각 HTTP 결과를 lock token 조건으로 기록한다.
  이전 배치의 영구실패가 마지막 성공 배치에 가려지는 문제도 함께 막는다.
- 재시도 한도가 소진된 일시 오류는 unknown으로 남긴다.
  기존 종료 행의 개별 수신 결과는 소급 추정하지 않는다.
- 수동 전체 재시도는 이 맵을 비우고 기존의 전체 봉투 재시도 의미를 유지한다.
- 서명 키 준비 실패 등 HTTP 이전 실패는 개별 HTTP 결과를 만들어내지 않는다.

## 읽기와 권한

axismundi_activitypub_bridge_delivery_report(id)는 Bridge 내부의 인가된 읽기 함수다.
manage_options 또는 발신 Actor로 행동할 권한이 있는 요청자만 대상 inbox와 상태를 읽는다.
Activity 문서, 공개 Outbox, 로그 원장에 수신자별 전송 상태를 추가하지 않는다.
REST 엔드포인트와 컴포저/Activity Log UI 연결은 이번에 추가하지 않았다.
내부 get_delivery / delivery_jobs는 기존처럼 호출자가 권한을 검사하는 저장소 함수다.

## 읽기 전용 진단

변경 전 wp-env에서:

| 상태 | 건수 |
| --- | ---: |
| failed | 16907 |
| queued | 1544 |
| delivered | 24 |

대기 중 due/lease 조건을 만족한 행 1544, Bridge worker의 예약 이벤트 0개.
원인 후보를 스케줄 부재로 좁히지만, 이벤트가 사라진 이유나 과거 실행은 증명하지 않는다.
이 값은 localhost 픽스처이며 스테이징 운영 진단이 아니다.
워커를 기존 행에 실행하거나 일괄 예약 복구하지 않았다.

## 검증

wp-env CLI에서 tests/audit-outbound-delivery.php 실행.
pre_http_request 모킹으로 원격 inbox POST를 대체한다.

- 기존 resolver의 blind 수신자 해소와 발신 payload 제거.
- Public/자기 액터 제외, followers 페이지 확장.
- 서로 다른 호스트의 410 / 202, batch size 1:
  앞 배치 실패와 뒤 배치 accepted가 둘 다 남고 전체 상태는 failed.
- 익명 report 접근 거부, 발신 Actor 관리자에게 부분 결과 제공.
- 202 / 503 이후 재시도 202:
  accepted 대상은 재전송하지 않고 시도 횟수는 1 / 2.

테스트 픽스처의 예약 이벤트는 기존 감사의 finally 정리 경로로 제거한다.

## 별도 결정

수신 inbox별 독립 백오프, 보존 기간, 자동 큐 복구, 보내기 취소,
클라이언트 상태 화면은 이번 범위가 아니다. sharedInbox 접수 한 건은
그 inbox 뒤의 각 Actor가 내용을 처리했다는 증거가 아니다.
