import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { accessLabel, folderSort, formatBytes, quotaLines, setFailure, uploadError, uploadOutcome, type Listing, type Quotas, type UploadResult } from "./files-model.ts";
import { clearFinished, enqueue, settle, summary } from "../shell/upload-queue.ts";

const fixture: {listing: Listing; upload: Record<string, UploadResult>; quota: Record<string, Quotas>} =
  JSON.parse(readFileSync(new URL("files.fixture.json", import.meta.url), "utf8"));

test("the Rust fixture reads as the GUI types it", () => {
  assert.equal(fixture.listing.nodes[0]["@type"], "File");
  assert.equal(fixture.listing.nodes[0].blobId, "stored");
});

test("an upload is done only when the server read it back; a lost answer is not a failure to resend", () => {
  assert.equal(uploadOutcome(fixture.upload.saved).state, "done");
  assert.equal(uploadOutcome(fixture.upload.exists).state, "conflict");
  assert.match(uploadOutcome(fixture.upload.overQuota).message!, /quota/);
  assert.equal(uploadOutcome(fixture.upload.unknown).state, "unknown");
  assert.match(uploadOutcome(fixture.upload.unknown).message!, /check the folder/);
  assert.equal(uploadOutcome(fixture.upload.needsRefresh).state, "unconfirmed");
});

test("the per-upload size limit and a full quota read differently", () => {
  assert.match(uploadError("This file is larger than the server accepts."), /per-file limit, not your storage/);
  assert.doesNotMatch(uploadOutcome(fixture.upload.overQuota).message!, /per-file/);
});

test("storage shows only quotas that count files, says when one is shared, and every state reads", () => {
  assert.deepEqual(quotaLines(fixture.quota.available).map(line => line.text), ["8 B of 4.1 KB used — shared with Email"]);
  assert.match(quotaLines(fixture.quota.notApplicable)[0].text, /no storage quota/);
  assert.match(quotaLines(fixture.quota.failed)[0].text, /could not be read/);
});

test("folders sort first when the server can, and nothing is invented when it cannot", () => {
  assert.deepEqual(folderSort(["name", "nodeType", "tree"]).map(s => s.property), ["nodeType", "name"]);
  assert.deepEqual(folderSort(["name"]).map(s => s.property), ["name"]);
  assert.deepEqual(folderSort(["size"]), []);
});

test("set failures read as sentences", () => {
  assert.equal(setFailure({notDestroyed: {d: {type: "nodeHasChildren"}}}, "notDestroyed", "d"), "The folder is not empty.");
  assert.equal(setFailure({updated: {d: null}}, "notUpdated", "d"), null);
  assert.equal(formatBytes(50000000), "50.0 MB");
});

test("the upload queue keeps states apart and clears only what is finished", () => {
  let n = 0;
  let items = enqueue([], [{handle: "h1", name: "a", size: 1, type: "t"}, {handle: "h2", name: "b", size: 1, type: "t"}], () => `k${++n}`);
  items = settle(items, "k1", "done");
  items = settle(items, "k2", "unknown", "check");
  assert.deepEqual(summary(items), {active: 0, attention: 1, done: 1});
  assert.deepEqual(clearFinished(items).map(item => item.key), ["k2"]);
});

test("folder access reads as words, and a missing myRights says nothing", () => {
  const all = {mayRead: true, mayAddChildren: true, mayModifyContent: true, mayRename: true, mayDelete: true, maySetMetadata: true, mayShare: true};
  assert.equal(accessLabel(all), "Full access");
  assert.equal(accessLabel({mayRead: true}), "View only");
  assert.equal(accessLabel({mayRead: true, mayAddChildren: true}), "View, Add items");
  assert.equal(accessLabel({}), "No access");
  assert.equal(accessLabel(undefined), null);
});
