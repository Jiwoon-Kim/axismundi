import assert from "node:assert/strict";
import { test } from "node:test";
import { acceptsPage, canRequestPage, reachedPageEnd } from "./mail-paging.ts";

const row = {kind: "unavailable", id: "a", reason: "notCached"} as const;

test("only requests pages when idle and not exhausted", () => {
  const listing = {hasMore: true, rows: [row]};
  assert.equal(canRequestPage(listing, false, false), true);
  assert.equal(canRequestPage(listing, true, false), false);
  assert.equal(canRequestPage(listing, false, true), false);
  assert.equal(canRequestPage({...listing, hasMore: false}, false, false), false);
});

test("an unknown total stops on an empty listing or a page without growth", () => {
  assert.equal(canRequestPage({hasMore: null, rows: []}, false, false), false);
  assert.equal(canRequestPage({hasMore: null, rows: [row]}, false, false), true);
  assert.equal(reachedPageEnd(1, {hasMore: null, rows: [row]}), true);
  assert.equal(reachedPageEnd(1, {hasMore: null, rows: [row, {...row, id: "b"}]}), false);
  assert.equal(reachedPageEnd(1, {hasMore: false, rows: [row, {...row, id: "b"}]}), true);
});

test("a late response cannot apply after switching or replacing its request", () => {
  const request = {};
  assert.equal(acceptsPage(request, request, "a", "a"), true);
  assert.equal(acceptsPage(request, request, "b", "a"), false);
  assert.equal(acceptsPage({}, request, "a", "a"), false);
  assert.equal(acceptsPage(null, request, "a", "a"), false);
});
