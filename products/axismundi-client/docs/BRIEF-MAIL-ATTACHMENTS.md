# Brief — 메일 첨부: 썸네일·미리보기·다운로드와 바이트 원장

> **범위**: 코어 + Tauri + GUI. 읽기 창의 첨부 카드(2f526c6 에서 배치만 됨)에 이미지 썸네일, 이미지 미리보기, 기기로
> 저장(다운로드), 시스템 앱으로 열기를 붙이고, 그 바이트를 디스크 캐시(바이트 원장)에 둔다. 규칙은
> [gui/AGENTS.md](../gui/AGENTS.md), 원장 설계는 [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md) "바이트 원장"(2026-11-23 결정),
> 메서드 범위는 [MATRIX-JMAP-MAIL.md](MATRIX-JMAP-MAIL.md).

2026-11-26 작성 및 착수 승인. "소유자 결정", "측정", 아래 착수 옵션이 구현 기준이다. 측정이 지시와
어긋나면 멈추고 보고한다.

## 소유자 결정 (이미 기록된 것)

```txt
UX         첨부 카드는 Bulwark 패턴 — 발신자 아래·본문 위, 개수·전체 용량, 카드마다 유형 아이콘(이미지는 썸네일)·
           이름·용량·다운로드.  본문에 표시된 cid 이미지는 목록에 넣지 않는다(이미 구현)
Files      "파일에 저장"(FileNode/set, 다른 Account 면 Blob/copy)은 JMAP Files 가 생긴 뒤.  이번엔 없다
blob       다운로드는 jmap::blob 경로 하나(cid 와 공유, 나중에 FileNode 도).  업로드 영역 분리 그대로
           (WP Media · JMAP File · AP uploadMedia 섞지 않음).  blob 접근은 Account 단위(accountId + blobId)
원장       DESIGN-MAIL-GUI "바이트 원장": 바이트는 받은 바이트로 직접 계산한 sha-256 으로 한 번만 저장, 서버(apiUrl
           오리진)마다 원장 하나, 참조는 (로그인, Account, Email, blobId) → 해시, 참조 0 인 바이트만 삭제.
           재사용은 **이 로그인이 그 blobId 의 digest 를 서버에서 받은 뒤에만**(같은 서버 다른 로그인이 받아 둔 사실은
           권한이 아니다).  blobId 를 해석하지 않는다, Message-ID 로 묶지 않는다
캐시 순서  blob 은 불변(RFC 8620) — 이 로그인의 참조가 있으면 캐시 먼저, 없을 때만 네트워크.  Bulwark 의 "온라인이면
           네트워크 먼저" 는 따르지 않는다(그건 그쪽 오프라인 설계의 선택)
```

## 착수 옵션

```txt
썸네일     래스터 이미지(png·jpeg·gif·webp·avif·bmp)만, 카드가 화면에 보일 때 받는다(목록 스크롤 중 미리 받지 않음).
           크기 상한(예: 10MB, Bulwark 와 같음) 넘으면 아이콘.  SVG 는 썸네일·미리보기 둘 다 하지 않는다(스크립트를
           품을 수 있음 — 저장만)
클릭       이미지 = 앱 안 미리보기(dialog, 원본 크기·화면 맞춤).  그 밖 = 아무것도 열지 않고 카드의 "다운로드"·"열기"
           버튼만.  "클릭 동작" 설정(Bulwark 의 미리보기/바로 다운로드)은 후속
다운로드   OS 저장 대화상자(파일명 기본값 = 첨부 이름, 정리한 것).  다운로드 폴더 자동 저장은 하지 않는다
열기       캐시의 바이트를 임시 파일로 꺼내 시스템 기본 앱으로(plugin-opener).  **실행 가능한 형식은 "열기" 를 주지
           않는다** — exe·msi·bat·cmd·ps1·vbs·js·jar·scr·lnk·hta 등, 확장자와 Content-Type 둘 다 보고 판단.  저장만
캐시 상한  서버별 원장 1 GiB 초기값, 오래 안 쓴 것부터 정리(LRU). 운영값 실측 및 설정 화면은 후속
열기 범위  최초에는 래스터·PDF·일반 텍스트의 MIME과 확장자가 모두 일치할 때만 열기, 그 외는 저장만
```

## 측정 — Stalwart 1.1.0 (2026-11-26, alice Account c, 읽기만)

```txt
blob capability  urn:ietf:params:jmap:blob — maxSizeBlobSet 7499488, supportedDigestAlgorithms [sha, sha-256, sha-512]
Blob/get         properties ["digest:sha-256","size"] → digest 를 준다.  "[Axismundi] HTML + cid" 메일의 pdf 첨부:
                 서버 digest = 내려받은 41바이트를 직접 sha-256(base64) 한 값과 일치
                 → 원장 규칙("받기 전 digest 로 묻고, 있으면 안 받음") 을 Stalwart 에서 그대로 쓸 수 있다
Blob/get 은 using 에 urn:ietf:params:jmap:blob 이 있어야 한다(없으면 unknownMethod — Blob/copy 에서 실측)
다운로드         downloadUrl(accountId, blobId, type, name) + 인증 — 웹뷰가 직접 못 받는다, 코어가 받는다
교차 Account     Stalwart 는 c 에 올린 blob 으로 b 에 Email/import 를 허용했다(2026-11-25) — RFC 보다 느슨.
                 우리는 기대지 않는다: 참조는 Account 단위, Account 를 넘는 재사용은 하지 않는다
```

## 이미 있는 것 — 읽고 시작

```txt
코어   jmap::blob — download(session, account, blob, type, name, max_bytes), upload, Blob/get·lookup(RFC 9404)
Tauri  mail_part_bytes — cid 인라인 이미지용(Account·메일의 첨부 파트 확인 후 바이트, 메모리만)
GUI    mail-body.component.html — 첨부 섹션(개수·전체 용량), 카드(유형 아이콘·이름·용량), 썸네일·다운로드 미연결
       @tauri-apps/plugin-opener (외부 링크용으로 이미 사용)
저장   메일 캐시 = 프로필별 SQLite(mail/<profile>.sqlite).  원장은 프로필이 아니라 서버 오리진 단위라 별도 저장소
```

## 지시

1. **원장(코어)**: 서버 오리진마다 원장 하나 — 바이트 파일(이름 = sha-256 hex) + 인덱스(해시·크기·마지막 사용) +
   참조 표 (로그인, Account, Email, blobId) → 해시. 위치는 앱 데이터 디렉터리 아래, 프로필 캐시와 분리(구조는 판단해
   보고). 받은 바이트는 직접 sha-256 하고, 서버 digest 를 받았으면 대조 — 다르면 저장하지 않고 오류.
2. **가져오기 순서**(코어 함수 하나 — 썸네일·미리보기·다운로드·열기·cid 가 모두 이걸 쓴다):
   (a) 이 로그인의 (Account, blobId) 참조가 있고 바이트가 있으면 그대로.
   (b) 없으면 `Blob/get digest:sha-256` 를 묻는다 → 그 해시의 바이트가 원장에 있으면 참조만 추가하고 받지 않는다.
   (c) 없으면 다운로드 → 직접 해시 → (b) 의 digest 와 대조 → 저장 + 참조.
   digest 를 못 받으면(capability 없음·실패) 바로 (c). 크기 상한은 호출자가 넘긴다.
   cid 인라인 이미지(mail_part_bytes)도 이 경로로 옮긴다 — 지금은 메모리만.
3. **Tauri 명령**: 바이트를 웹뷰로 넘기는 건 썸네일·미리보기용 래스터 이미지뿐(지금 cid 처럼). 다운로드·열기는
   웹뷰를 거치지 않고 Rust 가 원장 파일에서 바로 저장·임시 파일로 꺼낸다.
4. **파일명 정리**(발신자가 정한 이름은 신뢰하지 않는다): 경로 구분자·`..`·제어 문자 제거, Windows 예약 이름
   (CON·PRN·AUX·NUL·COM1… )·끝의 점·공백 처리, 길이 제한, 비면 `attachment`. 저장 대화상자 기본값과 임시 파일 이름에
   같은 함수를 쓴다. 순수 함수 + 테스트.
5. **GUI 카드**: 썸네일(보일 때만 — IntersectionObserver), 카드의 다운로드·열기 버튼(열기는
   실행 형식이면 비활성 + 이유), 이미지 클릭 → 미리보기 dialog. 진행 중·실패 상태를 카드에 표시. 다운로드 완료 스낵바에
   "폴더 열기" 는 넣지 않는다(후속).
6. **접근 재확인**(Codex 지적, 2026-11-26): 캐시 적중은 바이트가 있다는 뜻이지 지금 읽을 권한이 있다는 뜻이 아니다.
   - 썸네일·미리보기: 그 Email 이 이 로그인의 동기화된 캐시에 지금 있을 때만 원장에서 꺼낸다(공유가 회수돼 동기화에서
     메일이 빠지면 참조도 지운다 — 지시 7).
   - 다운로드·열기: 원장에 있어도 먼저 `Blob/get digest` 한 번(바이트 없이)으로 이 로그인의 접근을 확인한다.
     digest 미지원 서버는 인증된 다운로드가 성공한 바이트만 사용한다. 거부·notFound 면 참조를 지우고 열지 않는다.
     오프라인이면 다운로드·열기는 막고 이유를 표시.
7. **수명**: 메일이 캐시에서 사라지거나(파괴·Account 제거·로그아웃·연결 삭제) 하면 그 참조를 지우고, 참조 0 인 바이트는
   정리 대상. 상한 초과 시 LRU 로 정리하되 참조가 있는 것도 지울 수 있다(다시 받으면 된다).

## 하지 않는 것

파일에 저장(FileNode), Account 간 Blob/copy, 첨부 이름 바꾸기, 클릭 동작·파일명 템플릿·캐시 상한 설정 화면, PDF 등
이미지 외 미리보기, 여러 첨부 zip 다운로드, 작성 창의 첨부 업로드(작성 2단계), 오프라인 전용 동작, 캐시 암호화.

## 검증

```txt
cargo test   원장: 가져오기 (a)(b)(c) 분기 — digest 일치 시 다운로드 0회(모의 전송 카운트), digest 불일치 시 저장 안 함,
             다른 로그인이 받아 둔 바이트를 digest 확인 없이 내주지 않음; 파일명 정리; 참조 삭제·LRU
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib    IPC fixture
node --test "gui/src/**/*.spec.ts"   썸네일 대상 판정(타입·크기·SVG 제외), 실행 형식 판정
npm --prefix gui run build
```

창에서(원격 디버깅 CDP — gui/AGENTS.md), 기존 테스트 메일(지우지 않음: admin Inbox "[Axismundi] Image attachment test"
jpg, alice Inbox "[Axismundi] HTML + cid" pdf, admin Inbox "이 날의 추억" cid 5):

1. jpg 카드에 썸네일, 클릭 → 미리보기. pdf 카드는 아이콘, 다운로드 → 저장 대화상자, 열기 → 시스템 앱.
2. 같은 메일을 다시 열거나 앱을 재시작해도 썸네일이 **네트워크 요청 없이** 뜬다(캐시 먼저 — 요청 수 측정).
3. 다른 로그인(admin ↔ alice)이 같은 바이트를 가진 경우: digest 확인 요청 1회 후 다운로드 0회.
4. 원장 디렉터리에 같은 바이트가 한 번만 있다(해시 파일명 수).

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.

## 구현 결과 메모 (2026-11-26)

시스템 앱으로 열기(허용 목록 — pdf·래스터 이미지·txt, MIME·확장자 일치)는 창에서 재현하지 않았다(미검증). 보내는 첨부·
에디터의 선행 조건은 아니다. 공유 권한 회수는 원장 단위 테스트로만 확인.
