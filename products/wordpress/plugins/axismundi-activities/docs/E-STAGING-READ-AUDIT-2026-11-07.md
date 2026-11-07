# E staging read audit

2026-11-07. 읽기 전용 조사. 배포, 객체 수정, Delete 제출은 하지 않았다.

## 범위

- 로그인된 관리 화면에서 현재 acting actor의 Outbox를 조회했다.
- Actor: `https://designbusan.ai.kr/actors/313024ad-c8c1-45aa-9a44-a491c308b7d4`.
- 관리 화면의 First page / Next page를 따라 끝까지 조회했다.
- 고유 페이지 3개: 50 / 50 / 25건, 고유 Activity id 125개.
- 순회 중 한 페이지를 중복 읽었다. 페이지 id와 Activity id로 중복 제거했다.
- Create/Update가 참조하는 고유 URI 31개. 그중 하나는 액터 자신의 프로필이다.
- 이 뷰에서 Delete는 0건. 다른 액터나 전체 원장에 Delete가 없다는 증명은 아니다.
- Outbound 이력은 원격 전달 성공을 증명하지 않는다. 배송 기록은 조사하지 않았다.

## 공개 HTTP 역참조

쿠키 없는 GET, `Accept: application/activity+json`, 리다이렉트 추적.

| 분류 | 개수 | 관측 |
| --- | ---: | --- |
| WP URI | 4 | 200, application/activity+json |
| 옛 포럼 WP URI | 6 | 404, text/html |
| 옛 ax_note URI | 19 | 200, text/html; AS2 객체 반환 아님 |
| C2S 객체 | 1 | 200, application/activity+json; id 일치, Note |
| 액터 프로필 | 1 | 이번 HTTP 검사 제외; 철회 후보에서 분리 |

200 AS2를 반환한 WP id: 10491, 10477, 8231, 8201.
이 네 응답의 객체 id/type 본문 검사는 수행하지 않았다.

404를 반환한 WP id: 10187, 10152, 10114, 10105, 10093, 10084.
이 6개는 현재 AS2 역참조가 실패하는 후보이며, DB 행 영구삭제 여부는 미확인이다.

옛 Note URI의 UUID:

```txt
6c2d783f-34fe-4607-a1fe-6cdd377d6e58
ecdb32d3-9084-45a9-9d82-799f85b24ebb
375b31e9-05cc-44f0-abb1-e56c54f1166b
c8221d81-d886-4635-ad43-727e104bef38
f7c7b2cf-3c04-4fea-af39-9ce8da3759ee
de9bfa7b-5c82-46d0-a6d5-bd464be9abaf
b8cc4126-a116-407b-833e-431e3ef08ed2
1d080c27-b5b8-4f51-b5ab-66a0c9a0e027
a77a41c8-ebee-40bd-98c0-de45e7ca8cc0
af38d03c-009f-407f-8310-75e3c33d4761
5e7d6a9b-16c2-422f-aa44-924813912750
482064b9-a186-4e2f-a8ee-500fd438c16d
c70f3760-0a63-4a06-bb6c-951cecfbe4b8
9acd6dfa-2a65-4285-b17f-0e2c60aae730
1182ebb4-b42e-4147-8132-85206299e239
004eee64-721d-40a3-ab08-1aab4e39a46e
4beed64e-176b-4bef-9151-e0496d8f6e20
719474bb-f642-42a0-8fe2-6b35b91502ce
fc17449e-16d7-4f7e-bcbb-a8da14a5a0dd
```

URI 형식은 `https://designbusan.ai.kr/?ax_note=<UUID>`.
첫 1건의 HTML 제목도 확인했으며 사이트 홈의
`Axismundi – Material Design 3 social CMS`였다.
나머지 18건의 HTML 본문은 비교하지 않았다. 200만으로 생존을 판정하지 않는다.

## 次の実装への制約

- 候補25 URI = 404の6件 + AS2を返さない旧Note19件。削除対象の確定ではない。
- 認証で読めない対象、未登録CPT、ルート消失、実際の消去を区別する。
- 過去のUpdate(Person)も集合に入るため、自己Actor URIを一般オブジェクトの
  手動Delete経路に流さない。Actorsの削除契約とは分離する。
- 発行権限は元のacting actorと原本のURI一致で検証する。
- AS2取得成功の判定はstatusのみでなく、Content-Type、JSON、対象idを確認する。
- ステイジングのOutboxは旧インライン本文を返していた。
  ローカル4091c82と同じコードが稼働しているとは扱わない。
- 全ローカルActor、配送成功、他ActorによるDelete、復元計画は未調査。
- 発信はオーナー判断。自動一括Deleteは禁止という今回の作業境界を維持する。
