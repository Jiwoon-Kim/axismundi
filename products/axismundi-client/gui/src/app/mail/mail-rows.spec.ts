// Run: node --test gui/src/app/mail/mail-rows.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { type ListView, rowLabel } from "./mail-rows.ts";

// Written by the Rust test `listing_serializes_to_the_shape_the_gui_reads` from a real
// mail-sync response: the JSON the mail_sync command sends over IPC.
const view: ListView = JSON.parse(readFileSync(new URL("./mail-list.fixture.json", import.meta.url), "utf8"));
const labels = view.rows.map(rowLabel);

test("a message shows the sender and subject the command sent", () => {
  assert.deepEqual(labels[0], {
    kind: "message", id: "beaaaaal", sender: "admin@example.org",
    subject: "Re: [Axismundi probe v1] Conversation", unread: true,
    preview: (view.rows[0] as {preview: string}).preview,
    receivedAt: "2026-11-19T10:44:13Z",
    flagged: false,
  });
});

test("a message with $seen is not shown as unread", () => {
  assert.equal(labels[1].kind === "message" && labels[1].unread, false);
});

test("unknown read state is not shown as unread", () => {
  assert.equal(view.rows[2].kind === "message" && view.rows[2].unread, null);
  assert.equal(labels[2].kind === "message" && labels[2].unread, false);
});

test("an unavailable entry is its own state, not a message without a subject", () => {
  assert.deepEqual(labels[3], {kind: "unavailable", id: "zzzzzzzz", reason: "notFound"});
});

test("a cached $seen that is no longer covered is not shown as a read state", () => {
  assert.equal(view.rows[4].kind === "message" && view.rows[4].unread, null);
  assert.equal(labels[4].kind === "message" && labels[4].unread, false);
});

test("flagged is known only when keywords are: never fetched or stale is null, not false", () => {
  assert.deepEqual(view.rows.map(row => row.kind === "message" ? row.flagged : "unavailable"),
    [false, false, null, "unavailable", null]);
});

test("rows keep the server's order", () => {
  assert.deepEqual(view.rows.map(row => row.id), ["beaaaaal", "beaaaaaj", "eaaaaab", "zzzzzzzz", "staleseen"]);
});
