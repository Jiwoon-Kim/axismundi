# Application Single Object Read

## Scope, 2026-10-30

`/social/objects/{id}`는 기존 primary pane에서 단일 객체를 JSON 텍스트로 표시한다.
새 카드, Actor 전환 UI, DataView, list-detail, 검색, 피드, 미디어 렌더링은 추가하지 않는다.
프런트앱은 기존 LoadingIndicator를 사용하고 요청 중단 시 이전 응답을 반영하지 않는다.

## Identifier Decision

이번 `{id}`는 `ax_objects.id`다. WP post ID나 로컬 본문 ID와 혼용하지 않는다.
기존 인덱스에서 canonical URI와 source_owner/kind/ref를 읽고 실제 소스와 대조한다.
JSON의 `id`는 원래 canonical URI이며 `/social/objects/{id}`로 바꾸지 않는다.
새 테이블, 번호 재발급, 스키마 변경은 없다.

이는 기존 인덱스가 정수 deep link의 locator로 쓰인다는 실측 사례이지, 통합 원장이
도메인 모델상 필수라는 증명은 아니다. 각 저장소에 이미 URI 인덱스가 있으므로 나중에
canonical URI를 입력으로 삼는 조회 계약과 비교할 수 있다. 프런트앱에 출처별 SQL/저장소
분기를 노출하지 않는 것과 공통 DB 테이블이 반드시 필요한 것은 서로 다른 결정이다.

### 오너 결정 (2026-10-30): `ax_objects` 는 유지한다

위에서 열어 둔 질문 -- 정수 locator 외에 통합 인덱스가 필요한가 -- 를 닫는다.
근거는 도메인 모델이 아니라 **주소 공간의 대칭성**이다.

```txt
/social/actors/{ax_actors.id}          원장 PK
/social/activities/{ax_activities.id}  원장 PK
/social/objects/{ax_objects.id}        이 인덱스
```

Actor 와 Activity 는 자기 PK 를 쓰는데 Object 만 URI 를 입력으로 받으면, 세 공간 중
하나만 성질이 달라진다. 그 비대칭을 피하는 값이 테이블 하나를 유지하는 비용보다 크다.
앞서 검토한 `?uri=` 쿼리 방식은 기술적으로 성립하지만 이 대칭을 깨뜨리므로 택하지 않는다.

**유지하는 것은 역할이지 범위가 아니다.** 이 결정은 `ax_objects` 가 locator 이자 정수
id 공간이라는 뜻이고, 객체의 권위 있는 본체가 된다는 뜻이 아니다.

```txt
유지   canonical URI <-> 정수 id 매핑, source_owner/kind/ref 로 소유 저장소 지목
금지   타입 · 귀속 · 상태를 여기서 권위로 읽는 것.  그 답은 소유 저장소가 한다
```

이 구분이 중요한 이유는 과잉 설계가 굳는 경로가 정해져 있기 때문이다. 컬럼이 늘면
소비자가 그 컬럼을 읽고, 읽는 곳이 늘면 "이미 쓰고 있으니 유지"가 근거가 된다.
Emoji 가 그 반례였다 -- 객체로 존재하지만 피드에 실리지 않으므로 이 인덱스에 등록할
이유가 없다. **객체로 존재함 != 독립 발행됨 != 피드에 실림** 은 그대로 유지한다.

## API and Authorization

`GET /wp-json/axismundi/v1/objects/{id}`를 OP가 소유한다. 기존 `/objects/read`와
canonical AP 응답은 공개 읽기 정책 그대로다. 새 API는 먼저 기존 공개 reader를 호출한다.
공개 객체는 로그인이나 Actor 선택을 요구하지 않는다.

비공개 로컬 본문/원격 캐시에는 다음 순서로 서버 판정을 적용한다.

1. WP가 인증한 User를 확인한다. 프런트 bootstrap은 로그인 사용자에게 wp_rest nonce를
   제공하고 fetch는 같은 출처 쿠키와 X-WP-Nonce를 전달한다.
2. 기존 Actors resolver가 HttpOnly 선택 쿠키의 서명/로그인 세션 바인딩과 현재 User의
   Account 접근 권한을 검사한다. 쿼리의 actor/owner/granted 플래그는 사용하지 않는다.
3. 현재 Actor가 저장된 작성 Actor이거나 to/cc/bto/bcc의 명시 수신자면 허용한다.
   작성 Actor의 실제 followers endpoint가 수신자에 포함되어 있고 현재 Actor의 Follow가
   accepted일 때만 팔로워로 허용한다. Mention이나 임의 컬렉션 URI는 권한이 아니다.
4. 인가 뒤 소스의 변환/정규화와 OP sanitizer를 거친 JSON만 반환한다. 숨은 수신자는
   제거한다. 출처의 URI/type이 인덱스와 충돌하면 409이며 비활성 소스는 우회하지 않는다.

비로그인/무권한/미발견은 본문 없는 404다. 이미 로그인했으나 선택이 없거나 유효하지 않으면
선택이 필요하다는 403을 반환한다. 이 차이는 로그인 사용자에게만 적용된다.
WP draft/private/trash는 Actor 운영 권한으로 열지 않는다. 기존 Core 소스 공개 정책을 따른다.
로컬 공개 Tombstone은 최소 문서와 410이다. 모든 새 API 응답은 private, no-store다.

원격은 캐시에 보유한 audience/관계 근거로만 판단한다. 원본 서버의 최신 권한을 확인했다고
주장하지 않는다. 개별 수신자/팔로워 외의 그룹 audience 해소는 이번 범위가 아니다.

## Network and Presentation

캐시 미스는 404로 끝난다. 조회는 fetch, 재검증, lease touch를 하지 않는다.
향후 browser.pub식 수집은 별도의 명시적 작업이어야 한다.
현재 JSON은 React 텍스트 노드로 표시하며 HTML, remote context, image/attachment URL을
실행하거나 로드하지 않는다. 본문을 캐시에서 읽는 것만으로 미디어 IP 노출까지 방지되는
것은 아니므로 Visual 렌더링 시 미디어 프록시/외부 로딩 정책을 별도 결정한다.

## Verification

- `wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-application-object-read.php`:
  23검사. 로컬/원격, 공개/비공개, 세션 선택/위조, 명시 수신자/팔로워, Tombstone,
  내부 REST dispatch, no-store, 네트워크 0회를 검사한다. 본문/인덱스/관계 픽스처는 rollback한다.
- `wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-object-read-api.php`:
  55검사. 퇴역 Note CPT 픽스처를 로컬 본문 소스로 교체하고 기존 scalar/map 검사는 유지했다.
- `wp eval-file wp-content/plugins/axismundi/tests/audit-public-routes.php`: 41검사.
- Axismundi `npm run build` 통과. 기존 entrypoint 크기/runtime 분할 경고는 남아 있다.
- 실제 Edge 브라우저에서 원격 캐시 객체 47195의 JSON과 미발견 오류 표시를 확인했다.
  객체 조회에서 원격 HTTP 요청은 없었고 같은 출처 blob 요청만 관찰됐다.

선택 쿠키 검사는 실제 WP 로그인 세션 토큰을 생성한 CLI 감사이며, 로그인 브라우저의
비공개 GET/nonce 누락 왕복과 권한 회수 HTTP 검증을 대신하지 않는다. 이번에 그 HTTP
조합이나 변이 검증을 완료했다고 주장하지 않는다. Actor 단일 조회는 다음 슬라이스다.

## Handoff

- Actor 블록은 HTTP가 아니라 Actors의 내부 PHP 조회 API를 사용한다.
  `resolve_block_subject()` / `block_subject_from_actor()` / `profile_data()`를 재사용할
  수 있다. 범용 Actor 상세 REST 입구는 아직 추가하지 않았다. 기존 mention-resolve는
  edit_posts 권한의 편집기용이며 익명 상세 조회로 권한을 완화하지 않는다.
- Actor 앱 조회 키는 로컬/리모트 공용 `ax_actors.id`를 사용한다는 방향이다.
  `/social/actors/{id}`와 `/social/@handle@host`의 리디렉션은 논의 상태이며 미구현이다.
  기존 Actor canonical URI와 발행된 라우트는 바꾸지 않는다.
- 기존 공개 reader의 axObservation/axUninterpreted/axPayloadFallback은 이번 연결 전부터
  있었다. 객체와 API 메타데이터를 분리할지 후속 결정한다. 이번 커밋에서는 응답 구조를
  바꾸지 않았다. axUninterpreted는 값 비교 결과나 금지 목록이 아니라 미처리 키의 잔여다.
- 피드 목록은 상세 조회와 별도 계약이다. Article 카드에는 본문을 받았다가 버리는 대신
  서버의 요약 조회가 필요하다. 현재 단일 객체 API를 목록 API로 확장하지 않는다.
- 성능 최적화는 오너 결정으로 스테이징 측정 전까지 보류한다. 로컬 HTTP에서 정적 JS
  80ms, REST 루트 8.2초, 객체 API 5.3초, 앱 HTML 8.1초를 관찰했다. 별도 CLI에서는
  객체 조회 8ms/전체 SQL 40ms에 비해 공통 WP 초기화 비용이 컸다. CLI와 웹 OPcache는
  조건이 다르므로 같은 프로파일로 취급하지 않는다. Windows bind mount의 stat 비용도
  관찰했으나 Social 전용 부팅이나 블록 등록 생략을 구현하지는 않았다.
