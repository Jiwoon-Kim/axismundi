// Run: node --test "gui/src/**/*.spec.ts"   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { rowLabel } from "./mail-rows.ts";
import type { ThreadView } from "./mail-thread.ts";

// Written by the Rust test `thread_serializes_to_the_shape_the_gui_reads` from a real mail-thread
// response, the same with one member gone, and a thread that no longer exists.
const [real, withGone, missing]: ThreadView[] =
  JSON.parse(readFileSync(new URL("./mail-thread.fixture.json", import.meta.url), "utf8"));

test("a thread lists its members in thread order with sender and subject", () => {
  assert.ok(real.kind === "thread");
  if (real.kind !== "thread") return;
  const labels = real.messages.map(rowLabel);
  assert.deepEqual(labels.map(label => label.id), ["beaaaaaj", "beaaaaak", "beaaaaal", "beaaaaam"]);
  assert.ok(labels[0].kind === "message" && labels[0].sender === "admin@example.org");
  assert.ok(labels[1].kind === "message" && labels[1].sender === "alice@example.org");
  assert.ok(labels[0].kind === "message" && labels[0].subject === "[Axismundi probe v1] Conversation");
});

test("a member the server no longer has is its own state", () => {
  assert.ok(withGone.kind === "thread");
  if (withGone.kind !== "thread") return;
  assert.deepEqual(rowLabel(withGone.messages[4]), {kind: "unavailable", id: "gone0000", reason: "notFound"});
});

test("a thread that no longer exists says so", () => {
  assert.deepEqual(missing, {kind: "notFound", threadId: "nothread"});
});
