import assert from "node:assert/strict";
import test from "node:test";
import { scaffoldLayout } from "./scaffold-layout.ts";

test("navigation rail keeps search over the list and reads beside it", () => {
  assert.deepEqual(scaffoldLayout("navigation-rail", true),
    {appBarScope: "pane", navigation: "rail", reading: "side", compose: "fab"});
});

test("search app bar spans the window, switches apps by menu and opens a message in place of the list", () => {
  assert.deepEqual(scaffoldLayout("search-app-bar", true),
    {appBarScope: "window", navigation: "menu", reading: "replace", compose: "panel"});
});

test("a compact window is the same for both settings: one pane at a time", () => {
  assert.deepEqual(scaffoldLayout("search-app-bar", false), scaffoldLayout("navigation-rail", false));
  assert.equal(scaffoldLayout("navigation-rail", false).reading, "replace");
  assert.equal(scaffoldLayout("navigation-rail", false).appBarScope, "pane");
});
