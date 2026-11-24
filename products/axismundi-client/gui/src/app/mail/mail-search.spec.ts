import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { appendSearchPage, searchLabels, snippetParts } from "./mail-search.ts";
import type { ListView } from "./mail-rows.ts";

const mailbox: ListView = JSON.parse(readFileSync(new URL("./mail-list.fixture.json", import.meta.url), "utf8"));

test("highlighting renders escaped markup only as text and decodes entities once", () => {
  assert.deepEqual(snippetParts("&lt;b&gt;<mark>cats &amp; dogs</mark> &#39; &amp;lt;", ""), [
    {text: "<b>", marked: false}, {text: "cats & dogs", marked: true}, {text: " ' &lt;", marked: false},
  ]);
  for (const broken of ["", "<mark>x", "x</mark>", "<mark><mark>x</mark></mark>", "<img src=x>", "<mark class=s>x</mark>"]) {
    assert.deepEqual(snippetParts(broken, "plain"), [{text: "plain", marked: false}]);
  }
});

test("labels only show known memberships, in the caller's mailbox order", () => {
  const [first, second] = mailbox.mailboxes;
  assert.deepEqual(searchLabels([second.id, "hidden", first.id], [first, second]),
    {display: `${first.name} +1`, full: `${first.name} · ${second.name}`});
  assert.deepEqual(searchLabels([second.id], [first, second]), {display: second.name, full: second.name});
  assert.deepEqual(searchLabels(null, [first, second]), {display: "", full: ""});
});

test("search pages append only in the same state and position; position zero replaces", () => {
  const build = (position: number, state: string, rows: ListView["rows"]): ListView =>
    ({...mailbox, rows, search: {position, state, snippets: {}}});
  const current = build(0, "one", mailbox.rows.slice(0, 1));
  const next = build(1, "one", mailbox.rows.slice(1));
  assert.deepEqual(appendSearchPage(current, next).rows, mailbox.rows);
  assert.throws(() => appendSearchPage(current, build(1, "two", next.rows)));
  assert.throws(() => appendSearchPage(current, build(2, "one", next.rows)));
  assert.throws(() => appendSearchPage(current, build(1, "one", current.rows)));
  const replacement = build(0, "two", next.rows);
  assert.equal(appendSearchPage(current, replacement), replacement);
});
