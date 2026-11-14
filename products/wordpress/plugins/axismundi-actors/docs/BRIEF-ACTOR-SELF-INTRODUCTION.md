# BRIEF — 자기참조 Create 로 모르는 원격 Actor 발견

근거는 `axismundi-activities/docs/DESIGN-ADDRESSING.md` §6 의 "비공개 액터, 그리고 인라인은
권위가 아니다" 와 "자기참조 Create 와 자기소개" 에 있다. 이 문서는 **무엇을 바꾸고 무엇을
바꾸지 않는지**만 적는다.

## 범위

```txt
안에 있다   검증된 인바운드 Create 에서 actor URI === object URI 이면 자기소개 후보로 다룬다
            원격 Actor 문서는 **기존 발견·검증 경로를 거쳐** Actors 에 등록한다
            받은 Create 는 Activities 원장에 남긴다
밖에 있다   인라인 Update 경로 교체 — 이미 동작한다. 아래 "현재" 를 보라
            Follow 관계 — 소개는 관계가 아니다. 어떤 관계도 만들지 않는다
            OP 원격 콘텐츠 캐시 — 액터는 거기 들어가지 않는다 (이미 두 겹으로 막혀 있다)
            자기소개를 **보내는** 쪽 — 참조형 Create 발행 슬라이스가 선행이다
            비공개 액터의 서명 fetch 가 실제로 통과하는지 — 별도 검증 항목
```

## 현재 (실측 2026-11-14)

```txt
bridge.php:305            axismundi_actors_apply_remote_actor_update() 를 호출한다
remote-discovery.php:222  actor === object.id 를 요구하고, 부분 문서를 거부하고,
                          **기존 원격 Actor 만 갱신한다**.  새 정체성은 만들지 않는다
                          (ax_actors_update_not_cached)
inbox-observations.php:25 OP 가 인라인 액터를 객체 캐시에서 제외한다
remote-objects.php:611    원격 객체 타입 allowlist 에 액터 타입이 없다.
                          docblock: "Do not add Actor, Activity, or Collection types"
```

즉 **갱신은 구현돼 있고 등록이 비어 있다.** 2026-11-14 에 "검증된 Update 경로가 없다" 고
적었던 것은 검색어가 빗나간 오진이며, 정정은 DESIGN-ADDRESSING §6 에 기록했다.

## 바꿀 것

### ① 자기소개 후보를 식별한다

검증된 인바운드 `Create` 에서 **`actor` URI 와 `object` URI 가 같을 때만**이다. 다르면
누구나 남의 액터 프로필을 밀어 넣을 수 있다. 같은 규칙을 Actors 가 이미 갱신 쪽에서 쓴다
(`hash_equals( $actor_uri, $object_uri )`).

```txt
참조형   object 가 문자열 URI 다.  이 브리프의 대상
인라인   object 가 완전한 액터 문서다.  갱신 경로가 이미 처리한다 —
         모르는 액터면 ax_actors_update_not_cached 로 거부된다.  그 거부를
         **등록으로 바꾸지 말 것**: 아래 ②가 이유다
```

### ② 레코드는 fetch 에서 나온다, 활동 본문에서 나오지 않는다

모르는 액터에는 **아직 키가 없다.** 그러므로 수신자는 검증을 위해 그 URI 를 fetch 해야 하고,
레코드는 그 fetch 결과로 만든다. 기존 발견·검증 경로를 쓰고 새 경로를 만들지 않는다.

```txt
근거   FEP-fe34 69행  fetch 가 1차 인증 수단이고 다른 수단이 그것에 의존한다
       FEP-fe34 98행  키 소유를 reciprocal claim 으로 확인해야 한다 (MUST)
```

갱신 경로가 인라인 본문을 받아도 되는 이유는 이와 모순되지 않는다 — 그쪽은 서명이 **이미
fetch 해 둔 키**에 본문을 묶는다. 갈리는 축은 활동의 모양이 아니라 **키를 이미 가졌는지**다.

### ③ 원장과 등록을 분리한다

```txt
Activities   받은 Create 를 기록한다.  이것이 "소개를 받았다" 는 사실이다
Actors       fetch 로 확인된 원격 Actor 레코드.  이것이 "그 정체성을 안다" 는 사실이다
```

둘은 다른 사실이고 한쪽이 다른 쪽을 함의하지 않는다. fetch 가 실패하면 **Create 는 남고
등록은 없다** — 정상 상태이며 오류로 다루지 않는다.

## 바꾸지 말 것

```txt
apply_remote_actor_update   actor === object.id · 부분 문서 거부 · 새 정체성 생성 금지.
                            그대로 둔다
OP 가드 둘                   인라인 조기 반환과 타입 allowlist.  둘 다 유지
Follow 관계                  소개가 관계를 만들지 않는다
acct 주소                    등록이 verified acct 를 기록하지 않는다 (갱신 쪽 주석과 동일)
로컬 액터                    로컬 URI 를 가진 object 는 이 경로가 아니다
```

## 수용 기준

```txt
actor === object, 미등록      fetch 를 거쳐 Actors 에 원격 Actor 가 생긴다
actor ≠ object               등록하지 않는다.  원장에만 남는다
fetch 실패                    Create 는 남고 등록은 없다.  오류로 처리하지 않는다
인라인 Create(모르는 액터)     본문으로 등록하지 않는다 — fetch 를 거친다
인라인 Update(아는 액터)       기존 경로 그대로 동작한다 (회귀)
OP 캐시                       어떤 경우에도 액터가 원격 객체 저장소에 들어가지 않는다
Follow                       관계가 생기지 않는다
```

**변이로 깨 볼 것.** 최소 둘 — `actor === object` 비교 제거(남의 프로필 주입이 통과하는지),
fetch 대신 활동 본문으로 레코드 구성(감사가 실제로 깨지는지).

## 검수할 때 볼 것

- 등록이 fetch 결과에서만 나오는가. 활동 본문이 레코드에 새어들지 않았는가
- `actor === object` 가 유일한 자격 조건인가
- 갱신 경로가 그대로인가. `ax_actors_update_not_cached` 가 등록으로 바뀌지 않았는가
- 액터가 OP 원격 객체 저장소에 들어가지 않는가
- 감사가 known-positive 로 실제로 깨지는가
