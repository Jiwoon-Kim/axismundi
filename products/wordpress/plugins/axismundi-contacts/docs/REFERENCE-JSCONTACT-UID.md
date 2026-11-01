# REFERENCE — JSContact uid 와 권위

우리가 내보내는 `version` 은 `"2.0"` 이므로 **RFC 9553 에 RFC 9982 의 변경을
적용한 것이 규범이다.** 9982 는 9553 을 대체하지 않고 갱신한다 — 9553 의 나머지는
그대로 유효하다. 9553 만 읽으면 uid 에 대해 틀린 결론에 이른다.

## uid 는 2.0 에서 optional 이다 (실측 2026-11-02)

```txt
RFC 9553 §2.1.9   uid: String (mandatory)
RFC 9982 §4       uid: String (optional)
                  구현은 uid 없는 Card 를 거부해서는 안 된다 —
                  그 Card 가 version "1.0" 이거나 다른 문서가 달리 말하지 않는 한
제약              uid 없는 Card 는 members · relatedTo 에서 참조될 수 없다
IANA              JSContact Version 레지스트리: 1.0 → RFC 9553, 2.0 → RFC 9982
```

## 권위는 두 RFC 모두 다루지 않는다

9553 은 uid 를 "An identifier that associates the object as the same across
different systems, address books, and views" 라고만 정의하고, URN 을 SHOULD,
UUIDv4 를 RECOMMENDED 로 권한다. **유일성을 요구하지 않고, 누가 그 uid 에 대해
권위를 갖는지도 말하지 않는다.** 9982 도 "says nothing new about uid uniqueness
or about which party has authority over a uid" 다.

같은 주체를 가리킨다고 주장하는 Card 가 둘일 때 어느 쪽이 권위인지 정할 방법이
스펙에 없다.

**가설**: 그래서 uid 를 필수에서 선택으로 내렸다는 것은 **우리 추측이다.** 9982 는
변경 이유를 명시하지 않는다. 근거 없이 스펙의 의도로 인용하지 말 것.

### 우리 답은 subject 에 매다는 것이다

```txt
프로필 카드   Actor 의 WebFinger acct 에서 describedby 로 가리켜진다
              uid 를 민팅한다 — 남들이 사본을 들고 있을 값이다
남의 카드     uid 를 주지 않는다

**`describedby` 가 보증하는 범위는 좁다.** 그것은 *이 계정이 게시하는 프로필
문서가 무엇인가* 를 발견하게 해 주는 연결이다. uid 의 전역 유일성도, 같은 uid 를
단 다른 사본에 대한 권위도 보증하지 않는다. 우리가 얻는 것은 "이 주체가 발행한
문서" 라는 출처이지 중재 권한이 아니다.
```

후자는 9982 와 독립적으로 같은 결론에 이른 것이다. 9982 §5 는 UID 없는 vCard 를
2.0 이상으로 변환할 때 **uid 를 지어내면 안 된다**고 규정한다. 우리 코드 주석은
그 이유를 이렇게 적고 있다 — "A uid somebody else can quote should come from the
entity it describes; minting identities on their behalf for every contact in a
private address book would put this site's invented identifiers into other
people's exports."

### 따라오는 규칙

```txt
uid 는 권한이 아니다   어느 Card 인지 말할 뿐 누가 열어도 되는지는 말하지 않는다.
                      uid 를 쥐었다는 사실을 인가로 취급하는 코드가 있어서는 안 된다
불변                  프로필 카드의 uid 는 바뀌지 않는다. 바인딩을 잃은 문서를
                      입양할 때 uid 를 보존하는 이유이고, 새로 민팅하면 유통 중인
                      사본과 한 카드가 둘로 갈라진다
```
