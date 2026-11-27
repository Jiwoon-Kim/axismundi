import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { type Book, type Card, type ContactRow, type Edit, editsFor, fields, newCard, sortBooks } from "./contact-model.ts";

test("the Rust fixture exposes the fields consumed by Contacts", () => {
  const fixture: {books: Book[]; rows: ContactRow[]; detail: {card: Card}; edit: Edit} =
    JSON.parse(readFileSync(new URL("contacts.fixture.json", import.meta.url), "utf8"));
  assert.equal(fixture.books[0].isDefault, true);
  assert.equal(fixture.books[0].description, "Personal contacts");
  assert.equal(fixture.books[0].sortOrder, 10);
  assert.deepEqual(fixture.rows[0], {
    id: "test-card", kind: "individual", name: "Test Contact", email: "test@example.org",
    avatarUri: "https://example.org/avatar.png", phone: null, organization: null,
  });
  assert.equal(fixture.rows[1].kind, "group");
  const card = {...fixture.detail.card, phones: {p1: {number: "000", label: "Work"}}};
  assert.deepEqual(editsFor(card, fields(card), {'["phones","p1"]': "123"}, () => "new"), [fixture.edit]);
});

test("address book order does not group default or unsubscribed books", () => {
  const book = (id: string, name: string, sortOrder: number | null, isDefault = false, isSubscribed = true): Book =>
    ({id, name, sortOrder, isDefault, isSubscribed, description: null});
  const books = [book("default", "Default", 9, true), book("z", "Zulu", 0), book("a", "Alpha", null, false, false)];
  assert.deepEqual(sortBooks(books, new Intl.Collator("en")).map(item => item.id), ["a", "z", "default"]);
});

test("editing a name component replaces only the array and preserves other components", () => {
  const card = {name: {components: [{kind: "surname", value: "Kim"}, {kind: "given", value: "Old", phonetic: "x"}]}};
  assert.deepEqual(editsFor(card, fields(card), {given: "New"}, () => "new"), [{
    path: ["name", "components"],
    value: [{kind: "surname", value: "Kim"}, {kind: "given", value: "New", phonetic: "x"}],
  }]);
});

test("new contact converts form edits into a card without JMAP patch syntax", () => {
  assert.deepEqual(newCard(editsFor({}, fields({}), {full: "New contact", emails: "new@example.org"}, () => "e1")), {
    name: {full: "New contact"}, emails: {e1: {address: "new@example.org"}},
  });
});
