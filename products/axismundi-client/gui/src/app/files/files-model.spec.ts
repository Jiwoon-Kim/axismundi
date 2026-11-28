import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { ROOT, accessLabel, fileKind, usageError, usageSummary, type Usage, endsFolder, folderSort, folderTree, holdsAllFolders, formatBytes, quotaLines, setFailure, uploadError, uploadOutcome, type Listing, type Quotas, type UploadResult } from "./files-model.ts";
import { clearFinished, enqueue, settle, summary } from "../shell/upload-queue.ts";

const fixture: {listing: Listing; upload: Record<string, UploadResult>; quota: Record<string, Quotas>; usage: Usage} =
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

test("the folder tree shows only folders, unlisted ones as unknown, and stops at a cycle", () => {
  const dir = (id: string, parentId: string | null) => ({"@type": "Directory", id, parentId, name: id});
  const file = {"@type": "File", id: "f", parentId: null, name: "f"};
  const tree = folderTree(new Map([[ROOT, [dir("a", null), file, dir("b", null)]], ["a", [dir("c", "a")]], ["c", [dir("a", "c")]]]));
  assert.deepEqual(tree.children?.map(node => node.id), ["a", "b"]);
  assert.equal(tree.children?.[1].children, null);
  assert.deepEqual(tree.children?.[0].children?.[0].children, []);
  assert.equal(folderTree(new Map()).children, null);
  const cut = folderTree(new Map([[ROOT, [dir("a", null)]]]), new Set([ROOT]));
  assert.deepEqual(cut.children?.map(node => node.more ?? node.id), ["a", ROOT], "a tree read only in part says so");
});

test("a folder's subfolders are known once a file follows them in a folders-first sort", () => {
  const dir = {"@type": "Directory", id: "d", parentId: null, name: "d"};
  const file = {"@type": "File", id: "f", parentId: null, name: "f"};
  const byKind = [{property: "nodeType"}, {property: "name"}];
  assert.equal(holdsAllFolders([dir], true, []), true);
  assert.equal(holdsAllFolders([dir, file], false, byKind), true);
  assert.equal(holdsAllFolders([dir], false, byKind), false);
  assert.equal(holdsAllFolders([dir, file], false, [{property: "name"}]), false);
});

test("a short page ends the folder even when the server does not count", () => {
  assert.equal(endsFolder({nodes: [], position: 0, total: null, limit: 200}), true);
  assert.equal(endsFolder({nodes: Array(200), position: 0, total: null, limit: 200}), false);
  assert.equal(endsFolder({nodes: Array(100), position: 0, total: null, limit: 100}), false, "a server cap of 100 is not the end");
  assert.equal(endsFolder({nodes: Array(200), position: 200, total: 400, limit: 200}), true);
});

test("a file's icon and kind come from its media type, else from its extension", () => {
  const file = (name: string, mediaType?: string) => ({"@type": "File", id: "f", parentId: null, name, mediaType});
  assert.deepEqual(fileKind(file("a.mp4", "video/mp4")), {icon: "video_file", label: "Video"});
  assert.deepEqual(fileKind(file("x", "application/pdf")), {icon: "picture_as_pdf", label: "PDF"});
  assert.deepEqual(fileKind(file("x", "text/plain; charset=utf-8")), {icon: "description", label: "Text"});
  assert.deepEqual(fileKind(file("x", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")).label, "Spreadsheet");
  assert.equal(fileKind(file("song.FLAC", "application/octet-stream")).icon, "audio_file", "octet-stream falls back to the extension");
  assert.equal(fileKind(file("photo.webp")).icon, "image");
  assert.deepEqual(fileKind(file("blob.bin")), {icon: "draft", label: "File"});
  assert.deepEqual(fileKind(file(".hidden")), {icon: "draft", label: "File"});
  assert.deepEqual(fileKind({"@type": "Directory", id: "d", parentId: null, name: "d"}), {icon: "folder", label: "Folder"});
  assert.equal(fileKind(file("x", "image/png")).label, "Image", "the declared type wins over the extension");
});

test("where a file is used says only what was checked, and leaves the file itself out", () => {
  const usage = fixture.usage;
  assert.deepEqual(usageSummary(usage, "file"), {
    lines: ["No other file shares its stored content", "Not attached to any email"], checked: "Checked: Files, Mail"});
  assert.deepEqual(usageSummary({blobId: "b", types: ["Email"], references: {Email: ["m1", "m2"]}}, "x"),
    {lines: ["Attached to 2 emails"], checked: "Checked: Mail"});
  assert.equal(usageSummary({blobId: "b", types: ["FileNode"], references: {FileNode: ["x", "y"]}}, "x").lines[0], "Also stored as 1 other file");
  assert.equal(usageError("Blob lookup is unavailable."), "This server does not say where files are used.");
  assert.equal(usageError("timed out"), "Could not check where this file is used.");
});
