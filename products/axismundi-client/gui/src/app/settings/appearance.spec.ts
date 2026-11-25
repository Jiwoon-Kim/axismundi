import assert from "node:assert/strict";
import test from "node:test";
import { appearanceAttributes, readAppearance } from "./appearance.ts";

test("missing, malformed and unknown values fall back to the default appearance", () => {
  const expected = {brightness: "system", contrast: "default", accent: "azure-blue", mailBodyTheme: "app"};
  for (const stored of [null, "{", "42", '{"brightness":"night","contrast":false,"accent":"unknown"}', '{"contrast":"system"}', '{"contrast":"standard"}']) {
    assert.deepEqual(readAppearance(stored), expected);
  }
});

test("stored choices map to independent root attributes regardless of JSON key order", () => {
  const stored = '{"accent":"cyan-orange","contrast":"high","brightness":"dark","mailBodyTheme":"light"}';
  assert.deepEqual(appearanceAttributes(readAppearance(stored)), {
    brightness: "dark", contrast: "high", accent: "cyan-orange", mailBodyTheme: "light",
  });
});
