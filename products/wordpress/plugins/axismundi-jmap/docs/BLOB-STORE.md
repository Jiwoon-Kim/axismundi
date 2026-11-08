# Account Blob 저장소

## 소유와 경로

JMAP이 C2S 원본 바이트와 자체 테이블 `ax_jmap_blobs`, `ax_jmap_blob_uploads`,
`ax_jmap_blob_refs`를 소유한다.
Person·Group 모두 같은 Account 서비스와 권한 검사를 사용한다.
WP user가 미디어 라이브러리에 올리는 attachment 및 Emoji S2S 캐시와 합치지 않는다.
attachment 생성, 렌디션, 원본 이름 저장, AP uploadMedia, attachment 색인은 이번 범위 밖이다.

기본 디스크 경로:

```text
<wp_upload_dir basedir>/axismundi-jmap/blobs/{SHA-256 앞 2자리}/{blobId}
```

신규 blobId는 소문자 `b` + SHA-256 hex 65자이며 디스크 확장자가 없다.
샤드는 `b`를 건너뛴 digest 앞 두 자리다. RFC 8620 §1.2의 영문 접두사 권고를 따른다.
기본은 256개 버킷이다. `axismundi_jmap_blob_shard_depth` 필터를 2로 두면 신규
할당만 `{digest 앞 2자리}/{다음 2자리}/{blobId}`로 분산한다. 기존 파일은 이동하지 않는다.
규모별 충분함을 보장하지 않으며 실제 파일 수·파일시스템 성능으로 깊이를 결정한다.
각 내용 행의 `storage_path varchar(255)`에 실제 상대경로를 기록한다. 읽기·삭제는
`storage_root + storage_path`만 사용하므로 현재 할당 규칙을 다시 적용하지 않는다.
빈 값·절대경로·빈 경로 조각·점/상위 경로·백슬래시·다른 파일명·심볼릭 링크를 거부하고,
realpath가 저장 루트 안의 읽을 수 있는 파일인지 확인한다. DB 경로 값도 신뢰하지 않는다.
Cyrus의 SHA-1 기반 G ID와 호환된다고 주장하지 않는다.
개발 중인 기존 64자리 hex·bUUID 링크는 공개 라우트에서 지원하지 않는다.
기존 파일은 재해싱·이동·즉시 삭제하지 않는다. 스키마 전환에서 상대경로를 한 번 기록하고,
내부 만료 정리도 그 경로를 사용한다. 런타임 읽기에는 구 ID 형식별 경로 분기가 없다.
모든 blob_id 열은 varchar(65)다. content_hash는 구 개발 행의 무결성 검사와
공통 검증을 위해 남기며, 신규 행에서는 blob_id = 'b' + content_hash다.
바이트를 변경하거나 같은 경로를 덮어쓰는 API는 없다.
원본 내부 EXIF 등은 바이트와 함께 유지한다. 파일 이름은 참조 객체가 가진다.
다운로드 `name`은 요청마다 정하며 UTF-8 Content-Disposition filename*로 돌려준다.
관리자 링크는 `{blobId}.{MIME에서 유도한 확장자}`를 사용하고 실제 content_type을
전달한다. 알려지지 않은 확장자는 bin으로 대체하며 원본 파일명은 저장하지 않는다.
Core 업로드 처리 전후 해시·크기를 대조해 바이트 변환을 허용하지 않는다.

내용 행은 계정 간 전역 공유한다. `(blob_id, account_identity_id, uploader_user_id)`
업로드 행이 각 업로더의 MIME·생성·만료를 갖고, 참조도 Account별로 구분한다.
내용 행의 content_type은 기본 octet-stream이며 실제 업로드 응답과 관리자 이름은
업로드 행의 타입을 사용한다. 동일 바이트라도 서로 다른 MIME 주장이 섞이지 않는다.
스키마 4 전환은 개발 데이터의 소유·참조를 업로드/참조 행으로 복사하고 물리 상대경로를
기록한 뒤, 내용 테이블의 account_identity_id·uploader_user_id·expires_at을 제거한다.
전환은 기존 ID·바이트·파일 위치를 바꾸지 않고 재실행해도 기록 경로를 덮어쓰지 않는다.

사이트 관리자는 Tools > JMAP Blobs에서 비공개 절대 경로를 설정할 수 있다.
빈 값은 기본 uploads 경로다. 다른 경로는 알려진 웹 루트·content·uploads의
내부 또는 상위 경로이면 거부한다. 운영자는 별도 웹 서버 alias가 그 경로를
노출하지 않는지도 확인해야 한다. 임시 /tmp 경로는 로컬 UI 시험에만 사용했으며
운영 저장 위치로 권고하지 않는다. 경로 변경은 기존 blob을 이동하지 않는다.
각 행이 원래 저장 루트를 유지하므로 기존 파일을 별도로 지우거나 옮기면 안 된다.

## 접근과 수명

- 로그인·Application Password는 인증일 뿐이다. 매번 현재 Account 접근을 재검사한다.
- Core 업로드/삭제는 Account owner·editor에게 허용한다. 공개 프로필·handle 고정은
  요구하지 않는다. 바이트 업로드는 액티비티 발행이 아니기 때문이다.
- 미참조 blob은 같은 Account에서도 해당 uploader_user_id만 읽을 수 있다.
- 참조된 blob은 `axismundi_jmap_blob_reference_readable`에서 참조 객체의 실제
  읽기 권한을 명시적으로 허용해야 한다. 운영자나 업로더라는 이유로 우회하지 않는다.
- 참조 등록·해제 서비스 `reference()`는 권한을 검증하는 신뢰된 객체 저장소용이다.
  공개 REST에서 임의 owner_kind/owner_ref를 등록하는 경로는 없다. 객체 저장소가
  자기 객체의 저장·삭제와 참조 갱신을 조율해야 하며 분산 원자성을 주장하지 않는다.
- 미참조 업로드 만료는 24시간, 참조 해제 때 해당 Account의 업로드에 24시간 유예를 둔다.
  시간당 최대 100개 내용 행을 대상으로 만료를 처리한다. 같은 Account에 참조가 있으면
  해당 업로드를 유지한다. 관리자 삭제는 자기 업로드 행만 제거하며 해당 Account에
  참조가 있으면 거부한다. 다른 Account·업로더의 행은 삭제하지 않는다.
  실제 파일은 모든 Account의 참조 0 + 업로드 행 0일 때만 회수한다.
  이를 영구 보존 보장으로 표현하지 않는다. cron이 멈추면 물리 삭제가 늦어진다.
- 참조·삭제·만료는 blob별 MySQL named lock으로 직렬화한다. 파일 삭제가 실패하면
  메타데이터를 지워 성공으로 표시하지 않는다. 파일 삭제 후 DB 실패는 관리 복구가
  필요한 상태로 남을 수 있다. DB와 파일시스템의 원자적 commit을 주장하지 않는다.

업로드도 같은 내용 잠금을 사용한다. 중복 여부를 보기 전에 신규·중복 모두 코어 검증,
전체 임시파일 쓰기 및 SHA-256 검증을 수행한다. 잠금 후 기존 내용의 크기·digest를
확인하거나 같은 파일시스템의 최종 경로로 rename한다. 기존 적중은 임시파일을 버리고
업로드 행만 추가/갱신한다. 같은 Account·사용자의 재업로드는 행을 늘리지 않는다.
새 내용 기록 후 업로드 행 기록에 실패하면 내용은 보존하고 하루 후 무참조 회수 대상이 된다.

`usage(account)`는 유효 업로드 행의 size 합산이다. 참조 중인 만료 행도 계산하며,
두 사용자가 같은 바이트를 올리면 각 행의 크기를 계상한다. 물리 절약을 사용자 사용량에
반영하지 않는다. 총량 quota 강제·사용량 UI는 아직 없다.
HTTP 성공 status와 응답 필드는 신규·중복에서 같고 적중 여부를 내보내지 않는다.
감사에서 hrtime 기반 신규/적중 시간을 진단 출력한다. 전체 임시파일 쓰기는 빠른
적중 경로를 줄이는 완화일 뿐, DB·캐시·잠금 타이밍을 구분 불가능하게 만든다는 보장은 아니다.
인위적 응답 지연이나 시간 부채널 제거를 구현했다고 주장하지 않는다.

기본 상한은 파일당 10 MiB와 WordPress 상한 중 작은 값이며 Core maxSizeUpload로
동일하게 광고한다. 사용자당 업로드 처리 1개를 named lock으로 제한한다.
워드프레스/PHP/프록시의 요청 본문 크기 제한도 운영 환경에서 설정해야 한다.
이미 REST 서버가 수신한 전체 본문을 메모리에서 읽는 구조이므로 스트리밍 ingress나
계정별 총량 quota를 구현했다고 주장하지 않는다.
허용 형식은 Core get_allowed_mime_types와 파일 검사에 따른다.
raw HTTP 업로드는 임시 파일 후 wp_handle_sideload(), 관리자 multipart는
wp_handle_upload()를 사용한다. upload_files를 Actor 권한으로 추론하지 않는다.
RFC 9404 maxSizeBlobSet은 이 HTTP 경로의 상한이 아니다.

## 정적 경로 차단

기본 uploads 경로에는 Apache .htaccess 및 IIS web.config 차단 파일을 둔다.
이 파일을 쓰는 것만으로 안전하다고 판단하지 않는다. Nginx/CDN의 설정은
플러그인이 자동으로 수정하지 않는다. 인증 다운로드와 정적 우회 차단은 둘 다 필요하다.

업로드 전, 10분 이내의 같은 저장 루트 검사 결과가 없으면 random canary로
정적 GET과 Range bytes=0-15, 그리고 항상 존재하는 저장 루트 URL을 요청한다.
staging/·샤드 디렉터리 자체의 요청은 이번 검사에 추가하지 않는다.
blob과 같은 shard/id 형식의
무작위 파일을 쓰고 항상 제거한다. 응답에 원본/앞 16바이트가 있으면 실패한다.
401/403/404/410 이외는 차단 확인 불가로 취급한다. 따라서 200 오류 페이지도
안전하다고 자동 승인하지 않는다. 루프백 실패·확인 불가는 업로드를 열지 않는다.
루트 응답도 같은 거부 상태 목록을 요구한다. 본문에 카나리 ID가 있거나 staging·
현재 최상위 샤드의 링크/독립 항목이 있으면 거부한다. 두 글자 샤드를 오류 문장 속
부분문자열로 찾지 않아 우연한 일치로 정상 거부 응답을 오판하지 않는다.
http 진단 순서는 파일 GET·파일 Range·루트 GET이다. probe_version=2로 이전의
파일 전용 성공 캐시를 무효화한다.
검사 성공은 검사 시점의 운영 관측이며 모든 프록시·alias·미래 설정에 대한 증명이 아니다.
파일 한 개의 거부만으로 staging 등 모든 하위 경로의 정책 상속을 증명하지 않는다.
스테이징에서 원본 서버가 관측한 결과와 외부/CDN 경로의 결과는 별도로 확인해야 한다.
저장 루트를 바꾸거나 검사에 실패하면 이전 성공 결과를 버린다. 샤드 깊이를 바꾸어도
새 레이아웃의 차단 검사를 다시 요구한다. 우회 상수는 없다.

Docker처럼 외부 origin으로 루프백할 수 없는 배포는
`axismundi_jmap_blob_probe_request`로 내부 origin/Host를 명시적으로 라우팅할 수 있다.
바이트·HTTP 차단 검사는 그대로 실행하며 성공을 주입하는 스위치가 아니다.
설정 가능한 비공개 경로는 알려진 웹 루트 밖인지 검사하며 정적 URL을 광고하지 않는다.

## 검증

운영 데이터가 없는 별도 로컬 WordPress 7.1.4에서 실행한다.
CLI와 웹 PHP의 실제 숫자 UID를 맞춰야 한다. Alpine/Debian의 www-data 이름이
같아도 UID가 82/33으로 달랐다. 파일 소유권 문제를 ACL 버그로 판정하지 않는다.

```sh
docker exec --user 33:33 -e AX_JMAP_HTTP_ORIGIN=http://wordpress \
  ax-clean-review-20261108-cli-1 wp eval-file \
  wp-content/plugins/axismundi-jmap/tests/audit-blobs.php
```

검사는 미참조·공유 Account·타 Account·권한 회수, 실제 HTTP 업로드/다운로드,
UTF-8 이름, 원본 바이트, 관리자 multipart 업로드/삭제, full/Range 정적 차단,
Range만 새는 서버 및 루프백 실패 거부, 동시 업로드, 만료/참조 보존을 포함한다.
일반 uploads의 known-positive 파일을 먼저 내려받아 검사기가 실제 파일을 받을 수
있는지 확인한다. 존재하지 않는 blob URL은 응답 코드·해시만 진단 출력하며
실제 파일과 응답이 같다고 실패로 판정하지 않는다.

2026-11-08 이전 로컬 결과: audit-blobs.php 63개, audit-session-http.php 35개,
audit-session.php 50개, audit-runtime.php 34개, audit-http-transport.php 18개 통과.
Session의 50개 감사는 데이터 타입 provider 정책을 격리하기 위해 Core blob의
isReadOnly 투영 필터만 잠시 제거하고 finally에서 복구한다. 실제 Core 업로드
권한·isReadOnly·Person/Group은 audit-blobs.php에서 별도로 검증한다.

내용/소유 분리 후 재실행: audit-blobs.php 80개, audit-session-http.php 35개 통과.
두 업로더의 동일 내용 다운로드, Account 간 실제 파일 1개, 업로드별 논리 사용량,
다른 Account의 참조 권한 격리, 한 업로드 만료 후 다른 업로드 보존, 마지막 참조·업로드
정리, 별도 DB 연결의 잠금 경합, 실제 관리자 링크 이름·MIME·바이트를 포함한다.
HTTP 신규 업로드 0.0581초, 같은 내용 재업로드 0.0572초를 한 번 관측했다.
이는 로컬 단일 표본 진단이며 통계적 타이밍 동등성이나 부채널 부재의 증거가 아니다.

상대경로 기록 전환 후 재실행: audit-blobs.php 101개,
audit-blob-storage-migration.php 21개, audit-session-http.php 35개 통과.
known-positive 파일을 읽은 뒤 빈/상위 경로를 실제 DB에 넣어 HTTP 404를 확인하고,
절대경로·백슬래시·심볼릭 링크 거부, 1단계와 2단계 샤드의 혼재 읽기·삭제를 검사한다.
레이아웃 변경은 새 차단 검사를 요구한다. 마이그레이션 감사는 local 환경에서만
무작위 접두사의 임시 테이블을 사용하고, 종료 시 테이블·픽스처·변경 옵션을 정리한다.
실제 blob 테이블의 기존 데이터를 비우지 않는다. 구 개발 형식 2종과 내용 주소형의
경로 기록·소유 복사·컬럼 제거·멱등성을 검증한다.

저장 루트 프로브 추가 후 audit-blobs.php 109개 통과. 별도의 공개 테스트 디렉터리에만
autoindex를 허용해 더미 staging 항목을 실제 HTTP로 읽고, 그 응답을 루트 응답으로
주입했을 때 파일 차단 여부와 무관하게 검사가 실패함을 확인한다. 같은 리스팅에
403 상태를 붙여도 실패하며, 정상 오류 문장 속 샤드 두 글자는 리스팅으로 판정하지 않는다.
테스트가 실제 저장소의 deny를 해제하지는 않는다. 픽스처 디렉터리는 종료 시 제거한다.

```sh
docker exec --user 33:33 ax-clean-review-20261108-cli-1 wp eval-file \
  wp-content/plugins/axismundi-jmap/tests/audit-blob-storage-migration.php
```

관리 화면의 비공개 루트 설정·Account 선택은 브라우저에서 확인했다.
초기 브라우저 파일 선택은 확장 권한 때문에 막혔으나, 사용자가 권한을 허용한 뒤
localhost:8884의 a199에서 실제 OGG·PNG 파일 업로드와 다운로드까지 확인했다.
관리자 admin-post.php의 실제 multipart 업로드/삭제 감사도 유지한다.

| 실제 파일 | 바이트 | MIME | 원본·디스크·브라우저 다운로드 SHA-256 |
| --- | ---: | --- | --- |
| audio-placeholder-jazzy-lofi.ogg | 1905029 | audio/ogg | 8b6edabe489ed99066ea4ec6a56f7ea9c6f88d779d858ae2b28c2e36034e9669 |
| 28250-3aeae3ea159b5c8fc690746e003c00e5.png | 33269 | image/png | 069b9a0bfc8cdba4f8d57bb8cd32f74d9069c306a684cfead806ccb6f4d6ea9c |

실제 정적 URL 두 개는 무쿠키 GET·Range 모두 403, 본문 316바이트이고 원본 앞
16바이트가 없었다. 존재하지 않는 URL과 응답이 같은지는 성공/실패 판정에 쓰지 않는다.
인증 링크의 `_wpnonce` 쿼리를 permission()이 받지 않던 403 버그를 실제 클릭으로
발견해 수정했다. 쿠키+쿼리 nonce 성공, nonce만 보낸 요청 거부, 잘못된 nonce 거부를
회귀 검사에 추가했다. 브라우저 download 이벤트는 도구에서 잡히지 않았지만
실제 Downloads 파일 생성 및 원본과의 해시 일치를 확인했다.
관리자 HTML에서 생성된 링크를 그대로 따라가 이름·MIME·바이트를 검증하고,
이전 단계에서는 bUUID와 기존 64자리 ID를 검증했다. 이후 소유자 결정으로 두 형식의
공개 호환을 제거했다. 당시 브라우저에서 PNG·OGG가 각각 ID.png·ID.ogg로 저장되고
원본 해시와 일치함을 확인한 기록은 남긴다.
기록·스크린샷은 `_dist/attribution-jmap-20261108/`에 있다. 테스트 blob 두 개는
삭제하지 않았으며 미참조 상태이므로 기본 24시간 만료 대상이다.
스테이징 결과는 아래 호스트 호환성 절에 있다. 릴리즈 ZIP/버전은 변경하지 않았다.

## 호스트 호환성

정적 차단에 쓸 수 있는 수단은 PHP가 쓸 수 있는 파일뿐이다. `.htaccess`는 Apache와
LiteSpeed가, `web.config`는 IIS가 읽는다. Nginx에는 per-directory 설정 파일 기구가
없으므로 같은 차단을 플러그인이 만들 수 없고 호스트의 서버 설정이 필요하다.
Nginx가 비공개 저장에 부적합하다는 뜻이 아니라, 우리가 쓸 수 있는 수단이 없다는 뜻이다.

| 환경 | 결과 |
| --- | --- |
| wp-env (Apache) | 프로브 통과. 파일 GET·Range·루트 GET 모두 거부 |
| designbusan.ai.kr (WordPress.com / WP Cloud, nginx) | 프로브 실패. 128바이트 카나리가 정적 URL로 반환됨 |

스테이징 실패 시 업로드는 열리지 않았고 실제 파일은 저장되지 않았다. 노출된 것은
진단용 난수이며 프로브가 제거했다. 바이트를 오리진·프록시·CDN 중 어디가 제공했는지는
구분하지 못했다. 이 구분은 호스트 로그에서만 확인할 수 있다.

WP Cloud 문서는 SSH 홈 디렉터리를 "files placed there are not served by the web server
and are not available to PHP code handling a web request"로 설명하고, `/srv/htdocs`를
웹 루트로, `/tmp`를 "temporary work that does not need to persist"로 둔다. 따라서 이
플랫폼에 "웹 루트 밖 + 웹 요청 PHP가 읽고 쓰는 + 영속" 경로가 존재하는지는 확인되지
않았다. 지원팀에 문의 중이다.
근거: [WP Cloud access models](https://wp.cloud/docs/site-access/ssh-sftp/access-models/),
[문의 스레드](https://wordpress.com/forums/topic/private-file-storage-for-a-custom-plugin/).

Session은 저장 가능 여부와 무관하게 `uploadUrl`·`downloadUrl`을 항상 광고한다.
RFC 8620 §6.1이 `uploadUrl`을 Session 필수 속성으로 두므로 숨기면 비적합 Session이
된다. 저장 불가 호스트에서는 업로드 시도가 오류로 끝나며 이것이 의도된 동작이다.

비공개 저장 루트 설정은 파일시스템 검사만 한다. 웹 서버 alias로 노출되는지, 재배포 후
유지되는지는 검사하지 못하며 `/tmp`도 검사를 통과할 수 있다. 호스트가 확인해 준 경로만
입력해야 한다.

### 후속 선택지 (미구현, 기본값 변경 없음)

파일 백엔드를 기본으로 유지한다. 기본값을 바꾸면 저장할 곳이 없을 때 업로드를 막는
현재의 fail-closed가 사라진다.

- DB 백엔드. 비공개 경로가 필요 없고 정적 URL이 존재하지 않는다. `max_allowed_packet`,
  PHP 메모리, DB 용량 제한을 받는다. 참조된 blob은 만료로 줄지 않으므로 만료만으로
  총량이 제한된다고 말할 수 없다.
- 외부 비공개 객체 스토리지(S3 호환). 다운로드는 인증 후 PHP 중계 또는 짧은 수명의
  서명 URL로 제공한다.

어느 쪽이든 `storage_path`를 URL로 재해석하지 않고 백엔드 종류와 객체 키를 분리한다.
업로드 중간 파일을 공개 uploads에 남기지 않는다. Account·업로더 권한, 참조·만료·
중복제거는 백엔드와 무관하게 JMAP이 관리한다.

## 미구현

RFC 9404 Blob/upload·get·lookup capability와 RFC 8620 Blob/copy, EventSource,
메일 MIME 조립, AP uploadMedia 어댑터·광고, attachment 가져오기는 후속이다.
다운로드는 권한 회수를 늦추지 않도록 private, no-store를 사용하며 Range는 무시하고
인증 후 전체 바이트를 반환한다. 이 단계가 완전한 JMAP 서버라고 주장하지 않는다.

근거: [RFC 8620 §6](https://www.rfc-editor.org/rfc/rfc8620.html#section-6).
