# Brief — Files 첫 슬라이스 (JMAP FileNode)

> **범위**: 코어(Rust) + Tauri 명령 + GUI. 기준 문서는 draft-ietf-jmap-filenode-22, 실측은
> [INTEROP-FILENODE.md](INTEROP-FILENODE.md)(같은 날, 다시 재지 않는다). 규칙은 [gui/AGENTS.md](../gui/AGENTS.md).

2026-11-28 작성. "소유자 결정"·"있는 것"은 확정, "지시"는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
순서     Files 먼저(OAuth 는 뒤 — AP C2S 첫 구현은 WebFinger + 앱 패스워드로 충분, 2026-11-28)
이유     업로드 미디어 UI(파일 선택·업로드 대기열·진행·실패·재시도·미리보기)를 나중에 AP C2S 가 재사용한다.
         재사용하는 건 UI 부품뿐 — 저장·권한·공개 URL 은 FileNode 와 AP(uploadMedia·OP 경로)가 각자 맡는다.
         JMAP Files 를 AP 미디어 저장소로 쓰지 않는다(업로드 3영역 분리 결정 그대로)
첫 범위  폴더 탐색 → 파일 선택 → 업로드(진행·실패·재시도) → 목록에서 확인 → 다운로드.  **합격 기준은 이 흐름뿐**:
         올리고, 저장된 노드를 다시 읽고, 내려받는다
같이 함  RFC 9425 Quota 표시(완료 조건 아님) · 2단계 RFC 9404 Blob/lookup(파일 상세의 사용처)
빼는 것  OAuth, Blob 전용 화면·중복 정리, Blob/upload, 공유(shareWith), 휴지통 role, Direct HTTP write, Blob/convert,
         SymLink 만들기
Experimental  초안 기반이라 Files 는 실험 기능으로 **표시만** 한다 — 기본 켜짐, 끄는 설정 없음(클라이언트는 서버가
         광고한 것을 보여 줄 뿐, 2026-11-28 소유자 결정).  Files 화면 제목 옆 "Experimental" 칩, Settings JMAP 상세의
         filenode 행에 "Experimental · draft-ietf-jmap-filenode-22".  광고됐다고 특정 리비전과 호환된다고 단정하지 않고,
         검증한 리비전은 이 문서·테스트에 기록.  Quota·Blob 은 RFC 라 실험 표시 대상 아님
코어 구조 통합하되 독립 모듈 `jmap::filenode`(플러그인 로더 없음).  Session·요청·Blob 업로드/다운로드는 기존 경로.
         초안이 바뀌어도 메일·연락처를 건드리지 않게 FileNode 요청·응답·쓰기 흐름은 그 모듈 안에
출처 표기 주석·문서에 `draft-ietf-jmap-filenode-22 §x.y`.  RFC 번호가 나오면 일괄 교체(지어낸 번호 금지)
```

## 있는 것 (재사용)

```txt
코어 업로드·다운로드   core/src/jmap/blob.rs  Client::upload / Client::download (메일 첨부가 쓰는 경로)
네이티브 파일 선택·드롭 gui/src-tauri/src/compose_files.rs — OS 선택기·창 드롭만 등록, 웹뷰엔 핸들만(경로 안 넘김),
                     maxConcurrentUpload 슬롯, 슬롯 뒤 핸들 재조회, 제거 시 release.  이름이 "Compose" 지만 Files 도 같은
                     레지스트리를 쓴다(도메인 중립 이름으로 옮길지는 구현자가 판단·보고 — 둘로 복제 금지)
첨부 저장             메일 받은 첨부의 저장·열기(blob_ledger, 다이제스트 재확인) — 다운로드 저장 대화상자 흐름 참고
GUI 업로드 상태 규칙   gui/src/app/mail/compose-attachments.ts (uploading/uploaded/failed, 합계 한도 사전 검사, 재시도)
Session capability    Settings JMAP 상세가 이미 filenode capability 를 읽고 그린다(jmap-session-view.ts)
```

## 지시

0. **코어 `jmap/filenode.rs`**: FileNode/get(fetchParents) · /query(filter parentId, sort) · /set(create · update parentId ·
   name · destroy, onExists · onDestroyRemoveChildren) · /changes. 객체는 `serde_json::Value` 로 보존(모르는 필드 유지 —
   Stalwart 는 초안 밖 필드를 낸다). 정렬은 capability 의 `fileNodeQuerySortOptions` 에 있는 것만 보낸다(Stalwart 는
   초안 MUST 인 "id" 도 거절). `unsupportedSort` 등 서버 오류는 그대로 올린다.
1. **업로드 = 세 단계, 완료는 마지막**: uploadUrl → `FileNode/set create`(`@type: "File"`, `parentId`, `name`,
   **`mediaType` 명시** — 안 주면 octet-stream) → `FileNode/get` 으로 **저장된 blobId·size 확정**. 업로드 blobId 를 저장
   blobId 로 쓰지 않는다(Stalwart 는 다시 매긴다). 이름 충돌(`alreadyExists`)은 사용자에게 "덮어쓰기 / 이름 바꿔 저장"
   을 묻고 `onExists` 로 다시 보낸다(기본은 묻기 — 조용히 rename 하지 않는다).
2. **Tauri 명령**(얇은 래퍼, 반환은 Rust `Serialize` + 공용 fixture `gui/src/app/files/files.fixture.json`): 폴더 목록(자식,
   정렬), 노드 하나, 폴더 만들기, 이름 바꾸기, 이동, 삭제(하위 포함 여부 명시), 업로드(핸들 → 위 세 단계), 다운로드
   (저장 대화상자 → 파일). 파일 선택·드롭은 compose_files 레지스트리 재사용. 연결은 기존 Connection 처럼 명령마다
   connectionId·accountId(없으면 filenode primary).
3. **이름 검증은 capability 로**(클라이언트 사전 검사 + 서버 최종): `forbiddenNameChars`, `maxSizeFileNodeName`(UTF-8
   바이트), 있으면 `forbiddenNodeNames`(Stalwart 벤더 필드 — NON-STANDARD 주석), `.`·`..`, `/`. 형제 간 유일은
   `caseInsensitiveNames` 에 따라 서버 판정을 따른다.
4. **GUI — Files 목적지**: 연결 Account 가 filenode 를 가질 때만 레일·앱 메뉴에. 배치는 Contacts 와 같은 결(공용
   Scaffold, 두 인터페이스 레이아웃 유지): 왼쪽 폴더 트리(sidenav), 가운데 폴더 내용 표(이름 · 크기 · 종류 · 수정일,
   디렉터리 먼저 — 서버 정렬 `nodeType`+`name` 지원 시 서버 순서, 아니면 그 사실을 보이고 이름순), 위에 경로 브레드크럼.
   행: 폴더는 열기, 파일은 다운로드·이름 바꾸기·이동·삭제.
5. **업로드 대기열 UI — 재사용 부품으로**: `shell/` 또는 공용 위치에 업로드 대기열 컴포넌트(파일명 · 크기 · 진행 ·
   실패 이유 · 재시도 · 제거)와 상태 모델(compose-attachments 의 규칙을 도메인 중립으로 — 상태 전이와 합계 검사).
   Files 는 이 부품 + "FileNode 에 저장" 어댑터, 메일 작성은 기존 그대로(이번에 옮기지 않음 — 옮길지 보고만).
   드래그 앤 드롭은 Files 화면에서도 창 드롭 이벤트로.
6. **삭제는 영구**: 휴지통 role 이 없으면(Stalwart) 확인 대화상자에 "되돌릴 수 없음"과 하위 개수. role "trash" 가 있는
   서버라면 그쪽 이동을 기본으로(이번엔 감지·표시만, 서버가 없으니 검증은 다음).
7. **용량 — RFC 9425, 조회와 실패를 분리**:
   - 저장(FileNode/set)이 `overQuota` 면 `Quota/get` 결과와 무관하게 "할당량 초과"로 표시한다. 업로드가 `maxSizeUpload`
     를 넘는 건 **파일 하나의 업로드 크기 제한**이지 공간 부족이 아니다 — 두 오류를 다른 문구로.
   - 사이드바 사용량은 그 Quota 의 `types`(RFC 9425 §4.1)에 FileNode 가 들어 있는 것만. Email 등과 함께 적용되면
     "공유 할당량"이라고 밝힌다(Files 만의 사용량처럼 쓰지 않는다). 상태 셋: 사용량 있음 / 적용되는 할당량 없음 / 조회 실패.
     어느 상태든 Files 는 계속 쓸 수 있다.
8. **2단계 — 사용처(RFC 9404 Blob/lookup)**: 파일 상세에서 **저장된 blobId** 로만 조회(업로드 id 는 매칭 안 됨, 실측).
   결과가 비면 "이 Account 의 조회한 유형에서 찾지 못함" — "어디에서도 안 씀"이라고 쓰지 않는다.
9. **상태를 숨기지 않는다**: 빈 폴더 · 로딩 · 오류 · 업로드 중 · 결과 모름(응답 유실 — 메일처럼 그 노드 쓰기 잠금 후 다시
   읽기)을 구분해 보인다.

## 하지 않는 것

위 "빼는 것" + 미리보기(이미지 썸네일·텍스트) — 대기열 부품의 자리만 만들고 내용은 다음, 오프라인 캐시(첫 버전은 온라인
조회, 캐시는 Mail·Contacts 처럼 나중), 공유 폴더 탐색(다른 Account 의 FileNode).

## 다음 — 메타데이터 (Files 기본 흐름 뒤, 2026-11-28 소유자 결정)

JMAP Metadata(초안, capability urn:ietf:params:jmap:metadata) — Stalwart 는 dataTypes 에 FileNode 를 광고한다(maxDepth 8,
supportsPrivate, supportsVendorNamespaces). FileNode 초안 §10 의 xattr 네임스페이스도 여기에 얹힌다. 설명·별표·색 같은 사용자
속성은 이 층으로. 공개 링크·버전·썸네일은 FileNode 초안 밖이라 그때 따로 정한다. AP 미디어 속성(대체 텍스트·민감 표시·초점)은
FileNode 메타데이터가 아니라 AP·OP 쪽 모델(업로드 3영역 분리).

## 검증

```txt
cargo test --workspace                 filenode 요청 형태(mock), Tauri fixture
node --test "gui/src/**/*.spec.ts"     이름 검증, 대기열 상태 전이, 정렬 폴백
npm --prefix gui run build
```

Quota 표시·`overQuota`·`maxSizeUpload` 문구는 fixture 로 먼저. 실서버 Quota 검증은 **admin 계정**에(소유자가 확인하기
편하게, 2026-11-28 승인): 한도는 현재 사용량보다 넉넉히 — 메일·연락처 시험을 막지 않을 만큼, 설정 전 값을 기록하고 되돌릴지는
검증 뒤 소유자가 정한다. admin 에서 `overQuota` 를 일부러 일으키지 않는다(실패 경로는 fixture). 한도를 걸어도 `Quota/get`
이 비면 그 상태를 기록하고 Files 를 막지 않는다.
Stalwart(admin)에 **시험 폴더를 만들어** 그 안에서만: 폴더 만들기 → 파일 두 개 업로드(하나는 일부러 실패 — 금지 문자 이름 등) →
재시도 → 목록에 저장된 blobId 로 보이는지(업로드 id 아님, FileNode/get 대조) → 같은 이름 업로드 시 덮어쓰기/이름 바꾸기
선택 → 이동 → 다운로드 바이트 일치 → 폴더 삭제(하위 포함 확인) → 남은 노드 0. 창에서(CDP) 1280 · 400, 두 인터페이스
레이아웃. 끝나면 바꾼 파일, 본 것, 못 본 것. 커밋은 승인 뒤.
