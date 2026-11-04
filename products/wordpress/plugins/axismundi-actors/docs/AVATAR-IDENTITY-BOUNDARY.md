# Avatar Identity Boundary

WP User 아바타와 Actor 프로필 이미지는 양방향으로 독립이다.
User가 Person Account를 여러 개 운영해도 대표 Person을 추론하지 않는다.

- Actors는 `get_avatar_data`에 대체 훅을 등록하지 않는다. 기존 callable은 no-op으로 유지한다.
- 로컬 Actor의 이미지가 없으면 User/Gravatar로 폴백하지 않는다. URL과 HTML은 빈 값을 반환한다.
  mention 목록과 OP Actor icon도 같은 경계를 따른다.
- 시스템 Application의 사이트 아이콘과 원격 Actor의 자산 캐시는 이 변경과 별개로 유지한다.
- 선택기는 명시한 Actor 이미지를 표시할 수 있지만 WP User 아바타를 바꾸지 않는다.
- 기존 이미지 파일이나 저장된 profile media를 자동 삭제하거나 재배정하지 않는다.

이전 훅은 acting Actor가 아닌 레거시 User transport Actor를 조회했다. 따라서 그 훅 자체가
선택한 세션을 외부에 노출했다고 단정하지 않는다. 고정 Person과 User 이미지의 결합도 제거한다.
이 정책은 사이트의 다른 플러그인이 User 아바타를 대체하는 것까지 통제한다는 뜻은 아니다.

검증: `python tools/wp-env/run_audits.py run --audit audit-avatar.php --audit audit-actor-projection.php --audit audit-actor-axis-callers.php`.
임시 User가 서로 다른 Person 둘을 선택하고, 이미지 유무 및 익명 조회에서 User 아바타 불변과
Actor 이미지 독립성을 검사한다. 운영 User의 선택이나 로그인 세션은 변경하지 않는다.
