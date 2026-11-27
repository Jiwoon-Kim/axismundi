import assert from "node:assert/strict";
import test from "node:test";
import { settingsCrumbs } from "./settings-crumbs.ts";

const names = (id: string) => id === "2" ? "alice@example.org" : null;

test("the trail follows the path, and only earlier crumbs link", () => {
  assert.deepEqual(settingsCrumbs("/settings/connections/2?tab=accounts&account=b", names), [
    {label: "Settings", link: "/settings"},
    {label: "Connections", link: "/settings/connections"},
    {label: "alice@example.org", link: null},
  ]);
  assert.deepEqual(settingsCrumbs("/settings/connections/2/jmap?tab=session", names).map(c => [c.label, c.link]), [
    ["Settings", "/settings"], ["Connections", "/settings/connections"],
    ["alice@example.org", "/settings/connections/2"], ["JMAP", null],
  ]);
  assert.deepEqual(settingsCrumbs("/settings/appearance", names).map(c => c.label), ["Settings", "Appearance"]);
  assert.equal(settingsCrumbs("/settings/connections/9", names).at(-1)!.label, "Connection");
  assert.deepEqual(settingsCrumbs("/mail", names), []);
});
