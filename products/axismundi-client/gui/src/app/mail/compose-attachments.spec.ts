import assert from "node:assert/strict";
import test from "node:test";
import { draftAttachments, fitTotal, markMissing, picked, restored, sendBlocker, unsavedAttachments } from "./compose-attachments.ts";

const file = {handle: "h1", name: "a.pdf", size: 10, type: "application/pdf"};

test("a save references only finished uploads", () => {
  const uploading = picked(file, "k1");
  const done = {...picked({...file, handle: "h2", name: "b.pdf"}, "k2"), state: "uploaded" as const, blobId: "B2"};
  assert.deepEqual(draftAttachments([uploading, done]), [{blobId: "B2", name: "b.pdf", type: "application/pdf", size: 10}]);
});

test("sending waits for uploads and refuses failed attachments", () => {
  assert.equal(sendBlocker([]), null);
  assert.equal(sendBlocker([picked(file, "k1")]), "1 attachment is still uploading.");
  assert.match(sendBlocker([{...picked(file, "k1"), state: "failed"}])!, /failed/);
  assert.equal(sendBlocker([restored({blobId: "B", name: "x", type: "t", size: 1}, "k")]), null);
});

test("a blob the server lost becomes a failed attachment, retryable only with its local file", () => {
  const local = {...picked(file, "k1"), state: "uploaded" as const, blobId: "B1"};
  const saved = restored({blobId: "B2", name: "y", type: "t", size: 1}, "k2");
  const [a, b] = markMissing([local, saved], ["B1", "B2"]);
  assert.equal(a.state, "failed");
  assert.equal(a.blobId, null);
  assert.match(a.error!, /Upload it again/);
  assert.match(b.error!, /Remove it/);
  assert.equal(markMissing([local], ["other"])[0].state, "uploaded");
});

test("closing sees attachments a save snapshot cannot: uploading, failed, or uploaded since the last save", () => {
  const uploaded = {...picked(file, "k1"), state: "uploaded" as const, blobId: "B1"};
  assert.equal(unsavedAttachments([], new Set()), false);
  assert.equal(unsavedAttachments([picked(file, "k0")], new Set()), true);
  assert.equal(unsavedAttachments([{...picked(file, "k0"), state: "failed"}], new Set()), true);
  assert.equal(unsavedAttachments([uploaded], new Set()), true);
  assert.equal(unsavedAttachments([uploaded], new Set(["B1"])), false);
});

test("the per-message total counts what is already attached and refuses before upload", () => {
  const thirty = {handle: "h", name: "a", size: 30, type: "t"};
  const attached = {...picked(thirty, "k1"), state: "uploaded" as const, blobId: "B1"};
  const {accepted, refused} = fitTotal([attached], [thirty, {...thirty, handle: "h2", size: 10}], 50);
  assert.deepEqual(refused.map(f => f.handle), ["h"]);
  assert.deepEqual(accepted.map(f => f.handle), ["h2"]);
  assert.equal(fitTotal([], [thirty, thirty], null).accepted.length, 2);
  assert.equal(fitTotal([{...attached, state: "failed"}], [thirty], 50).accepted.length, 1);
});

test("a retry is held to the same total: a failed file retried after another filled the room is refused", () => {
  const thirty = {handle: "h1", name: "first", size: 30, type: "t"};
  const failed = {...picked(thirty, "k1"), state: "failed" as const};
  const second = {...picked({...thirty, handle: "h2", name: "second"}, "k2"), state: "uploaded" as const, blobId: "B2"};
  // Adding the second file fit, since a failed attachment does not count...
  assert.equal(fitTotal([failed], [{...thirty, handle: "h2"}], 50).accepted.length, 1);
  // ...but retrying the first, counted against everything else, would make 60 of 50.
  const others = [failed, second].filter(item => item.key !== "k1");
  assert.equal(fitTotal(others, [thirty], 50).refused.length, 1);
});
