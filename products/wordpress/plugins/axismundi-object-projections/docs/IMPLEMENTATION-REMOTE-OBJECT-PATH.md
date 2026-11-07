# Remote Object View Path

2026-11-07. 로컬 구현 및 wp-env 검증. 스테이징 배포와 기존 URI/해시 재작성은 하지 않았다.

## 계약

- 정규 SSR URL: `/remote-objects/{sha256}`.
- `/remote-objects/?uri=<encoded URI>`는 기존
  `axismundi_op_cached_object_view_url()`로 검증/해시 계산 후 302.
- 옛 `?ax_object={hash}`는 기존 단일 쿼리 인자 검증을 유지하고 302.
- 해시 계산과 URI 검증 함수는 변경하지 않았다.
- HTML은 기존 블록 템플릿과 공개 게이트를 유지한다.
- AS2 Accept 요청은 저장된 공개 캐시 표현을 반환하며 원격 id를 유지한다.
  이 namespace는 일반 WP 글의 공식 AP 플러그인 협상 소유권과 별도로 OP가 처리한다.
- 원격 객체는 view-model source이지 로컬 object transformer의 입력이 아니다.
  따라서 AS2에는 캐시의 payload를 사용하고 bto/bcc를 제거한다.
- 읽기는 캐시 조회만 수행한다. 이 경로에서 remote fetch를 실행하지 않는다.
- SPA `/social/objects/{key}`, 원본 URI, 캐시 PK는 변경하지 않는다.
- 공개 캐시 Tombstone의 기존 HTML 404 / AS2 410 정책은 유지한다.

## 검증

`wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-object-view-route.php`
를 wp-env CLI에서 실행: 14개 검사, 실패 0.

HTTP 확인:

- 옛 ax_object 링크: 302, 새 해시 경로 Location.
- uri 진입점: 302, 동일한 해시 경로 Location.
- 기존 공개 Misskey Note 캐시: HTML 200, AS2 200.
- AS2 JSON id는 `https://misskey.io/notes/ap81z89ofg3d0f2n` 그대로.
- 잘못된 해시: AS2 404.
- 기본 canonical redirect가 빈 WP_Post에 접근하는 경고가 관측되어,
  이 namespace에서 core canonical/old-slug redirect를 제거했다.

## 정규화 읽기 전용 감사

현재 wp-env 캐시 128행의 object_uri를 기존 검증 함수에 다시 넣고 저장된 해시와 비교:

| 항목 | 결과 |
| --- | ---: |
| 해시 변경 | 0 |
| 검증 오류 | 0 |
| 유니코드 호스트 | 0 |
| 현재 idn_to_ascii 사용 가능 | true |
| 현재 AS2 URI normalizer 사용 가능 | true |

현재 구성에서의 무변경만 확인했다. Actors 비활성 환경이 과거에 존재했는지,
폴백으로 저장된 행이 있는지, intl 없는 환경에서의 결과는 이 데이터로 증명할 수 없다.
다른 환경에서 향후 정규화 변경이 무해하다는 증명도 아니다.
Actors 의존성/IDN 정책/저장된 URI 및 해시 마이그레이션은 별도 결정으로 남긴다.
