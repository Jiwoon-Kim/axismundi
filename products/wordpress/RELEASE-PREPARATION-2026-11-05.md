# Release Preparation

2026-11-05 기준 SVN 배포 준비 대상이다. 이 기록은 SVN 업로드나 공개 릴리즈 완료를 뜻하지 않는다.

| Plugin | Version |
|---|---|
| axismundi-actors | 0.1.8 |
| axismundi-object-projections | 0.1.7 |
| axismundi-emoji | 0.3.2 |
| axismundi-media-library | 0.0.44 |

이번 릴리즈는 오너 판단에 따라 staging 동작 확인을 기준으로 진행한다.
공개 구버전 전체 설치본에서 새 ZIP으로 올리는 별도 업그레이드 검증 인스턴스는 만들지 않는다.
이 경로를 검증 완료로 표시하지 않으며, staging과 릴리즈 후보 소스가 동일하다는 뜻도 아니다.
기존 마이그레이션과 실패 시 중단하는 검증 코드는 제거하지 않는다.

Activities는 별도 심사 경로이며 이 SVN 대상에 포함하지 않는다. Activities의 깨끗한 설치와
WP_DEBUG 검증 역시 완료로 기록하지 않는다. navigation-icons의 버전 상수 정리는 별도 범위다.

각 build-zip.ps1로 ZIP을 재생성하고, 버전/Stable tag/상수, 슬러그 루트, 개발 파일 제외 및
ZIP과 소스 파일 해시 일치를 확인한다. 이 패키징 검사는 전체 업그레이드나 실제 연합 왕복을
검증하는 검사가 아니다. SVN 커밋과 공개 태그 생성은 별도 작업이다.
