# 플러그인 리뷰 입력과 출력 경계

## 변경

- Community migration과 review POST는 명시된 필드만 읽는다. URI, nonce,
  decision, reason과 ID를 서비스 호출 전에 정화하며 배열 입력은 400으로 거부한다.
- review 리다이렉트는 정화된 group_id를 재사용한다.
- Actor 라벨 직접 출력은 a[href], code만 허용하는 wp_kses로 감싼다.
- Follow, Block, Remove 제어의 직접 출력은 폼 전용 로컬 허용 목록을 사용한다.
  action, nonce, referer, 버튼의 name/value를 보존한다. 전역 KSES 정책은 바꾸지 않는다.
- Follow 두 분기와 reaction bar의 직접 context 출력은 wrapper 속성으로 옮겼다.
  JSON_HEX 옵션은 코어 컨텍스트 헬퍼와 같고 속성 이스케이프는 wrapper가 담당한다.
- nonce 검증은 원래도 정화했지만 Plugin Check가 원본 POST의 지역 변수 대입을
  지적했다. 이제 POST 읽기 지점에서 정화한다. 단순 오탐으로 회신하지 않는다.

## 전수 확인 범위

includes의 직접 echo 헬퍼 및 직접 echo context 호출은 더 이상 남지 않는다.
기존 OutputNotEscaped 주석은 반환 HTML 조립, 코어 wrapper 출력, CLI 감사 출력에도
있다. 이를 모두 같은 취약점으로 세거나 주석 수만큼 수정했다고 보고하지 않는다.
반환 경로에서 코어 context 헬퍼를 사용하는 것까지 불필요하게 재작성하지 않았다.

## 검증과 한계

- audit-review-output.php: 8개 통과. 입력 필드 선택/정화, 배열 거부,
  폼 보존, 악성 마크업 제거, context JSON 왕복과 서버 Interactivity 처리를 확인했다.
- audit-follow-modal-kses.php: 9개 통과. 기존 허용 목록 변이 검사 포함.
- audit-follow-button.php: 13개 통과.
- audit-publish-selection.php: 24개 통과.
- audit-reactions.php: 14개 통과. 중복 interaction 블록 등록 notice와
  OP reactions.php:119의 attributedTo 누락 warning은 별도로 남아 있다.
- audit-local-social.php: 25개 중 24개 통과. 실패는 Contributor Follows URL의
  profile.php 기대값이다. 이번 변경에서는 그 URL 생성 함수를 변경하지 않았다.
- wp plugin check axismundi-activities --format=json을 실행했다. 검사 종료 코드는
  0이지만 DB/nonce/input 경고와 CLI fixture 오류가 있어 전체 통과로 보고하지 않는다.
  tests 디렉터리를 포함한 개발 checkout 검사이며 배포 ZIP 검사를 대체하지 않는다.
- wp plugin check axismundi-activities --checks=late_escaping --exclude-directories=tests
  --format=json: 오류 없음. 운영 코드 출력 검사 결과이며 전체 Plugin Check 통과는 아니다.

기존 wp-env에서 검증했다. 깨끗한 WordPress 설치, 배포 ZIP, 브라우저 hydration과
실제 클릭 회귀는 아직 검증하지 않았다. 업로드, 이메일 발송, 커밋과 푸시는 하지 않았다.
