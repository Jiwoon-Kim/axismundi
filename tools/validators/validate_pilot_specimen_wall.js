const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

const outDir = path.resolve(process.cwd(), "tmp", "phase1-specimen-wall");
fs.mkdirSync(outDir, { recursive: true });

const url = "http://localhost:8888/?pagename=axismundi-core-block-specimen-wall";

const tier1Families = [
  "core-paragraph",
  "core-heading",
  "core-list",
  "core-quote",
  "core-code",
  "core-table",
  "core-buttons",
  "core-search",
  "core-separator",
  "core-group",
  "core-columns",
];

const specimenEntries = [
  { id: "paragraph-default", family: "core-paragraph", selector: '[data-ax-specimen-id="core-paragraph"] p' },
  { id: "heading-h1", family: "core-heading", selector: '[data-ax-specimen-id="core-heading"] h1' },
  { id: "heading-h2", family: "core-heading", selector: '[data-ax-specimen-id="core-heading"] h2' },
  { id: "heading-h3", family: "core-heading", selector: '[data-ax-specimen-id="core-heading"] h3' },
  { id: "list-unordered", family: "core-list", selector: '[data-ax-specimen-id="core-list"] ul:not(.is-style-list-segmented)' },
  { id: "list-ordered", family: "core-list", selector: '[data-ax-specimen-id="core-list"] ol' },
  { id: "list-segmented", family: "core-list", selector: '[data-ax-specimen-variant="list-segmented"]' },
  { id: "quote-default", family: "core-quote", selector: '[data-ax-specimen-id="core-quote"] blockquote' },
  { id: "code-block", family: "core-code", selector: '[data-ax-specimen-id="core-code"] pre' },
  { id: "table-default", family: "core-table", selector: '[data-ax-specimen-variant="table-default"]' },
  { id: "table-stripes", family: "core-table", selector: '[data-ax-specimen-variant="table-stripes"]' },
  { id: "table-footer", family: "core-table", selector: '[data-ax-specimen-variant="table-footer"] tfoot' },
  { id: "button-fill", family: "core-buttons", selector: '[data-ax-specimen-variant="button-fill"] .wp-block-button__link' },
  { id: "button-outline", family: "core-buttons", selector: '[data-ax-specimen-variant="button-outline"] .wp-block-button__link' },
  { id: "button-tonal", family: "core-buttons", selector: '[data-ax-specimen-variant="button-tonal"] .wp-block-button__link' },
  { id: "button-elevated", family: "core-buttons", selector: '[data-ax-specimen-variant="button-elevated"] .wp-block-button__link' },
  { id: "button-text", family: "core-buttons", selector: '[data-ax-specimen-variant="button-text"] .wp-block-button__link' },
  { id: "search-default", family: "core-search", selector: '[data-ax-specimen-id="core-search"] .wp-block-search:not(.is-style-filled-search)' },
  { id: "search-filled", family: "core-search", selector: '[data-ax-specimen-id="core-search"] .wp-block-search.is-style-filled-search' },
  { id: "separator-default", family: "core-separator", selector: '[data-ax-specimen-variant="separator-default"]' },
  { id: "separator-inset", family: "core-separator", selector: '[data-ax-specimen-variant="separator-inset"]' },
  { id: "separator-middle-inset", family: "core-separator", selector: '[data-ax-specimen-variant="separator-middle-inset"]' },
  { id: "group-card-filled", family: "core-group", selector: '[data-ax-specimen-variant="group-card-filled"]' },
  { id: "group-card-elevated", family: "core-group", selector: '[data-ax-specimen-variant="group-card-elevated"]' },
  { id: "group-card-outlined", family: "core-group", selector: '[data-ax-specimen-variant="group-card-outlined"]' },
  { id: "columns-default", family: "core-columns", selector: '[data-ax-specimen-id="core-columns"] .wp-block-columns' },
];

const snapshotProps = [
  "display",
  "font-family",
  "font-size",
  "line-height",
  "background-color",
  "color",
  "border-top-width",
  "border-top-style",
  "border-top-color",
  "border-bottom-width",
  "border-bottom-style",
  "border-bottom-color",
  "box-shadow",
  "text-decoration-line",
  "user-select",
  "padding-top",
  "padding-right",
  "padding-bottom",
  "padding-left",
  "margin-top",
  "margin-bottom",
];

function assert(findings, condition, label, details) {
  if (!condition) findings.push({ label, details });
}

async function snapshotElement(page, selector) {
  return page.$eval(
    selector,
    (el, props) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      const data = {
        tagName: el.tagName.toLowerCase(),
        className: String(el.className || ""),
        text: el.textContent.trim().replace(/\s+/g, " ").slice(0, 160),
        rect: {
          x: Math.round(rect.x),
          y: Math.round(rect.y),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
        },
      };
      for (const prop of props) data[prop] = style.getPropertyValue(prop);
      return data;
    },
    snapshotProps
  );
}

async function run() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 390, height: 900 }, deviceScaleFactor: 1 });
  const page = await context.newPage();
  const findings = [];
  const consoleErrors = [];

  page.on("console", (message) => {
    if (message.type() === "error") consoleErrors.push(message.text());
  });
  page.on("pageerror", (error) => consoleErrors.push(error.message));

  const response = await page.goto(url, { waitUntil: "networkidle" });
  const status = response ? response.status() : null;
  const overflowX = await page.evaluate(() =>
    Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)
  );

  const families = {};
  for (const family of tier1Families) {
    const selector = `[data-ax-specimen-id="${family}"]`;
    const count = await page.locator(selector).count();
    families[family] = {
      selector,
      count,
      snapshot: count > 0 ? await snapshotElement(page, selector) : null,
    };
    assert(findings, count === 1, `${family} has exactly one stable anchor`, { selector, count });
  }

  const variants = await page.$$eval("[data-ax-specimen-variant]", (nodes) =>
    nodes.map((node) => ({
      id: node.getAttribute("data-ax-specimen-variant"),
      tagName: node.tagName.toLowerCase(),
      className: String(node.className || ""),
      text: node.textContent.trim().replace(/\s+/g, " ").slice(0, 120),
    }))
  );

  const entries = {};
  for (const entry of specimenEntries) {
    const count = await page.locator(entry.selector).count();
    entries[entry.id] = {
      family: entry.family,
      selector: entry.selector,
      count,
      snapshot: count > 0 ? await snapshotElement(page, entry.selector) : null,
    };
    assert(findings, count >= 1, `${entry.id} entry exists`, { selector: entry.selector, count });
  }

  assert(findings, status === 200, "specimen wall HTTP 200", { status, url });
  assert(findings, consoleErrors.length === 0, "console/page errors are zero", { consoleErrors });
  assert(findings, overflowX === 0, "horizontal overflow is zero", { overflowX });
  assert(findings, Object.values(families).filter((entry) => entry.count === 1).length === 11, "Tier 1 coverage is 11/11", families);

  const report = {
    generatedAt: new Date().toISOString(),
    url,
    status,
    viewport: { width: 390, height: 900 },
    overflowX,
    consoleErrors,
    tier1: {
      expected: tier1Families.length,
      represented: Object.values(families).filter((entry) => entry.count === 1).length,
      families,
    },
    variants,
    entries,
    findings,
  };

  await page.screenshot({ path: path.join(outDir, "specimen-wall-390.png"), fullPage: true });
  fs.writeFileSync(path.join(outDir, "specimen-wall-render-gate.json"), JSON.stringify(report, null, 2));
  await browser.close();

  if (findings.length) {
    console.error(JSON.stringify(findings, null, 2));
    process.exit(1);
  }

  console.log("specimen wall render gate PASS");
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
