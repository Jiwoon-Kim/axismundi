// Run: node --test gui/src/app/mail/*.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { type DetailView, addressList, bodyNotice, markReadOnOpen, maySetSeen } from "./mail-detail.ts";

// Written by the Rust test `detail_serializes_to_the_shape_the_gui_reads` from a real mail-read
// response and three edits of it.
const [real, htmlOnly, truncated, unavailable]: DetailView[] =
  JSON.parse(readFileSync(new URL("./mail-detail.fixture.json", import.meta.url), "utf8"));

test("a text message shows its body, sender and recipients", () => {
  assert.equal(real.kind, "message");
  if (real.kind !== "message") return;
  assert.equal(addressList(real.from), "admin@example.org");
  assert.equal(addressList(real.to), "alice@example.org");
  assert.equal(real.body.kind, "text");
  assert.ok(real.body.kind === "text" && real.body.text.startsWith("Probe conversation, message 3 of 4."));
  assert.equal(bodyNotice(real.body), null);
  assert.equal(real.unread, true);
  assert.equal(real.flagged, false);
});

test("an HTML-only message carries no markup and says why nothing is shown", () => {
  assert.ok(htmlOnly.kind === "message");
  if (htmlOnly.kind !== "message") return;
  assert.deepEqual(htmlOnly.body, {kind: "htmlOnly"});
  assert.ok(!JSON.stringify(htmlOnly).includes("<p>"));
  assert.match(bodyNotice(htmlOnly.body) ?? "", /HTML/);
});

test("a truncated body says so, and attachments are listed", () => {
  assert.ok(truncated.kind === "message");
  if (truncated.kind !== "message") return;
  assert.match(bodyNotice(truncated.body) ?? "", /longer/);
  assert.equal(truncated.attachments[0].name, "probe.txt");
});

test("an unavailable message is its own state", () => {
  assert.deepEqual(unavailable, {kind: "unavailable", id: "zzzzzzzz", reason: "notFound"});
});

test("opening marks read only a message known to be unread, and not while a read write is unknown", () => {
  assert.equal(markReadOnOpen(false, false, true), true);
  assert.equal(markReadOnOpen(true, false, true), false, "already read");
  assert.equal(markReadOnOpen(null, false, true), false, "unknown keywords are left alone");
  assert.equal(markReadOnOpen(false, true, true), false, "an unknown earlier write is checked, not overwritten");
  assert.equal(markReadOnOpen(false, false, false), false, "a read-only mailbox is not written to unasked");
});

test("$seen may change only if every mailbox the message is in allows it; unstated is not a no", () => {
  const boxes = [{id: "in", maySetSeen: true}, {id: "shared", maySetSeen: false}, {id: "p", maySetSeen: null}];
  assert.equal(maySetSeen(boxes, ["in", "p"], "in"), true);
  assert.equal(maySetSeen(boxes, ["in", "shared"], "in"), false, "one refusing mailbox is enough");
  assert.equal(maySetSeen(boxes, null, "shared"), false, "unknown memberships: the listed mailbox stands in");
  assert.equal(maySetSeen(boxes, null, "in"), true);
});
