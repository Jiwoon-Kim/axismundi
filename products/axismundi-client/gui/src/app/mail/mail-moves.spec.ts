// Run: node --test gui/src/app/mail/mail-moves.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { exclusiveMove, moveOptions } from "./mail-moves.ts";
import type { ListView, MailboxRow } from "./mail-rows.ts";

// Written by the Rust listing test from a real Stalwart response: Inbox a, Trash b ("Deleted
// Items"), Junk c, Drafts d, Sent e, and a role-less "Axismundi Probe" h, all with full rights.
const view: ListView = JSON.parse(readFileSync(new URL("./mail-list.fixture.json", import.meta.url), "utf8"));
const boxes = view.mailboxes;
const ids = (list: readonly MailboxRow[]) => list.map(box => box.id);

test("from the Inbox: Trash by role, and every other mailbox as a destination, Sent and Drafts included", () => {
  const options = moveOptions(boxes, "a");
  assert.equal(options.showTrash, true);
  assert.equal(options.trash?.name, "Deleted Items", "found by role, not by name");
  assert.equal(options.trashBlocked, null);
  assert.deepEqual(ids(options.targets).sort(), ["b", "c", "d", "e", "h"]);
  assert.equal(options.moveBlocked, null);
});

test("inside Trash there is no Move to Trash, and moving out goes anywhere else", () => {
  const options = moveOptions(boxes, "b");
  assert.equal(options.showTrash, false);
  assert.ok(ids(options.targets).includes("e"), "Trash → Sent is offered");
  assert.ok(!ids(options.targets).includes("b"));
});

test("a shared Account that shares only its Inbox has no Trash and nowhere to move to", () => {
  const sharedInbox: MailboxRow = {...boxes[0], mayAddItems: true, mayRemoveItems: true};
  const options = moveOptions([sharedInbox], sharedInbox.id);
  assert.equal(options.trash, null);
  assert.match(options.trashBlocked ?? "", /no Trash/);
  assert.match(options.moveBlocked ?? "", /no other mailbox/);
});

test("rights: no removing from the source blocks both; no adding to a box drops it from the targets", () => {
  const locked = boxes.map(box => box.id === "a" ? {...box, mayRemoveItems: false} : box);
  const fromLocked = moveOptions(locked, "a");
  assert.match(fromLocked.trashBlocked ?? "", /cannot remove/);
  assert.match(fromLocked.moveBlocked ?? "", /cannot remove/);
  const closed = boxes.map(box => box.id === "e" ? {...box, mayAddItems: false} : box);
  assert.ok(!ids(moveOptions(closed, "a").targets).includes("e"));
  const closedTrash = boxes.map(box => box.role === "trash" ? {...box, mayAddItems: false} : box);
  assert.match(moveOptions(closedTrash, "a").trashBlocked ?? "", /cannot add messages to Trash/);
});

test("a right the server did not state is not taken as missing", () => {
  const unstated = boxes.map(box => ({...box, mayAddItems: null, mayRemoveItems: null}));
  const options = moveOptions(unstated, "a");
  assert.equal(options.trashBlocked, null);
  assert.equal(options.targets.length, 5);
});

test("Trash and Junk are exclusive moves; Sent, Drafts, Inbox and role-less mailboxes are plain", () => {
  const byRole = (role: string | null) => boxes.find(box => box.role === role)!;
  assert.equal(exclusiveMove(byRole("trash")), true);
  assert.equal(exclusiveMove(byRole("junk")), true);
  for (const role of ["inbox", "sent", "drafts", null]) assert.equal(exclusiveMove(byRole(role)), false, String(role));
});
