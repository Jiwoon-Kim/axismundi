import assert from "node:assert/strict";
import { test } from "node:test";
import { indicatorDelay, isRefreshShortcut, manualRefreshAction, matchesMailPush, pullProgress, reloadRevision, type MailChanged } from "./mail-refresh.ts";

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

test("pull progress rejects upwards and horizontal swipes and caps at 64px", () => {
  assert.equal(pullProgress(0, -70), 0);
  assert.equal(pullProgress(-50, 32), 0);
  assert.equal(pullProgress(0, 32), 0.5);
  assert.equal(pullProgress(0, 63) < 1, true);
  assert.equal(pullProgress(0, 64), 1);
  assert.equal(pullProgress(0, 100), 1);
});

test("indicators wait 300ms to appear and stay for at least 500ms", () => {
  assert.equal(indicatorDelay(false, 299), 1);
  assert.equal(indicatorDelay(false, 301), 0);
  assert.equal(indicatorDelay(true, 200), 300);
  assert.equal(indicatorDelay(true, 501), 0);
});

test("manual refresh joins an active load and ignores repeated requests", () => {
  assert.equal(manualRefreshAction(false, false), "reload");
  assert.equal(manualRefreshAction(false, true), "join");
  assert.equal(manualRefreshAction(true, true), "skip");
  assert.equal(manualRefreshAction(true, false), "skip");
});

test("refresh shortcuts use the physical R key and leave shifted reloads alone", () => {
  const f5 = {key: "F5", code: "F5", ctrlKey: false, shiftKey: false, altKey: false, metaKey: false};
  const ctrlR = {...f5, key: "Process", code: "KeyR", ctrlKey: true};
  assert.equal(isRefreshShortcut(f5), true);
  assert.equal(isRefreshShortcut(ctrlR), true);
  assert.equal(isRefreshShortcut({...ctrlR, code: "KeyK"}), false);
  assert.equal(isRefreshShortcut({...ctrlR, shiftKey: true}), false);
  assert.equal(isRefreshShortcut({...f5, shiftKey: true}), false);
});
