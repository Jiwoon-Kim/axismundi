# JMAP FileNode — 기준 초안과 Stalwart 실측

2026-11-28. Files 앱 착수 전 기록. 코드 주석·문서의 출처 표기는 아래 "기준"을 쓴다.

## 기준

```txt
문서     draft-ietf-jmap-filenode-22 "JMAP File Storage extension" (2026-11-18, B. Gondwana).  Active I-D, jmap WG,
         Standards Track 의도, "Updates: 8620 (if approved)".  **RFC 번호 없음** — 주석에는 RFC 번호 대신
         `draft-ietf-jmap-filenode-22 §x.y` 로 적고, RFC 가 나오면 번호로 일괄 바꾼다(지어낸 번호 금지)
capability  urn:ietf:params:jmap:filenode.  Session capabilities 값은 MUST 빈 객체, 실제 값은 accountCapabilities
메서드   FileNode/get(fetchParents) · /changes · /set(onDestroyRemoveChildren · onExists · compareCaseInsensitively) ·
         /copy · /query(depth) · /queryChanges.  Direct HTTP PUT/PATCH(webWriteUrlTemplate, If-Match MUST) · Blob/convert
         archive·extract 확장 · Blob/lookup(RFC 9404) 참조
객체     공통 @type(immutable) · id · parentId · name(NFC, 형제 간 유일) · created · modified · accessed · changed(server-set)
         Directory: isSubscribed · myRights(FileNodeRights) · shareWith · role(root·home·temp·trash·documents·…)
         File: blobId(생성 시 필수, 갱신=내용 교체) · size · mediaType(기본 application/octet-stream) · executable
         SymLink: target.  다른 타입의 속성은 null 이 아니라 생략
정렬     §3.6.2 "Servers MUST support 'id' and 'name'" + tree · nodeType · 속성명
삭제     자식 있으면 nodeHasChildren(onDestroyRemoveChildren 로 하위 전체).  role 있는 Directory 는 삭제·이름변경·이동
         forbidden.  trash 는 role 하나일 뿐 자동 생성 규정 없음
```

## 인용하는 다른 문서 (2026-11-28 확인)

```txt
JMAP Sharing     RFC 9670 (2024-11).  filenode-00(2024-11-22)은 아직 draft-ietf-jmap-sharing-09 로 적었지만 -22 는 RFC 9670
Quotas           RFC 9425.   Contacts  RFC 9610.   Metadata  draft-ietf-jmap-metadata-05(초안)
Blob 관리        -22 는 [JMAP-BLOBEXT] = draft-ietf-jmap-blobext-04 를 인용한다 — **RFC 9404 를 대체할 후속 초안**,
                 capability 가 urn:ietf:params:jmap:blob2 (RFC 9404 는 urn:ietf:params:jmap:blob).  Blob/get·Blob/lookup 도
                 새 capability 아래 다시 정의하고, 한 요청의 using 에 blob 과 blob2 를 함께 넣으면 안 된다.
                 Stalwart 는 blob 만 광고 → 우리 코드는 RFC 9404 경로(blob)로 쓰고 주석도 RFC 9404.  blob2·Blob/convert 는
                 서버가 blob2 를 광고할 때 별도 작업
```

## Stalwart 1.1.0 실측 (admin Account b, 시험 폴더 생성 후 삭제 — 남은 노드 0)

```txt
capability      Session 값 {} (MUST 지킴).  Account 값: maxFileNodeDepth 64 · maxSizeFileNodeName 255 ·
                forbiddenNameChars "/<>:\"\\|?*" · supportedNodeTypes Directory/File/SymLink · mayCreateTopLevelFileNode ·
                caseInsensitiveNames false · webTrashUrl/webUrlTemplate/webWriteUrlTemplate 모두 null
                + 초안에 없는 forbiddenNodeNames(. .. CON PRN AUX NUL COM0-9 LPT0-9 — Windows 예약 이름, 벤더 확장)
정렬            fileNodeQuerySortOptions 에 "id" 없음, sort id → unsupportedSort.  §3.6.2 MUST 위반(Stalwart 쪽 문제).
                name·tree·size 는 됨.  tree: 이름순 + 디렉터리 바로 뒤에 그 자식
초기 상태       새 Account 에 노드 0 — root·home·trash role 디렉터리를 만들지 않는다(휴지통 없음)
Quota/get       list 비어 있음 — 이 서버는 Quota 를 내주지 않는다(용량 표시 불가가 정상 상태)
생성            @type 으로 받음(type 아님).  같은 요청 안 "#dir" 생성 참조 됨.  created 응답에 blobId 가 없다
blobId 바뀜     업로드 blobId(e…) ≠ 저장된 File 의 blobId(c…) — 서버가 첨부 시 다시 매긴다.  Blob/lookup 은 저장된
                blobId 로만 매칭(업로드 id 로는 FileNode [] ).  → 생성 뒤 FileNode/get 으로 blobId 를 다시 읽어야 한다
mediaType       생성 때 안 주면 application/octet-stream — 업로드 Content-Type 을 물려받지 않는다.  클라이언트가 명시
이름 충돌       기본 alreadyExists + existingId.  onExists "rename" → "a (2).txt"
이동            update parentId 로 됨(changed 갱신)
삭제            자식 있으면 nodeHasChildren("Cannot delete non-empty folder."), onDestroyRemoveChildren 로 하위까지 destroyed
myRights        Directory 에 초안의 6개 + maySetMetadata(JMAP Metadata 확장)
다운로드        downloadUrl 로 내용 그대로
changes         생성·이동이 created/updated 로 잡힘
```

## Files 앱에 주는 결론

2026-11-28 재검증: admin의 `isTopLevel: true` 조회, 임시 폴더 내 업로드 → 저장된 노드 재조회 →
폴더 목록 → 다운로드 바이트 일치까지 통과했다. 업로드와 저장 blobId는 달랐다. 임시 폴더는 하위 포함 삭제했고
admin 할당량 설정은 변경하지 않았다. `Quota/get`은 `ids: null`로 호출해도 빈 목록이었다.

- 이름 검증은 capability 로: forbiddenNameChars·maxSizeFileNodeName·(있으면) forbiddenNodeNames, 형제 간 유일은 서버
  alreadyExists 를 받아 onExists 선택("rename" 제안)으로.
- 업로드 = uploadUrl → FileNode/set create(mediaType 명시) → FileNode/get 으로 blobId·size 확정. 업로드 blobId 를 저장
  blobId 로 착각하지 않는다(첨부 원장·Blob/lookup 은 저장 blobId 기준).
- 휴지통은 role "trash" 디렉터리가 있을 때만. 없으면 삭제는 영구 — 확인 UI 필요(Stalwart 는 없음).
- 용량은 Quota/get 이 값을 줄 때만 표시. Stalwart 는 빈 목록.
- 정렬 기본은 nodeType + name(서버가 지원할 때), "id" 는 Stalwart 가 거절하므로 쓰지 않는다.
