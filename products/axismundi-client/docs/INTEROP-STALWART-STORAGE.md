# Stalwart Storage Layout

2026-11-20. Stalwart v1.1.0(`stalwartlabs/stalwart:v1`) 로컬 상호운용 서버의 데이터 저장소 실측.
**참고 자료이지 클라이언트 계약이 아니다.** 클라이언트는 RFC 8620 대로 모든 id 와 state 를 불투명
문자열로 다룬다. 아래 해독은 서버 동작을 이해하고 관찰 결과를 해석하는 데만 쓴다.

출처는 둘이다. 문서로 확인한 것과 이번에 덤프해서 측정한 것을 구분해 적는다.

- 문서: [Database Internals](https://stalw.art/docs/development/database/),
  [Storage](https://stalw.art/docs/storage/), [Data store](https://stalw.art/docs/storage/data/),
  [Migration](https://stalw.art/docs/management/maintenance/migration/),
  [Database Console](https://stalw.art/docs/management/maintenance/database/)
- 측정: 아래 "재현" 절의 절차로 뜬 RocksDB 사본 덤프(키 11,149개)

## 1. 왜 DBeaver 로 볼 수 없나

설정 파일은 저장소 위치만 담는다. 나머지 설정은 전부 저장소 안에 있다(문서: "keeps almost all of its
configuration in its own data store").

```txt
config.json   {"@type":"RocksDb","path":"/var/lib/stalwart/","blobSize":4096}   122 bytes
data/         RocksDB: *.sst (정렬 테이블) + *.blob (큰 값 분리 저장)
```

- **백엔드를 바꿔도 데이터는 옮겨지지 않는다.** 문서: "changing a store's backend only changes where
  the server looks for its data: it does not move the existing data". 빈 DB 를 가리키면 계정·메일·
  설정 없이 시작한다. 옮기려면 서버를 멈추고 `--export` → 새 설정으로 `--import`.
- **SQL 백엔드도 같은 구조다.** 문서: "The same layout is used with every data store backend".
  SQL 에서는 서브스페이스 하나가 글자 이름의 테이블 하나(`a`…`z`), RocksDB 에서는 column family
  하나, FoundationDB 에서는 키 첫 바이트. 키와 값은 바이너리다. `mailboxes` 같은 테이블은 없다.
  PostgreSQL·MySQL 만 전문 검색을 `s_email`·`s_cal`·`s_card`·`s_file`·`s_trace` 에 따로 둔다.

## 2. 서브스페이스 (문서 표 + 측정한 키 수)

글자 하나가 서브스페이스 하나다. 설명은 문서 그대로, 키 수는 이 서버의 측정값이다.

| 글자 | 이름 | 내용 (문서) | 키 수 |
|---|---|---|---|
| a | ACL | 다른 계정에 공유 자원 접근을 주는 ACL | 0 |
| b | Registry index | 설정·디렉터리 객체를 필드 값으로 찾는 인덱스 | 4887 |
| c | Immutable | 한 번 쓰고 안 바뀌는 값. 메일의 파싱된 메타데이터와 정렬 키 | 32 |
| d | Directory | 계정·도메인·역할·메일링 리스트·OAuth·공개키·테넌트·마스킹 주소 | 11 |
| e | Queue messages | 발송 큐의 메시지 | 0 |
| f | Task queue | 백그라운드 작업, 재시도·실패 작업 | 0 |
| g | Registry primary keys | 설정·디렉터리 객체의 고유 키와 참조 | 2768 |
| h | Outgoing reports | 보낼 DMARC·TLS 리포트 | 0 |
| i | Indexes | 계정 데이터 인덱스(메시지·메일함 정렬·필터용) | 0 |
| j | Deleted items | 보존 기간 중 복구용 삭제 항목 (Enterprise) | 0 |
| k | Blob links | blob 과 그것을 참조하는 문서의 연결 | 53 |
| l | Logs | 변경 로그(IMAP CONDSTORE·JMAP changes·WebDAV sync), 공유 알림 | 291 |
| m | Search documents | 내장 전문 검색 인덱스의 문서 | 16 |
| n | Counters | 계정별 문서 id·변경 id·메일함 UID·객체 수·쿼터 사용량 등 | 39 |
| o | Telemetry spans | 저장된 트레이스 | 0 |
| p | Properties | 계정 데이터 객체의 속성과 메타데이터 | 43 |
| q | Queue events | 큐 메시지 배달 일정 | 0 |
| r | Incoming reports | 받은 DMARC·TLS·ARF 리포트 | 0 |
| s | Registry | 설정 객체 | 2765 |
| t | Blobs | 데이터 저장소가 blob 저장소를 겸할 때의 blob | 29 |
| u | System | 스키마 버전, 노드 id | 2 |
| v | Index properties | 값으로 객체를 찾기 위한 개별 속성의 색인 값 | 42 |
| w | Spam samples | 스팸 분류기 학습용 메시지 | 7 |
| x | Telemetry metrics | 저장된 메트릭 | 0 |
| y | Search queue | 검색 인덱스 대기 문서·파티션 상태 | 0 |
| z | Search terms | 내장 전문 검색의 단어 | 164 |

키의 절반 이상(b·g·s 합 10,420)이 **설정**이다. 메일 데이터는 수백 개뿐이다.

## 3. 키 배치 (측정 — 문서에 없음)

문서는 바이트 배치를 적지 않는다. 아래는 덤프에서 읽은 것이고, 확신도를 함께 적는다.
정수는 big-endian 이다(정렬 순서가 숫자 순서가 되도록).

```txt
p  'p' | account u32 | collection u8 | field u8 | document u32          11B   확정
c  'c' | account u32 | collection u8 | field u8 | document u32          11B   확정
m  'm' | index u8    | account u32   | document u32                     10B   확정
l  'l' | account u32 | kind u8       | change id u64                    14B   부분
n  'n' | counter u8  | ...counter 마다 다름 (§6)                              부분
v  'v' | account u32 | collection u8 | field u8 | 값... | document        20/28B 부분
t  't' | 32-byte hash                                                   33B   확정 (해시 종류 미확인)
k  'k' | 32-byte hash | (없음 / 9B / 12B)                                      부분
```

`p` 의 field `0x32` 가 객체 본체다. 메일(collection 0)의 `c` 는 field `0x47`(파싱 메타데이터,
~900B)과 `0x48`(정렬 키, ~50–80B) 두 개.

## 4. 컬렉션 번호와 JMAP id (측정·확정)

| collection | 타입 | 근거 |
|---|---|---|
| 0 | Email | admin 문서 {2,3,15,16,17,18,19,20} = JMAP 메일 8통을 해독한 문서번호와 일치 |
| 1 | Mailbox | admin {0,1,2,3,4,10} = id a,b,c,d,e,k / alice {0,1,2,3,4,7} = a~e,h |
| 3 | Identity | admin {1} = Identity `b` |
| 4 | EmailSubmission | admin {1,5,6} = `b`,`f`,`g` |
| 7 | (계정 수준 값, 문서 0) | 의미 미확인 |
| 8 | Calendar | 값에 "Stalwart Calendar (admin@…)" |
| 10 | AddressBook | 값에 "Stalwart Address Book (admin@…)" |

계정 번호: admin = 1, alice = 2. JMAP accountId `b`, `c` 가 바로 이 숫자다.

**JMAP id 인코딩.** 숫자를 5비트씩 끊어 큰 자리부터, 앞의 0 은 생략, 알파벳은 표준 base32 가
아니라 `abcdefghijklmnopqrstuvwxyz792013` 이다(`a`=0 … `z`=25, `7`=26, `9`=27, `2`=28 …).

```txt
accountId · Mailbox · Identity · Submission · Calendar · AddressBook   = 문서번호
Email id   = (threadId << 32) | 문서번호       b2aaaaas → thread 15, doc 18
threadId   = 그 스레드 첫 메일의 문서번호        thread 15 = doc 15
```

8통 모두 이 식으로 문서번호와 일치했다. 그래서 메일함 id 가 `a`~`k` 처럼 짧고 계정마다 같은 문자열이
반복된다 — 같은 id 는 다른 계정의 별개 객체다([INTEROP-MAIL](INTEROP-MAIL.md)). 클라이언트 캐시 키가
`(account, id)` 인 이유가 서버 쪽에서도 확인된다.

## 5. 값 인코딩 (추정 — 문서에 없음)

메일함 값에서 짧은 이름 `Inbox` 는 값 안에 그대로 있고, 긴 `Axismundi Probe` 는 본문 뒤에
`8f 00 00 00 | f1 ff ff ff`(길이 15 에 최상위 비트, 상대 오프셋 −15)가 붙는다. 짧은 문자열 인라인 /
긴 문자열 상대 포인터라는 rkyv 아카이브의 모양과 같다. 소스로 확인하지 않았으므로 추정이다.

## 6. 카운터 (n) — 값은 little-endian i64

키는 big-endian, **카운터 값은 little-endian** 이다. 이 해석으로 변경 로그와 정확히 맞는다.

```txt
n 00 | account | collection        문서 id 카운터     Email 27 (살아 있는 최대 20), Mailbox 14 (최대 10)
n 01 | account | kind              변경 id 카운터     83 = l(계정1, kind 0) 의 마지막 id. 3·5·6 도 일치
n 02 | account | collection        객체 수            Email 8 — 두 계정 모두 실제 8통
n 03 | account                    쿼터 사용량(바이트로 보임)   4397 / 3893
n 04 | account | mailbox u32       메일함 UID 카운터   Inbox 20, Drafts 7, Sent 6
```

문서 id 카운터가 살아 있는 최대값보다 크다. 지운 id 를 재사용하는지는 이 데이터로 확인하지 않았다.

**변경 로그(l)의 kind.** kind 0·3·5·6 의 마지막 id 가 카운터 01 과 일치한다. kind 1(46건)·251·253 은
kind 0 과 같은 id 범위(2..83)를 쓴다 — 같은 변경 번호 안의 추가 기록으로 보인다. 각 kind 가 어떤
JMAP 타입의 state 에 대응하는지와 JMAP state 문자열(`sie` 등)과의 관계는 **미해독**이다.

## 7. 미확인 목록

- `l` kind 와 JMAP 타입·state 문자열의 대응
- 컬렉션 2·5·6·7 과 11 이후의 타입 (이 서버에 데이터가 없거나 해석 못 함)
- `t` 해시 종류, `k` 꼬리 9B·12B 의 의미
- `v`·`z` 의 값 부분 배치
- 문서 id 재사용 여부

## 8. 재현

**실행 중인 RocksDB 는 다른 프로세스가 열 수 없고, 콘솔은 일관성 검사 아래에서 동작해 데이터를
손상시킬 수 있다(문서).** 그래서 원본이 아니라 사본에만 붙인다.

```powershell
docker stop axismundi-jmap-interop
Copy-Item -Recurse D:\axismundi-interop\stalwart\data D:\axismundi-interop\stalwart\data-snapshot-YYYYMMDD
docker start axismundi-jmap-interop
```

콘솔 명령은 파일로 넘긴다. 셸 `printf`·heredoc 은 `\xff` 를 실제 바이트로 바꿔 콘솔이 UTF-8 오류로
죽는다. 상한 키는 Python 에서 `chr(92)` 로 만든다. `scan` 의 두 키는 같은 서브스페이스여야 한다.

```txt
scan p p\xff\xff…   (서브스페이스마다 한 줄)
exit
```

```powershell
docker run --rm -i --network none `
  -v D:/axismundi-interop/stalwart/data-snapshot-YYYYMMDD:/var/lib/stalwart `
  -v D:/axismundi-interop/stalwart/config:/etc/stalwart:ro `
  stalwartlabs/stalwart:v1 --config /etc/stalwart/config.json --console < commands.txt
```

출력은 `키 : 값` 한 줄씩이고 비 ASCII 바이트는 `\xNN` 이다.

**비밀값.** `d`(디렉터리)에는 자격증명, `s`(설정)에는 비밀 설정이 들어 있을 수 있다. 이 문서에는
그 값을 옮기지 않았다. 사본 디렉터리도 원본과 같은 비밀을 담으므로 저장소 밖에 두고 커밋하지 않는다.
