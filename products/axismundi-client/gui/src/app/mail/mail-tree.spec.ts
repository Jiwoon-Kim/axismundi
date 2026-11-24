// Run: node --test gui/src/app/mail/mail-tree.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { type MailboxNode, ancestorsOf, mailboxTree } from "./mail-tree.ts";
import type { ListView, MailboxRow } from "./mail-rows.ts";

// Written by the Rust listing test from a real Stalwart response: six top-level mailboxes.
const view: ListView = JSON.parse(readFileSync(new URL("./mail-list.fixture.json", import.meta.url), "utf8"));
const box = (id: string, parentId: string | null, name = id, role: string | null = null, sortOrder = 0): MailboxRow => ({
  id, name, role, parentId, sortOrder, totalEmails: null, unreadEmails: null,
  mayAddItems: null, mayRemoveItems: null, maySetSeen: null,
  mayCreateChild: null, mayRename: null, mayDelete: null,
});
const names = (nodes: MailboxNode[]): unknown[] =>
  nodes.map(node => node.children.length ? [node.box.name, names(node.children)] : node.box.name);

test("roles first in the documented order, then mailboxes without a role", () => {
  assert.deepEqual(names(mailboxTree(view.mailboxes)),
    ["Inbox", "Drafts", "Sent Items", "Junk Mail", "Deleted Items", "Axismundi Probe"]);
});

test("children nest under their parent and sort among themselves", () => {
  const tree = mailboxTree([box("p", null, "Probe"), box("t", "p", "test"), box("a", "p", "alpha"), box("in", null, "Inbox", "inbox")]);
  assert.deepEqual(names(tree), ["Inbox", ["Probe", ["alpha", "test"]]]);
});

test("sortOrder ranks before name, below the role order", () => {
  const tree = mailboxTree([box("b", null, "b", null, 1), box("a", null, "a", null, 2), box("x", null, "x", "important")]);
  assert.deepEqual(names(tree), ["x", "b", "a"], "an unlisted role comes after the listed ones, before none");
});

test("a child whose parent is not visible is shown at the top, not hidden", () => {
  assert.deepEqual(names(mailboxTree([box("c", "hidden", "shared child")])), ["shared child"]);
});

test("a hidden ancestor lifts only its visible child; the nesting below stays", () => {
  const tree = mailboxTree([box("b", "hidden-a", "B"), box("c", "b", "C"), box("d", "c", "D")]);
  assert.deepEqual(names(tree), [["B", [["C", ["D"]]]]]);
});

test("a parentId cycle neither loops nor drops a mailbox", () => {
  const tree = mailboxTree([box("a", "b"), box("b", "a"), box("c", "a")]);
  const count = (nodes: MailboxNode[]): number => nodes.reduce((n, node) => n + 1 + count(node.children), 0);
  assert.equal(count(tree), 3);
});

test("ancestors of the open mailbox, nearest first, so the tree can show it expanded", () => {
  const boxes = [box("p", null), box("q", "p"), box("r", "q")];
  assert.deepEqual(ancestorsOf(boxes, "r"), ["q", "p"]);
  assert.deepEqual(ancestorsOf(boxes, "p"), []);
  assert.deepEqual(ancestorsOf(boxes, null), []);
});
