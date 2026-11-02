# Announced Collection Controls

현재 범위는 검증된 원격 Group의 `Announce(Add/Remove)` 전달과,
같은 원격 Group을 audience로 지정한 Person의 직접 Add/Remove 수신이다.
moderators 멤버십 적용, principal 부여, Create/Page/Note 해석은 하지 않는다.
Move는 미지원 오류이며, 지원되는 활동의 소유자 처리기 미등록(unclaimed)과 다르다.
기존 Create 등을 담은 Announce 경로는 가로채지 않는다.

## Evidence Boundary

- Group 서명은 전달 사실을 증명한다. 내부 Person의 작성 사실을 자동으로 증명하지 않는다.
- 내부 Activity URI 조회는 입장 조건이 아니다. 접근 권한이나 조회 endpoint가 없을 수 있다.
- Announce만 받으면 Group의 Announce를 원장에 기록하고 내부 문서는 전달 증거로 보관한다.
  별도의 Person Activity나 Person 직접 수신 사실을 만들지 않는다.
- Person에게 직접 받은 Activity는 기존 inbound 원장에 기록한다.
- 이미 있는 로컬 outbound 또는 직접 수신 Activity와 내부 문서가 일치하면 그 증거를
  재사용한다. outbound 행을 inbound 행으로 바꾸거나 복제하지 않는다.
- 내부 id가 없으면 이를 생성하지 않는다. 현재 원장은 object URI를 요구하므로
  이 경우 전체 Announce도 `envelope_json` 증거로만 보관하며 원장용 내부 id를 만들지 않는다.

②는 콘텐츠 객체 캐시를 추가하지 않는다. 내부 Add/Remove의 object에 대한 역할 해석은
③의 책임이다. 향후 Create/Update/Delete 수신과 객체 캐시 변경 권한은 별도 슬라이스다.
특히 Group이 알린 Delete만으로 타인의 원본 객체 삭제 권한이 생기지 않는다.

## Ownership And Dispatch

발신 Group의 캐시된 문서가 target을 `attributedTo` 또는 `featured`로 정확히 참조하고,
target과 Group의 origin이 같은지를 확인한다. 경로 접미사나 같은 도메인만으로 판단하지 않는다.
이것은 이번 어댑터가 수용하는 광고된 target의 범위다. 상대 플랫폼이나 moderators 역할,
Person의 임명 권한을 확정하는 검사가 아니다. 일반 공유 컬렉션의 소유권 규칙도 아니다.

`axismundi_activitypub_bridge_collection_control_handler` 필터는 기본 null이며,
trusted local 모듈이 특정 target에 대한 callback을 등록한다. context에는 `group`, `person`,
`announced`, `author_verified`, `operation_key`가 전달된다. Group 전달 증거와 Person 작성 증거를
구분하고, callback이 역할과 작업 권한을 별도로 검증해야 한다. ② 자체는 handler를 등록하지 않는다.

callback의 true는 처리 완료, false는 unclaimed 유지, WP_Error는 재시도 가능한 실패다.
callback은 operation_key로 자체 SQL 효과를 멱등 처리해야 한다. 외부 부작용의 exactly-once를
보장하지 않는다. 같은 operation의 동시 실행은 DB advisory lock으로 직렬화한다.

## Receipt Identity

`ax_collection_controls`는 Group·내부 id·정확한 intent에 한정된 관측 기록이다.
내부 id만으로 전역 저자 정체성을 선점하지 않는다. 서로 다른 Group 주장 또는 상충하는
미검증 내용은 독립 증거이며, Person 원장의 검증된 내용을 덮어쓰지 못한다.
직접 수신과 Announce가 같은 intent면 한 operation으로 합쳐진다.
id가 없는 Announce는 외부 envelope id까지 키에 포함해 서로 다른 발표를 동일시하지 않는다.
이는 URI가 없는 원격 활동의 전역 정체성 또는 순서를 복원한다는 뜻이 아니다.

원문 증거는 개인정보를 포함할 수 있는 내부 저장소이며 공개 REST 조회를 제공하지 않는다.
별도 보존 기간 정책과 운영 UI는 후속 작업이다.

## Verification

```sh
python tools/wp-env/run_audits.py run --audit audit-collection-controls.php --audit audit-collection-controls-mutations.php --audit audit-actor-inbox.php --audit audit-person-featured.php
```

Bridge tests/가 Forum 밖 중첩 수신·서명 감사의 자리다. 원격 HTTP는 fixture로 제어하지만
RSA 서명은 실제 upstream verifier로 검사한다. 실제 Lemmy 서버와의 왕복 검증은 아직 아니다.
