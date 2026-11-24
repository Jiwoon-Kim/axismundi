import assert from "node:assert/strict";
import { test } from "node:test";
import { matchesMailPush, reloadRevision, type MailChanged } from "./mail-refresh.ts";

test("push wakes only the connection and account it names", () => {
  const wake: MailChanged = {connectionId: 7, accountIds: ["a", "b"], reason: "push"};
  assert.equal(matchesMailPush(wake, 7, "b"), true);
  assert.equal(matchesMailPush(wake, 8, "b"), false);
  assert.equal(matchesMailPush(wake, 7, "c"), false);
  assert.equal(matchesMailPush(wake, 7, null), false);
});

test("wakes during a load are carried forward as one reload", () => {
  const applied = reloadRevision(0, 1, false);
  assert.equal(applied, 1);
  assert.equal(reloadRevision(applied, 2, true), 1);
  assert.equal(reloadRevision(applied, 3, true), 1);
  assert.equal(reloadRevision(applied, 3, false), 3);
});
