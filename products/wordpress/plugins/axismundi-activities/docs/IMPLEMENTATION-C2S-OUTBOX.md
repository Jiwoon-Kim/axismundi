# AP C2S Like / Undo, 2026-10-29

## Scope

이번 단계는 인증된 AP C2S 입구의 제한된 구현이다. 기존 WP Interactivity API 블록과
`POST/DELETE axismundi/v1/likes`는 바꾸지 않는다. 원장의 넓은 어휘 수용 계약과 달리
outbox 입구는 Like와 Undo(Like)만 허용한다. Create 및 bare Object 제출은 아직 거부한다.
따라서 AP C2S 전체 구현이나 §6.2.1 완료를 주장하지 않는다.

## Transport

```text
POST /wp-json/axismundi/v1/actors/{uuid}/outbox
GET  /wp-json/axismundi/v1/actors/{uuid}/liked
GET  /wp-json/axismundi/v1/actors/{uuid}/liked?page=1[&before={id}]
```

기존 outbox GET은 OP가 그대로 제공하고 POST는 Activities가 추가한다. POST는
`application/activity+json`, `application/ld+json`, `application/json`을 받는다.
현재 type·actor·object는 단일 문자열이며 object는 URI여야 한다. Like-with-content의
이모지 반응, 내장 객체와 복수 값은 이 단계의 입력 계약에 포함하지 않는다.

쿠키 인증에는 `X-WP-Nonce`가 필요하다. Application Password는 WP 코어에서 실제 인증한
UUID를 확인하고 nonce를 요구하지 않는다. Authorization 헤더의 존재만 믿지 않는다.
운영 환경의 Application Password HTTPS 조건도 WP 코어가 집행한다.

principal User → URL Actor의 운영 권한과 기존 반응 작성자 정책을 검사한다. 이번 범위는
기존 반응 서비스와 같은 활성·public·핸들 고정 local Person이다. 이를 AS2의 보편적인
Actor 타입 제한이라고 해석하지 않는다. 브라우저 선택 쿠키와 JMAP capability는 사용하지 않는다.
본문 actor는 URL Actor와 정확히 일치해야 한다. JSON의 uuid가 URL 매개변수를 덮어쓸 수 없다.

클라이언트 id는 무시하고 서버가 Activity URI를 발급한다. 성공 응답은 `201`과 `Location`이다.
원장의 기존 API, Actor 검사, Undo 상태 전이를 재사용한다. WP source 제출 서비스에
Like를 억지로 넣거나 임시 WP_Post를 만들지 않는다. 이 단계에서 S2S 전달 성공까지 보장하지 않는다.
기존 Activity ID 읽기는 공개 투영이므로 private Activity의 인증된 읽기 표면은 후속 범위다.

## Undo And Retry

Undo는 지정된 Like URI를 그대로 사용한다. 대상이 실제로 존재하고 같은 Actor가 작성한
plain Like인지 검사한다. "현재 Like 취소"로 변환하지 않으며 Create/Add의 취소도 받지 않는다.
이전 Like가 이미 undone이어도 새 Like에는 영향을 주지 않는다. addressing은 클라이언트가
명시한다. 원격 수신자에게 철회를 전달하려면 원 Like에 맞는 addressing을 제출해야 한다.

선택적인 `Idempotency-Key`는 우리 HTTP 확장이지 AP 표준 속성이 아니다. 최대 128자
printable ASCII이며 User·Actor 범위로 분리된다. 기존 원장의 source-event 고유 키를 재사용하고
Actor별 named lock 안에서 동일 키의 입력을 비교한다. 다른 입력에 같은 키를 쓰면 409다.
동일 입력은 원래 Activity를 반환하며, 그 Activity가 이미 undone이어도 되살리지 않는다.
키가 없으면 POST 각각은 별도 제출이다. 기존 UI의 활성 Like 중복 방지와 이 계약은 다르다.

## Liked Disclosure

최종 오너 결정은 멤버별 audience 필터가 아니라 **컬렉션 단위 공개/비공개**다.
Actors의 `ax_actormeta`에 `policy:liked_visibility`로 저장하며 기본값은 private다.
Actor Profile의 체크박스는 공개 시 private-addressed Like의 대상 URI와 좋아요 사실까지
노출된다는 점을 명시한다. sensitive 및 객체 본문의 공개 설정과는 독립적이다.

- private: AP liked 읽기는 운영자 쿠키 유무와 무관하게 차단한다.
- public: 익명·제3자도 같은 URI 목록을 읽는다. Like의 audience로 다시 필터하지 않는다.
- 객체 본문은 포함하지 않는다. 실제 dereference 권한은 객체 엔드포인트가 집행한다.
- 접근 불가능한 객체의 존재와 좋아요 사실은 공개 설정으로 의도적으로 노출되는 정보다.
- 공개를 끄면 이후 조회는 차단하지만 이미 공개된 정보를 회수할 수는 없다.

멤버십은 원장에서 중복 URI를 제거한 뒤 MAX(id) 커서로 페이지화한다. Undo된 Like와
이모지 반응은 제외한다. AP 읽기는 principal이나 선택 쿠키로 우회하지 않는다. 운영자의
"내 좋아요" 전체 조회는 향후 별도 API에서 principal 인가로 구현하며 이번 범위에는 없다.
OP는 컬렉션 계약·JSON-LD 표현을, Activities는 멤버십을 소유한다. 두 읽기 표면은 향후 같은
원장 질의를 재사용할 수 있지만 요청자 인가와 프로토콜 표현은 분리한다.

쿠키에 따라 표현이 달라지지는 않지만 아직 응답은 no-store다. 공개를 끈 직후에도 이전
공개 응답을 shared cache가 계속 제공하는 상황을 만들지 않기 위해, 캐시 무효화 계약 없이
public max-age를 추가하지 않는다. 숨겨진 totalItems도 계산하거나 광고하지 않는다.

Mastodon의 현재 Actor serializer는 liked를 광고하지 않는다. FavouritesController는
인증 및 read:favourites scope 아래 current_account.favourites를 조회한다. 이것을
공개 liked 컬렉션 구현의 선례로 삼지 않는다.

Sources:
- https://www.w3.org/TR/activitypub/#client-to-server-interactions
- https://www.w3.org/TR/activitypub/#liked
- https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- https://github.com/mastodon/mastodon/blob/main/app/serializers/activitypub/actor_serializer.rb
- https://github.com/mastodon/mastodon/blob/main/app/controllers/api/v1/favourites_controller.rb

## Verification

```sh
AX_C2S_HTTP_ORIGIN=http://wordpress wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-outbox-http.php
wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-outbox-mutations.php
```

HTTP 감사는 실제 WP 인증·라우팅을 통과한다. 쿠키 nonce의 누락·오류, App Password의
nonce 없는 인증과 폐기, 타 Actor·URL shadowing, server ID, 재시도 충돌, 오래된 Undo,
컬렉션의 기본 비공개·운영자 우회 거부·공개 응답의 쿠키 독립성·명시적 공개·재차 차단을 검사한다. 기존 원장과 principal
전체 행 내용의 전후 일치도 검사한다. 테스트 자격 증명은 출력하지 않는다.

변이는 함수 소스를 Reflection으로 읽어 메모리에서만 복제한다. 지정 Undo를 현재 Like로
치환하는 변이, 컬렉션 private 설정을 우회하는 변이, AP 읽기에 운영자 예외를 되살리는 변이를 검사한다. 소스 파일이나 기존
데이터를 변이하지 않으며 fixture Activity는 finally에서 제거한다.

이전 요청자별 정책 단계에서 HTTP 39개 단언과 변이 2개를 검증했다. 최종 정책은 AP 읽기와
principal API를 분리하며 위 HTTP 감사에 운영자 우회 거부·응답 동일성 검사를 반영했다.
Undo 대상 치환·collection policy 우회 변이에 운영자 예외 복원 변이를 추가했다.
최종 분리 후 실제 HTTP 40개 단언과 변이 3개가 모두 통과했다. AP 공개 응답은 운영자
쿠키 유무에 따른 본문 차이가 없고, 비공개 설정은 운영자에게도 403을 반환한다.
이 분리는 AP의 모든 principal 인가를 금지한다는 뜻이 아니다. C2S 쓰기와 공개 설정
변경의 principal 인가는 그대로 유지하며, 여기서 분리한 것은 liked 읽기 표면이다.
기존 votes 7/7, public outbox 4/4, collection contracts 14/14 통과. HTTP 감사는 실제
admin-post 설정 저장·CSRF 거부와 체크박스의 private 상태 및 공개 동의 문구도 검사했다.
최종 원장 30,770행·principal 1,038행이며 HTTP 픽스처 Actor 잔여는 0이다. 기존 votes
감사의 principal cleanup 누락 한 줄을 보완했고 이번 실행이 남긴 해당 principal 한 행만
정리했다. 기존 데이터 일괄 정리, 재발신, schema 변경은 하지 않았다.

## HTTP 감사 실행법

`audit-c2s-outbox-http.php` 는 실제 왕복을 하므로 다른 감사와 달리 **웹서버에 닿는
origin** 이 필요하다. 기본값 `home_url()` 은 호스트에서만 유효하고, wp-env 의 cli
컨테이너에서는 `cURL error 7` 로 죽는다. 내부 호스트를 환경변수로 준다.

```bash
docker exec -e AX_C2S_HTTP_ORIGIN=http://wordpress \
  wp-env-<project>-cli-1 \
  wp eval-file /var/www/html/wp-content/plugins/axismundi-activities/tests/audit-c2s-outbox-http.php
```

`npx wp-env run cli -- wp eval-file …` 로는 실패한다. 그 실패는 구현 결함이 아니라
도달 불가다 -- 2026-10-29 리뷰에서 실제로 그렇게 오해했다.
