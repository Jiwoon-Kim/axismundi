import assert from "node:assert/strict";
import { test } from "node:test";
import { MAILBOX_ROLES, allowsMailboxAction, mailboxNameError, parentAllowed } from "./mailbox-management.ts";

test("only an explicit false blocks a mailbox action", () => {
  assert.equal(allowsMailboxAction(false), false);
  assert.equal(allowsMailboxAction(null), true);
  assert.equal(allowsMailboxAction(true), true);
});

test("mailbox names are limited by UTF-8 bytes and cannot be blank", () => {
  assert.notEqual(mailboxNameError(" ", 12), null);
  assert.equal(mailboxNameError("abc", 3), null);
  assert.notEqual(mailboxNameError("\u00e9\u00e9", 3), null);
  assert.equal(mailboxNameError("\u00e9\u00e9", 4), null);
});

test("parent permissions and account depth are independent of root creation", () => {
  const parent = {id: "p", path: "Parent", depth: 2, allowed: true};
  assert.equal(parentAllowed(undefined, false, 3), false);
  assert.equal(parentAllowed(parent, false, 3), true);
  assert.equal(parentAllowed(parent, true, 2), false);
  assert.equal(parentAllowed({...parent, allowed: false}, true, null), false);
});

test("choices include special-use roles, not shared or IMAP LIST flags", () => {
  assert.deepEqual([...MAILBOX_ROLES].sort(), [
    "all", "archive", "drafts", "flagged", "important", "inbox", "junk", "memos", "scheduled", "sent", "snoozed", "trash",
  ]);
});
