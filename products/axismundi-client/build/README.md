# Platform Builds

빌드 스크립트·플랫폼 설정은 클라이언트와 함께 버전 관리한다. Rust·Node·Windows SDK·
NSIS·Xcode·Android SDK 같은 툴체인 바이너리는 저장소에 복사하지 않는다.
Windows 번들러 보조 도구는 Tauri의 useLocalToolsDir로 Cargo target/.tauri 캐시에 둔다.
이 캐시도 빌드 출력이며 버전 관리하지 않는다.

| 경로 | 역할 | 현재 상태 |
|---|---|---|
| windows/ | Windows 설치본·설정·검증 | Development NSIS 실험 |
| linux/ | Linux 패키징·시스템 의존성 | 예정, 스크립트 없음 |
| ios/ | iOS 서명·프로비저닝·패키징 | 예정, macOS/Xcode 필요 |
| android/ | Android SDK·서명·패키징 | 예정, 스크립트 없음 |

예정 플랫폼에 동작하지 않는 빈 스크립트를 만들지는 않는다. 공통 Rust/Angular 코드는
공유하고 플랫폼별 패키징·OS 권한 선언·검증을 이 경로에 둔다.

## Windows 0.1.0 Development

클라이언트 루트에서 실행한다. 먼저 gui 의존성 설치와 Windows Rust/Tauri 빌드 환경이
준비돼 있어야 한다. 스크립트가 시스템 툴체인을 새로 설치하거나 보안 설정을 바꾸지는 않는다.
Tauri가 사용하는 NSIS 등 번들러 보조 도구는 빌드 과정에서 캐시에 내려받을 수 있다.

```powershell
./build/windows/build-development.ps1
```

스크립트는 생산용 Angular 자산을 빌드한 뒤 debug Rust 실행 파일을 NSIS EXE로 묶는다.
개발 서버 localhost:1420에 의존하지 않는다. Cargo 출력 경로는 metadata로 읽고
CARGO_TARGET_DIR을 존중한다. 기본 결과는 `artifacts/windows/development/`이며 Git에서 제외한다.
installer와 SHA-256/source commit/dirty 여부를 담은 build-info.json을 함께 남긴다.
`-OutputDirectory`로 결과 위치, `-Target`으로 설치된 Rust target을 지정할 수 있다.

| 항목 | 개발 빌드 |
|---|---|
| 표시 이름 | Axismundi Client Development |
| 앱 식별자 | org.axismundi.client.development |
| 실행 파일 | axismundi-client-development.exe |
| 버전 | 0.1.0, 창 제목에 Development 표시 |
| 설치 범위 | 현재 사용자, 관리자 권한 요구하지 않음 |
| 서명/자동 업데이트 | 없음 |
| Store/MSIX | 이번 범위 아님 |

정식 앱의 기본 설정을 바꾸거나 같은 앱 식별자로 시험 설치하지 않는다.
설치·실행은 별도 명시적 작업이며 이 스크립트가 자동 설치하지 않는다.
현재 GUI는 연결·AP 문서 읽기용이고 Mail 화면은 아직 없다. CLI 바이너리를 이 GUI 설치본에
포함한 것으로 간주하지 않는다. 카메라·마이크·위치·백그라운드 작업도 구현하지 않았다.
Tauri IPC capability와 OS 권한은 별개이며, 이번 빌드가 Outlook과 같은 권한 관리 통합이나
출하용 CSP·서명·업데이트 정책을 검증했다는 뜻은 아니다.

확인 항목은 설치/시작 메뉴/제거 항목 등록, 설치 위치에서의 실행, 번들된 폰트·자산,
개발 서버 없이 화면 표시, 로컬 HTTPS JMAP 발견이다. 인증 입력이나 Windows 보안·권한
대화상자에서 자동으로 허용/우회하지 않는다. 실제 검증 결과는 빌드 후 이 문서에 기록한다.

## 2026-11-19 Windows 실험 결과

- NSIS x64 installer 생성 성공: 약 8.83 MiB, 버전 0.1.0, 서명 없음.
- 처음 사용자 전역 NSIS 캐시 압축 해제는 access denied로 실패했다. useLocalToolsDir=true의
  Cargo 출력 캐시에서는 내려받기 hash 검사·압축 해제·번들링이 성공했다. 보안 설정은 바꾸지 않았다.
- 현재 사용자 silent 설치 exit 0. 시작 메뉴 바로가기와 HKCU 앱 제거 항목 등록 확인.
- 설치 위치는 현재 PC의 `%LOCALAPPDATA%/Axismundi Client Development`.
- 설치된 실행 파일에서 ProductName/버전과 `Axismundi Client 0.1.0 - Development` 창 생성,
  WebView2 자식 프로세스를 확인했다. 이때 1420 개발 서버 listener는 없었다. 앱은 실행 상태로 유지.
- debug 빌드이므로 콘솔 프로세스가 함께 생길 수 있다. 정식 배포의 UX를 뜻하지 않는다.
- 설치 exe와 Cargo 원본 exe의 차이는 Tauri bundle-type 표식 세 바이트(UNK→NSS)였다.
  로컬 tauri-utils 소스에서 NSS가 NSIS를 뜻함을 확인했다. 설치 파일 자체의 SHA-256은
  artifacts의 build-info.json에 보존했다.
- 화면 자동화 도구가 kernel assets 기록 오류로 초기화·재시도 모두 실패했다.
  따라서 실제 화면 렌더링·폰트 표시·GUI 서버 발견/로그인은 아직 검증하지 못했다.
  프로세스/창 제목 확인을 GUI E2E 통과로 표현하지 않는다.
- Tauri lib 회귀 테스트 5개 통과, 실제 Axismundi 인증 테스트 1개는 ignored로 남겼다.
  실제 앱의 GUI 로그인 통과로 세지 않는다. 빌드 스크립트 구문·문서 링크·diff 검사도 통과했다.

이 설치는 개발용 앱 하나를 추가한 것이며 기존 정식 식별자 앱·서버 컨테이너·자격증명
설정을 바꾸지 않았다. Store/MSIX·코드 서명·자동 업데이트·카메라/위치 권한은 여전히 후속이다.
