# Brief — Contacts 첫 슬라이스 (주소록 목록 → 연락처 상세 → 검색 → 생성·수정)

> **범위**: 코어(Rust) + Tauri 명령 + GUI(Angular). JMAP Contacts([RFC 9610](https://www.rfc-editor.org/rfc/rfc9610)),
> 데이터는 JSContact([RFC 9553](https://www.rfc-editor.org/rfc/rfc9553) + [RFC 9982](https://www.rfc-editor.org/rfc/rfc9982),
> version `"2.0"`). 상호운용 검증은 메일처럼 **Stalwart + Bulwark 먼저**. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md),
> 셸은 [BRIEF-MAIL-LAYOUT-MODES.md](BRIEF-MAIL-LAYOUT-MODES.md)(Scaffold).

2026-11-26 작성. "소유자 결정"·"있는 것"·"Stalwart 실측"은 확정, "지시"는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
순서      주소록 목록 → 연락처 상세 → 검색 → 생성·수정.  메일 받는 사람 자동완성은 이 다음(실데이터로 검증하려고 Contacts 먼저)
상호운용  Stalwart(localhost:18881) + Bulwark(localhost:3000) 에서 먼저.  WP Contacts 서버는 아직 ContactCard 메서드를 안 냄
남겨 둠   메일의 Bulwark 시각 비교·보내는 첨부 예외 경로 실창 검증은 완료 처리하지 않는다.  리치 텍스트 엔진 비교는 별도 작업
```

## 있는 것

```txt
코어   core/src/jmap/contacts.rs (733줄, INTEROP-CONTACTS.md): 프로필별 SQLite 캐시(JSON 이 권위, SQL 은 색인),
       AddressBook/get · ContactCard query+get 전체 동기화 · /changes 증분 · cannotCalculateChanges 재구축,
       수정은 PatchObject 를 pending 에 쌓고 ifInState 로 제출(거절되면 남김).  **생성·삭제 큐 없음**, 푸시 없음
Tauri  Contacts 명령 없음(GUI 에서 아직 못 씀)
GUI    없음.  레일 주석에 "Contacts and Calendar join when their screens are built"
WP     axismundi-contacts: AddressBook 을 JMAP Account 에 연결한 저장소·서비스까지(IMPLEMENTATION-JMAP-ADDRESSBOOKS.md).
       ContactCard 메서드·HTTP transport·capability 광고 없음 — 클라이언트가 붙을 서버는 지금은 Stalwart 뿐
```

## Stalwart 1.1.0 실측 (2026-11-26, admin Account `b`)

```txt
능력      accountCapabilities contacts = {maxAddressBooksPerCard: 10, mayCreateAddressBook: true}
주소록    b "Stalwart Address Book (admin@example.org)" isDefault · isSubscribed
          h "Trusted Senders"  isDefault false · isSubscribed **false** — 서버가 만든 것, 목록에서 구독 안 한 주소록 처리 필요
시드      ContactCard g = 소유자 제공 jscontact.json(주소록 b).  **지우지 말 것**(UI·Bulwark 비교 기준 데이터)
검색      ContactCard/query filter: text·name·name/given·name/surname·email·note·onlineService·kind·inAddressBook 모두 동작,
          text 는 부분 일치("kimjiwoon75" 맞음 — 메일 text 의 토큰 전체 일치와 다름).  phone "01027421672" 는 안 맞음
          (저장값 tel:+8210…).  sort name/surname → unsupportedSort, created 는 됨
```

**왕복 손실** — 보낸 Card 와 ContactCard/get 결과 비교(Stalwart 가 내부적으로 vCard 로 바꿔 저장하는 것으로 보임, 원인 미확인):

```txt
version                 "2.0" → "1.0"
name.defaultSeparator   ""(한국어 이름 붙여 쓰기) 사라짐
localizations           ko-Hani → 키가 "ko-hani" 로 소문자화, 패치 3개가 name/components 배열 하나로 합쳐짐(defaultSeparator 손실)
                        en: name 로컬라이즈 사라짐, titles/projectLeader 사라짐, titles/siteOwner 값이 "Project Leader" 로 **바뀜**,
                            organizations/axismundi 가 로컬라이즈에서 빠져 **기본 Card 의 organizations 로 올라감**,
                            addresses/home/defaultSeparator 사라짐
                        ja-Kana · zh-Hant-TW 통째로 사라짐
anniversaries           date 에 "@type": "PartialDate" 추가(무해)
```

참고: 시드 파일의 `localizations.en["organizations/axismundi"]` 는 기본 Card 에 없는 id(기본은 `o1`)를 가리킨다 — 원본 쪽
문제이기도 하다. 위 손실 중 이것 말고는 서버 쪽이다.

## 지시

0. **손실 서버 전제**: 서버가 받은 Card 를 그대로 돌려준다고 가정하지 않는다.
   - 수정은 **바뀐 경로만 PatchObject 로**(코어 `stage_update` 경로). Card 전체를 다시 보내지 않는다 — 전체 교체는 위 손실을
     한 번 더 일으키고, 우리가 모르는 속성(unknown members)도 지운다.
   - 저장 뒤 화면은 서버가 돌려준 값(다음 동기화 결과)을 보여 준다. 보낸 값으로 덮어 그리지 않는다.
   - 손실 자체를 고치거나 사용자에게 경고하는 UI 는 이번 범위 밖 — INTEROP 문서에 기록만.
1. **Tauri 명령**(얇은 래퍼, 반환 형식은 Rust `Serialize` + 공용 fixture — 메일과 같은 규칙):
   주소록 목록, 주소록별 카드 목록(표시 이름·대표 이메일·아바타 URI 정도의 행), 카드 하나(JSON 그대로), 동기화(전체/증분),
   검색(서버 `ContactCard/query` filter `text`, 결과 id → 캐시에서 행), 생성, 수정 제출. 캐시 파일 위치·바인딩은 메일 캐시가
   정하는 방식에 맞춘다(먼저 확인해서 보고).
2. **생성**: 코어에 생성 큐가 없으므로 첫 버전은 온라인에서 `ContactCard/set create` 직접(메일 초안 생성과 같은 결: 응답의
   id 를 받은 뒤에만 목록에 넣는다). `addressBookIds` 는 고른 주소록(기본 = `isDefault`). `version` 은 `"2.0"`, `uid` 는
   `urn:uuid:` 를 클라이언트가 만든다(RFC 9982 — uid 를 지어내면 안 되는 건 **변환** 때 이야기, 새 Card 는 우리가 만든다 —
   WP `docs/REFERENCE-JSCONTACT-UID.md` 확인 후 어긋나면 보고).
3. **GUI — Contacts 목적지**: 연결이 `urn:ietf:params:jmap:contacts` 를 지원할 때만 레일(·앱 메뉴)에 나온다. 화면은 메일과
   같은 Scaffold list-detail: 주소록 패널(구독 안 한 주소록은 접힌 "기타"로) / 카드 목록 / 상세. 두 인터페이스 레이아웃
   모두에서 메일과 같게 동작(Search app bar 면 검색은 창 위 바, 상세는 목록 자리).
4. **상세**: 데이터 확인 표면이라 `dl > dt + dd`(WP Contacts dl 경계 결정과 같은 결). 첫 버전에 보이는 것 — name(full,
   없으면 components 조립), emails, phones, addresses, organizations·titles, onlineServices, links, anniversaries, notes,
   keywords. media 는 아바타 하나(원격 이미지는 메일처럼 기본 차단·보이기 선택). localizations 는 "다른 언어" 로 접어서
   언어 태그별 원문 값만(패치 적용 렌더링은 다음). HTML 로 그리지 않는다.
5. **검색**: 목록 pane 위 search bar 를 메일과 같은 컴포넌트 결로(메일 search bar 를 도메인 중립으로 꺼낼 수 있으면 꺼내고,
   어려우면 보고). 결과는 주소록 경계를 넘는 하나의 목록. 검색어는 디스크·URL 에 남기지 않는다(메일과 같음).
6. **수정 폼**: 첫 버전 편집 대상은 name(full·given·surname), emails, phones, organizations 이름, notes. 나머지 속성은 화면에
   보이되 편집하지 않고, 패치에 들어가지 않는다. 편집 중 서버 변경(ifInState 거절)은 코어처럼 pending 유지 + "서버에서
   바뀜, 다시 불러오기" 한 줄.

## 하지 않는 것

삭제(JMAP Contacts 에 휴지통 없음 — 영구 삭제라 확인 UI 와 함께 다음), 그룹(`kind: "group"`·members), keywords 라벨 관리,
주소록 생성·공유, 생일 캘린더, vCard 가져오기·내보내기, 메일 받는 사람 자동완성, 로컬라이즈 패치 적용 렌더링, Stalwart 손실 보정.

## 검증

```txt
cargo test --workspace                 코어·Tauri 형식 fixture
node --test "gui/src/**/*.spec.ts"     표시 이름 조립, 행 변환, 패치 생성(바뀐 경로만)
npm --prefix gui run build
```

창에서(CDP), **크롬의 Bulwark 연락처 화면과 나란히**: 시드 카드 g 목록·상세, 검색("지운"·"kimjiwoon75"·"gmail.com"),
새 카드 만들기(이름 + 이메일) → Bulwark 에서 보이는지, Bulwark 에서 고친 것이 우리 쪽 증분 동기화로 들어오는지, 우리가 이름
하나 고쳤을 때 g 의 나머지 속성(특히 localizations·notes·media)이 패치 전과 같은지(ContactCard/get 전후 비교). 만든 테스트
카드는 검증 뒤 지우되 g 와 기존 데이터는 건드리지 않는다.

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
