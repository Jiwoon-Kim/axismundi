# Geodata 공간 Feature 실험

상태: 설계 초안. Outbox 이후 진행한다. 이 문서는 구현이나 적합성 선언이 아니다.

장기 제품 방향과 기능 후보는 [Geodata 확장 방향](geodata-direction.md)을
참조한다. 아래 실험의 통과 조건과 장기 기능 목록은 별개다.

## 객체와 컬렉션

- `geo_area`와 `geotag`는 기존 게시물 분류로 유지한다. 기존 term, 좌표,
  공개 URL을 자동 이관하지 않는다.
- 독립 Place는 Geodata가 소유하는 메타데이터 객체다. 정규 URI는 이 서버가
  발행한 레코드를 식별하며, 현실 장소의 유일한 전역 ID는 아니다.
- OGC 컬렉션은 탐색·조회 범위다. 동일 Feature를 여러 컬렉션에 노출해도
  정규 URI는 유지한다. 컬렉션 항목 URL은 별도 조회 주소일 수 있다.
  컬렉션에서 빠지는 것과 원본 객체의 삭제는 별개다.
- 기본 표현은 GeoJSON이다. JSON-FG는 CRS·시간·형상 의미를 보존할 수 있는지
  살피는 기준이지 DB 스키마가 아니다. JSContact Location Card와 AS2 Place는
  같은 정규 URI의 선택적 표현 후보이며, 공개 허용 정보만 투영한다.

## 최소 스키마 후보

| 테이블 | 후보 필드 |
| --- | --- |
| `ax_geo_features` | 내부 PK, 정규 URI와 유일성 키, 이름, 유형, 공개 상태, 생성·수정 시각 |
| `ax_geo_geometries` | 내부 PK, Feature 참조, 형상 역할, Geometry, Feature 조회 인덱스와 공간 인덱스 |

공원 경계와 대표점을 별도 역할로 저장한다. 첫 컬렉션은 경계를 공개
GeoJSON `geometry`와 교차 검색에 쓰고, 대표점은 마커용으로 쓴다.
외부 좌표 순서는 경도, 위도로 고정한다. 엔진별 DDL과 생성자 차이는 저장
계층 안에 두고, 컬렉션 응답은 DB 제품에 의존하지 않게 한다.

## 첫 단계: 두 엔진 감사

논의에서 공유된 wp-env MariaDB 12.3.3 관측: 컬럼의 `SRID 4326` 선언은
실패했고, `GEOMETRY NOT NULL`과 공간 인덱스는 생성됐다. 값의 SRID 4326,
GeoJSON 축 순서, 교차 판정 및 `EXPLAIN`의 공간 인덱스 선택이 관측됐다.
실패한 DDL에도 `dbDelta()`가 생성 메시지를 반환한 사례가 있었다.
이 문서 작업에서 해당 결과를 재실행하지 않았으며, MySQL은 아직 미검증이다.

- 동일한 비대칭 좌표와 Polygon으로 양쪽 엔진의 GeoJSON 왕복, SRID, 교차
  결과를 비교한다. 공통 DDL보다 동일한 결과가 기준이다. MySQL 8.4의
  SRID 제한 컬럼을 인덱스 후보로 검증한다.
- 대표점이 bbox 밖이지만 경계가 걸치는 공원은 포함하고, 최소 경계 사각형만
  교차하는 객체는 제외한다. 인덱스 후보 검색과 실제 형상 판정을 분리한다.
- 선택도가 높은 bbox와 충분한 데이터로 `EXPLAIN`을 확인한다. 인덱스 생성과
  실행 계획상 사용을 별개로 기록한다.
- 설치 성공은 실제 컬럼과 인덱스를 재조회해 판정한다. 설치가 실패하면
  완료 상태를 기록하거나 실험 API를 활성화하지 않는다.

## 다음 단계: 공개 읽기

감사를 통과하면 `places` 하나로 랜딩, API 정의 링크, 적합성 정보, 컬렉션
목록·상세, 아이템 목록·상세를 연결한다. bbox 검증과 안정된 정렬 키,
`next` 링크를 둔다. 페이지 사이에 삽입·형상 변경이 있을 때를 검사하되
스냅샷 일관성은 약속하지 않는다. 해당 적합성 요구를 검증하기 전에는
OGC Core 준수를 광고하지 않는다.

성공 기준: 우리 Map과 외부 GIS가 같은 컬렉션을 발견하고, 대표점이 화면
밖이지만 경계가 걸치는 공원을 둘 다 찾는다. 기존 Geodata 경로는 그대로
동작해야 한다. JSON-FG 응답, CQL2, GeoSPARQL, 연합 및 리뷰 집계는 별도다.

## 참조

- [OGC API – Features Part 1: Core](https://docs.ogc.org/is/17-069r4/17-069r4.html)
- [OGC JSON-FG Part 1: Core](https://docs.ogc.org/is/21-045r1/21-045r1.html)
- [MySQL 8.4 Spatial Index Optimization](https://dev.mysql.com/doc/refman/8.4/en/spatial-index-optimization.html)
- [MariaDB Spatial Index](https://mariadb.com/docs/server/reference/sql-structure/geometry/spatial-index)
