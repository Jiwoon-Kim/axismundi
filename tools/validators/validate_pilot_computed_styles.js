const { chromium } = require("playwright");
const fs = require("fs");
const path = require("path");

const outDir = path.resolve(process.cwd(), "tmp", "phase3-computed-audit");
fs.mkdirSync(outDir, { recursive: true });

function isTransparent(value) {
  return (
    !value ||
    value === "transparent" ||
    value === "rgba(0, 0, 0, 0)" ||
    value === "rgba(0,0,0,0)"
  );
}

function assert(findings, condition, pageName, label, details) {
  if (!condition) {
    findings.push({ page: pageName, label, details });
  }
}

async function snapshot(page, selector, props) {
  return page.$eval(
    selector,
    (el, propNames) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      const data = {
        selector: el.matches ? el.tagName.toLowerCase() + (el.className ? "." + String(el.className).trim().replace(/\s+/g, ".") : "") : "",
        text: el.textContent.trim().replace(/\s+/g, " ").slice(0, 120),
        rect: {
          x: Math.round(rect.x),
          y: Math.round(rect.y),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
        },
      };
      for (const prop of propNames) {
        data[prop] = style.getPropertyValue(prop);
      }
      return data;
    },
    props
  );
}

async function optionalSnapshot(page, selector, props) {
  const handle = await page.$(selector);
  if (!handle) return null;
  return snapshot(page, selector, props);
}

async function pageBase(page, name, url) {
  const consoleErrors = [];
  page.on("console", (message) => {
    if (message.type() === "error") consoleErrors.push(message.text());
  });
  page.on("pageerror", (error) => consoleErrors.push(error.message));
  await page.goto(url, { waitUntil: "networkidle" });
  const overflowX = await page.evaluate(() =>
    Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth)
  );
  return { name, url, consoleErrors, overflowX };
}

async function run() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 390, height: 900 }, deviceScaleFactor: 1 });
  const findings = [];
  const report = {
    generatedAt: new Date().toISOString(),
    viewport: { width: 390, height: 900 },
    pages: {},
    findings,
  };

  const pattern = await context.newPage();
  const patternBase = await pageBase(pattern, "pattern-qa", "http://localhost:8888/?page_id=10");
  report.pages.pattern = patternBase;
  report.pages.pattern.styles = {};

  const buttonProps = [
    "background-color",
    "color",
    "border-top-width",
    "border-top-style",
    "border-radius",
    "box-shadow",
    "padding-left",
    "padding-right",
  ];
  const buttonSelectors = {
    fill: ".wp-block-button.is-style-fill .wp-block-button__link",
    tonal: ".wp-block-button.is-style-tonal .wp-block-button__link",
    elevated: ".wp-block-button.is-style-elevated .wp-block-button__link",
    outline: ".wp-block-button.is-style-outline .wp-block-button__link",
    text: ".wp-block-button.is-style-text .wp-block-button__link",
  };
  for (const [key, selector] of Object.entries(buttonSelectors)) {
    report.pages.pattern.styles[`button-${key}`] = await optionalSnapshot(pattern, selector, buttonProps);
    assert(findings, !!report.pages.pattern.styles[`button-${key}`], "pattern-qa", `button ${key} exists`, { selector });
  }

  const fill = report.pages.pattern.styles["button-fill"];
  const outline = report.pages.pattern.styles["button-outline"];
  const text = report.pages.pattern.styles["button-text"];
  if (fill) assert(findings, !isTransparent(fill["background-color"]), "pattern-qa", "filled button has non-transparent container", fill);
  if (outline) {
    assert(findings, outline["border-top-width"] === "0px", "pattern-qa", "outlined button native border reset", outline);
    assert(findings, outline["box-shadow"] !== "none", "pattern-qa", "outlined button has inset outline", outline);
    assert(findings, isTransparent(outline["background-color"]), "pattern-qa", "outlined button background transparent", outline);
  }
  if (text) assert(findings, isTransparent(text["background-color"]), "pattern-qa", "text button background transparent", text);

  const searchRoot = await optionalSnapshot(pattern, ".wp-block-search.is-style-filled-search", [
    "background-color",
    "border-top-width",
    "border-radius",
    "box-shadow",
  ]);
  report.pages.pattern.styles.searchRoot = searchRoot;
  assert(findings, !!searchRoot, "pattern-qa", "filled-search block exists", {});
  if (searchRoot) {
    assert(findings, searchRoot["border-top-width"] === "0px", "pattern-qa", "filled-search border reset", searchRoot);
    assert(findings, !isTransparent(searchRoot["background-color"]), "pattern-qa", "filled-search container visible", searchRoot);
  }

  await pattern.screenshot({ path: path.join(outDir, "pattern-qa-390.png"), fullPage: true });

  const prose = await context.newPage();
  const proseBase = await pageBase(prose, "single-prose", "http://localhost:8888/?p=1");
  report.pages.prose = proseBase;
  report.pages.prose.styles = {};

  const proseSelectors = {
    postContent: ".wp-block-post-content",
    paragraph: ".wp-block-post-content p",
    heading: ".wp-block-post-content h2",
    inlineCode: ".wp-block-post-content p code",
    codeBlock: ".wp-block-post-content .wp-block-code, .wp-block-post-content pre",
    quote: ".wp-block-post-content .wp-block-quote, .wp-block-post-content blockquote",
    quoteCite: ".wp-block-post-content .wp-block-quote cite, .wp-block-post-content blockquote cite",
    list: ".wp-block-post-content ul, .wp-block-post-content ol",
    separator: ".wp-block-post-content .wp-block-separator",
    defaultTableCell: ".wp-block-post-content .wp-block-table:not(.is-style-stripes) tbody tr:first-child td:first-child",
    stripeOddRow: ".wp-block-post-content .wp-block-table.is-style-stripes tbody tr:nth-child(odd)",
    stripeOddCell: ".wp-block-post-content .wp-block-table.is-style-stripes tbody tr:nth-child(odd) td:first-child",
    stripeEvenRow: ".wp-block-post-content .wp-block-table.is-style-stripes tbody tr:nth-child(even)",
    stripeEvenCell: ".wp-block-post-content .wp-block-table.is-style-stripes tbody tr:nth-child(even) td:first-child",
  };
  const proseProps = [
    "font-family",
    "font-size",
    "line-height",
    "background-color",
    "color",
    "border-top-width",
    "border-bottom-width",
    "border-bottom-color",
    "border-left-width",
    "border-left-color",
    "padding-top",
    "padding-left",
    "margin-top",
  ];
  for (const [key, selector] of Object.entries(proseSelectors)) {
    report.pages.prose.styles[key] = await optionalSnapshot(prose, selector, proseProps);
    assert(findings, !!report.pages.prose.styles[key], "single-prose", `${key} selector exists`, { selector });
  }

  const inlineCode = report.pages.prose.styles.inlineCode;
  const codeBlock = report.pages.prose.styles.codeBlock;
  const quote = report.pages.prose.styles.quote;
  const quoteCite = report.pages.prose.styles.quoteCite;
  const separator = report.pages.prose.styles.separator;
  const defaultCell = report.pages.prose.styles.defaultTableCell;
  const stripeOddRow = report.pages.prose.styles.stripeOddRow;
  const stripeOddCell = report.pages.prose.styles.stripeOddCell;
  const stripeEvenRow = report.pages.prose.styles.stripeEvenRow;
  const stripeEvenCell = report.pages.prose.styles.stripeEvenCell;

  if (inlineCode) assert(findings, !isTransparent(inlineCode["background-color"]), "single-prose", "inline code has visible token surface", inlineCode);
  if (codeBlock) assert(findings, !isTransparent(codeBlock["background-color"]), "single-prose", "code block has visible token surface", codeBlock);
  if (quote) assert(findings, quote["border-left-width"] !== "0px", "single-prose", "quote has leading indicator", quote);
  if (quoteCite) assert(findings, quoteCite.color !== report.pages.prose.styles.paragraph?.color, "single-prose", "quote cite uses secondary color", quoteCite);
  if (separator) assert(findings, separator["border-top-width"] !== "0px" || separator["border-bottom-width"] !== "0px", "single-prose", "separator visible", separator);
  if (defaultCell) {
    assert(findings, defaultCell["border-top-width"] === "0px", "single-prose", "default table no top cell border", defaultCell);
    assert(findings, defaultCell["border-bottom-width"] !== "0px", "single-prose", "default table has bottom separator", defaultCell);
  }
  if (stripeOddRow) assert(findings, isTransparent(stripeOddRow["background-color"]), "single-prose", "stripe odd row background reset", stripeOddRow);
  if (stripeOddCell) assert(findings, isTransparent(stripeOddCell["background-color"]), "single-prose", "stripe odd cell background reset", stripeOddCell);
  if (stripeEvenRow) assert(findings, !isTransparent(stripeEvenRow["background-color"]), "single-prose", "stripe even row has M3 band", stripeEvenRow);
  if (stripeEvenCell) assert(findings, !isTransparent(stripeEvenCell["background-color"]), "single-prose", "stripe even cell has M3 band", stripeEvenCell);

  await prose.screenshot({ path: path.join(outDir, "single-prose-390.png"), fullPage: true });

  const front = await context.newPage();
  const frontBase = await pageBase(front, "front", "http://localhost:8888/");
  report.pages.front = frontBase;
  report.pages.front.styles = {};
  report.pages.front.styles.fill = await optionalSnapshot(front, ".wp-block-button.is-style-fill .wp-block-button__link", buttonProps);
  report.pages.front.styles.outline = await optionalSnapshot(front, ".wp-block-button.is-style-outline .wp-block-button__link", buttonProps);
  await front.screenshot({ path: path.join(outDir, "front-390.png"), fullPage: true });

  for (const pageReport of [report.pages.pattern, report.pages.prose, report.pages.front]) {
    assert(findings, pageReport.overflowX === 0, pageReport.name, "horizontal overflow is zero", {
      overflowX: pageReport.overflowX,
    });
    assert(findings, pageReport.consoleErrors.length === 0, pageReport.name, "console/page errors are zero", {
      consoleErrors: pageReport.consoleErrors,
    });
  }

  fs.writeFileSync(path.join(outDir, "computed-style-audit.json"), JSON.stringify(report, null, 2));
  await browser.close();

  if (findings.length) {
    console.error(JSON.stringify(findings, null, 2));
    process.exit(1);
  }

  console.log("computed-style audit PASS");
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
