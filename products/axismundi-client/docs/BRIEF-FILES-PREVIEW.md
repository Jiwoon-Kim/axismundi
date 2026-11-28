# Brief — Files 미리보기 (`files_preview`)

> **범위**: Tauri 명령 + (필요하면) 코어. GUI 는 Claude 가 붙인다. 앞 문서는
> [BRIEF-FILES-FIRST-SLICE.md](BRIEF-FILES-FIRST-SLICE.md), 실측은 [INTEROP-FILENODE.md](INTEROP-FILENODE.md),
> 규칙은 [gui/AGENTS.md](../gui/AGENTS.md).

2026-11-28 작성. "소유자 결정"·"있는 것"은 확정, "지시"는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
목적       상세 패널의 이미지 미리보기, 그 다음 그리드 썸네일.  라이트박스는 미리보기 위에 나중에 붙인다
완료 기준  **상세 패널에서 이미지 한 장을 안전하게 띄우고, 실패하면 기본 아이콘으로 돌아온다.**  그리드·라이트박스는
           이 기준에 들어가지 않는다
두 변형    같은 명령에 variant: "thumbnail" | "preview".  thumbnail 은 그리드용 작은 이미지(긴 변 256px 안팎),
           preview 는 상세용(긴 변 1600px 안팎).  카드마다 원본을 받아 CSS 로 줄이는 방식은 쓰지 않는다
           그리드는 화면에 들어온 항목만 요청한다(GUI 몫) — 명령은 동시 요청 수를 스스로 제한한다
아이콘 ≠ 허용  GUI 의 fileKind 는 확장자로 이미지 아이콘을 고를 수 있다(표시용 추정).  미리보기 허용은 그것과 별개다:
           **지원하는 래스터 형식이고, 실제 디코딩이 성공한 경우만** 미리보기를 만든다.  mediaType·확장자는 시도할지를
           고르는 힌트일 뿐 판정이 아니다
기본 아이콘  불러오기 전·지원 안 함·너무 큼·실패 — 모두 GUI 가 같은 fileKind 아이콘을 보인다.  명령은 이유를 구분해
           돌려준다(GUI 가 상세에 한 줄로 적는다)
범위 밖    SVG(스크립트·외부 참조), PDF·동영상 첫 프레임, 애니메이션 재생, HEIC/AVIF(디코더 확인 뒤 별도), 오디오 파형,
           라이트박스, 공유 링크, Blob/lookup
```

## 안전 요구 (명령이 지킨다)

```txt
접근 재확인  요청마다 요청한 connection·Account 에서 해당 노드를 FileNode/get 으로 다시 읽는다.  File 이 아니거나,
             없거나, blobId 가 캐시와 다르면 캐시를 쓰지 않는다.  **blobId 만으로 캐시를 공유하지 않는다**
             (같은 바이트라도 다른 연결·Account 의 요청에 내주지 않는다)
캐시 키      (connectionId, accountId, nodeId, blobId, variant).  내용이 바뀌면 blobId 가 바뀌어 저절로 무효
재인코딩     원본 바이트를 웹뷰에 넘기지 않는다.  Rust 에서 디코딩 → 축소 → 새로 인코딩(PNG 또는 WebP)한 결과만
             넘긴다.  메타데이터(EXIF·GPS·ICC 외 청크)는 따라가지 않는다
디코딩 한도  원본 크기 상한(예: 20 MB — 넘으면 tooLarge, 받지도 않는다)과 **별개로 디코딩 픽셀 수 상한**
             (예: 40 MP, 헤더의 가로×세로로 디코딩 전에 판정 — 작은 파일도 풀면 메모리를 크게 쓴다) + 디코더 할당
             상한(Limits).  시간이 오래 걸리면 실패로.  첫 프레임만(GIF·애니메이션 WebP)
지원 형식    PNG, JPEG, GIF(첫 프레임), WebP(정지).  나머지는 unsupported
웹뷰 참조    웹뷰에는 **미리보기 전용 불투명 참조**만 넘긴다 — 로컬 파일 경로·asset 프로토콜 범위를 열지 않는다.
             권장: 전용 URI scheme(예: `axismundi-preview://<token>`)을 등록하고, token 은 앱 메모리의 등록부에서
             (캐시 항목)으로만 풀린다.  token 은 추측 불가능하고, 연결 삭제·로그아웃·앱 종료로 무효.
             **발급할 때뿐 아니라 쓸 때도 검사한다**: scheme 요청 시점에 그 연결이 아직 로그인 상태인지 다시 보고,
             로그아웃한 연결의 token 은 폐기한다.  token 은 발급한 웹뷰(창 label)에 묶여 다른 웹뷰에서 재사용하면
             거절한다.  scheme 응답은
             image/png|webp 만, Content-Type 고정.  (지금 tauri.conf.json 의 csp 는 null — CSP 를 켤 때 img-src 에 이
             scheme 을 넣는다.  CSP 변경은 이 작업 범위가 아니다)
             대안으로 IPC 로 바이트를 돌려줄 수도 있으나, preview 1600px 은 base64 IPC 로 무겁다 — 택한 쪽과 이유를 보고
캐시 저장    앱 캐시 디렉터리, 총량 상한(예: 200 MB)과 LRU 정리.  연결을 지우면 그 연결 항목을 지운다.
             임시 파일 → 크기 확인 → persist 순서(files_download 와 같은 원자성)
동시성       thumbnail 동시 다운로드·디코딩 수 제한(예: 4).  같은 키의 중복 요청은 하나로 합친다
```

## 결과 모양 (fixture 로 고정)

```txt
files_preview(connectionId, accountId, nodeId, variant) →
  {kind: "ready", ref, width, height, variant}     ref = 웹뷰가 <img src> 에 그대로 쓰는 참조
  {kind: "unsupported", mediaType?}                 형식 밖이거나 디코딩 불가(이유 구분이 쉬우면 reason 추가)
  {kind: "tooLarge", size, limit} | {kind: "tooLarge", pixels, limit}   바이트 상한과 픽셀 상한을 구분
  {kind: "failed", error}                           네트워크·서버·시간 초과
  노드가 없음/폴더/권한 없음은 Err(String) — 다른 files_* 명령과 같게
```

기존 규칙대로 Rust 테스트가 실제 응답을 직렬화해 `gui/src/app/files/files.fixture.json` 에 `preview` 항목을 더하고, GUI
spec 이 같은 파일을 읽는다.

## 있는 것 (재사용)

```txt
files_download       gui/src-tauri/src/files.rs — file_node() 로 노드 재조회, 스트리밍 → 임시 파일 → 정확한 크기 → persist
코어 다운로드        core/src/jmap/blob.rs  Client::download
fileKind             gui/src/app/files/files-model.ts — 기본 아이콘·라벨(표시용, 허용 판정 아님)
상세 패널            gui/src/app/files/files-page.html  #file-details-sheet — 미리보기 자리는 제목 위
```

## 지시

```txt
1  명령과 결과 모양, fixture.  디코딩·재인코딩은 검증된 크레이트(image 등)로, 직접 파서 금지.  의존성을 더하면
   기본 feature 를 줄여 필요한 디코더만(위 4형식)
2  테스트(단위):  지원 형식 4종 ready · 확장자는 .png 인데 바이트가 깨짐 → unsupported · SVG → unsupported ·
   상한 초과 → tooLarge(다운로드 안 함) · blobId 가 바뀌면 캐시 미사용 · 다른 Account 요청에 캐시 미공유 ·
   token 이 등록부 밖이면 scheme 이 404 · 로그아웃한 연결의 token 거절 · 다른 웹뷰의 token 거절 ·
   파일은 작지만 픽셀 수가 상한을 넘음 → tooLarge(디코딩 전) · 재인코딩 결과에 EXIF 없음
3  Stalwart 실측(admin, axismundi-gui-probe 폴더 안의 png/webp 로):  preview 1장 ready, 크기·시간 기록.
   INTEROP-FILENODE.md 에 한 줄 추가
4  보고:  scheme vs IPC 선택과 이유, 상한 값, 캐시 위치, 남은 위험.  GUI 는 손대지 않는다
```
