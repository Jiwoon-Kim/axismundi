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

async function resolvedColor(page, value) {
  return page.evaluate((colorValue) => {
    const probe = document.createElement("span");
    probe.style.color = colorValue;
    probe.style.position = "absolute";
    probe.style.visibility = "hidden";
    document.body.appendChild(probe);
    const resolved = getComputedStyle(probe).color;
    probe.remove();
    return resolved;
  }, value);
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

async function forceTheme(page, mode) {
  await page.evaluate((themeMode) => {
    if (themeMode === "auto") {
      document.documentElement.removeAttribute("data-theme");
    } else {
      document.documentElement.setAttribute("data-theme", themeMode);
    }
  }, mode);
}

async function themeSnapshot(page) {
  return page.evaluate(() => {
    const style = getComputedStyle(document.documentElement);
    return {
      attr: document.documentElement.getAttribute("data-theme"),
      primary: style.getPropertyValue("--md-sys-color-primary").trim(),
      background: style.getPropertyValue("--md-sys-color-background").trim(),
      surface: style.getPropertyValue("--md-sys-color-surface").trim(),
      bodyBackground: getComputedStyle(document.body).backgroundColor,
      bodyColor: getComputedStyle(document.body).color,
    };
  });
}

async function auditThemeMode(context, findings, report, mode, url, pageName) {
  const page = await context.newPage();
  const base = await pageBase(page, `${pageName}-${mode}`, url);
  await forceTheme(page, mode);
  const snapshotData = await themeSnapshot(page);
  const fillButton = await optionalSnapshot(page, ".wp-block-button.is-style-fill .wp-block-button__link", [
    "background-color",
    "color",
  ]);
  const rootAttr = await page.evaluate(() => document.documentElement.getAttribute("data-theme"));

  assert(findings, rootAttr === mode, `${pageName}-${mode}`, "forced data-theme sticks", {
    expected: mode,
    actual: rootAttr,
  });
  assert(findings, !isTransparent(snapshotData.bodyBackground), `${pageName}-${mode}`, "body background resolves", snapshotData);
  assert(findings, !!fillButton, `${pageName}-${mode}`, "filled button exists under forced theme", {});
  if (fillButton) {
    assert(findings, !isTransparent(fillButton["background-color"]), `${pageName}-${mode}`, "filled button background resolves", fillButton);
  }

  report.themeMatrix[`${pageName}-${mode}`] = {
    ...base,
    theme: snapshotData,
    fillButton,
  };
  await page.close();
}

async function auditPilotThemeToggle(context, findings, report) {
  const page = await context.newPage();
  await pageBase(page, "front-theme-toggle", "http://localhost:8888/");

  const darkButton = await page.$('[data-theme-set="dark"]');
  const lightButton = await page.$('[data-theme-set="light"]');
  assert(findings, !!darkButton && !!lightButton, "front-theme-toggle", "theme toggle buttons exist", {});
  if (!darkButton || !lightButton) {
    await page.close();
    return;
  }

  await darkButton.click();
  const dark = await themeSnapshot(page);
  await lightButton.click();
  const light = await themeSnapshot(page);

  assert(findings, dark.attr === "dark", "front-theme-toggle", "dark button sets data-theme", dark);
  assert(findings, light.attr === "light", "front-theme-toggle", "light button sets data-theme", light);
  assert(findings, dark.background !== light.background, "front-theme-toggle", "light/dark sys backgrounds differ", {
    dark: dark.background,
    light: light.background,
  });
  assert(findings, dark.bodyBackground !== light.bodyBackground, "front-theme-toggle", "visible body background responds", {
    dark: dark.bodyBackground,
    light: light.bodyBackground,
  });

  report.themeToggle = { dark, light };
  await page.close();
}

async function run() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 390, height: 900 }, deviceScaleFactor: 1 });
  const findings = [];
  const report = {
    generatedAt: new Date().toISOString(),
    viewport: { width: 390, height: 900 },
    pages: {},
    themeMatrix: {},
    themeToggle: {},
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
    const expectedOutlinedColor = await resolvedColor(pattern, "var(--md-sys-color-on-surface-variant)");
    const expectedOutlinedOutline = await resolvedColor(pattern, "var(--md-sys-color-outline-variant)");
    assert(findings, outline["border-top-width"] === "0px", "pattern-qa", "outlined button native border reset", outline);
    assert(findings, outline["box-shadow"] !== "none", "pattern-qa", "outlined button has inset outline", outline);
    assert(findings, outline.color === expectedOutlinedColor, "pattern-qa", "outlined button matches M3 on-surface-variant", {
      actual: outline.color,
      expected: expectedOutlinedColor,
      outline,
    });
    assert(findings, outline["box-shadow"].includes(expectedOutlinedOutline), "pattern-qa", "outlined button outline matches M3 outline-variant", {
      actual: outline["box-shadow"],
      expected: expectedOutlinedOutline,
      outline,
    });
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
    tableThead: ".wp-block-post-content .wp-block-table thead",
    stripeFigure: ".wp-block-post-content .wp-block-table.is-style-stripes",
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
    "border-top-color",
    "border-bottom-width",
    "border-bottom-color",
    "border-block-end-width",
    "border-block-end-color",
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
  const tableThead = report.pages.prose.styles.tableThead;
  const stripeFigure = report.pages.prose.styles.stripeFigure;
  const defaultCell = report.pages.prose.styles.defaultTableCell;
  const stripeOddRow = report.pages.prose.styles.stripeOddRow;
  const stripeOddCell = report.pages.prose.styles.stripeOddCell;
  const stripeEvenRow = report.pages.prose.styles.stripeEvenRow;
  const stripeEvenCell = report.pages.prose.styles.stripeEvenCell;

  if (inlineCode) assert(findings, !isTransparent(inlineCode["background-color"]), "single-prose", "inline code has visible token surface", inlineCode);
  if (codeBlock) assert(findings, !isTransparent(codeBlock["background-color"]), "single-prose", "code block has visible token surface", codeBlock);
  if (quote) assert(findings, quote["border-left-width"] !== "0px", "single-prose", "quote has leading indicator", quote);
  if (quoteCite) assert(findings, quoteCite.color !== report.pages.prose.styles.paragraph?.color, "single-prose", "quote cite uses secondary color", quoteCite);
  if (separator) {
    assert(
      findings,
      separator["border-top-width"] !== "0px" ||
        separator["border-bottom-width"] !== "0px" ||
        !isTransparent(separator["background-color"]),
      "single-prose",
      "separator visible",
      separator
    );
  }
  if (tableThead) {
    assert(findings, tableThead["border-bottom-width"] === "0px", "single-prose", "core table thead 3px border reset", tableThead);
  }
  if (stripeFigure) {
    assert(findings, stripeFigure["border-bottom-color"] !== "rgb(240, 240, 240)", "single-prose", "core stripes wrapper #f0f0f0 border reset", stripeFigure);
    assert(findings, stripeFigure["border-bottom-width"] === "1px", "single-prose", "stripes wrapper keeps M3 bottom border", stripeFigure);
  }
  if (defaultCell) {
    assert(findings, defaultCell["border-top-width"] === "0px", "single-prose", "default table no top cell border", defaultCell);
    assert(findings, defaultCell["border-bottom-width"] !== "0px", "single-prose", "default table has bottom separator", defaultCell);
  }
  if (stripeOddRow) assert(findings, isTransparent(stripeOddRow["background-color"]), "single-prose", "stripe odd row background reset", stripeOddRow);
  if (stripeOddCell) assert(findings, isTransparent(stripeOddCell["background-color"]), "single-prose", "stripe odd cell background reset", stripeOddCell);
  if (stripeEvenRow) assert(findings, !isTransparent(stripeEvenRow["background-color"]), "single-prose", "stripe even row has M3 band", stripeEvenRow);
  if (stripeEvenCell) assert(findings, !isTransparent(stripeEvenCell["background-color"]), "single-prose", "stripe even cell has M3 band", stripeEvenCell);

  await prose.screenshot({ path: path.join(outDir, "single-prose-390.png"), fullPage: true });

  const styleguideBlocks = await context.newPage();
  const styleguideBlocksUrl = "file:///" + path.resolve(process.cwd(), "styleguide", "blocks.html").replace(/\\/g, "/") + "#blocks-table";
  const styleguideBase = await pageBase(styleguideBlocks, "styleguide-blocks", styleguideBlocksUrl);
  report.pages.styleguideBlocks = styleguideBase;
  report.pages.styleguideBlocks.styles = {};
  report.pages.styleguideBlocks.styles.tableThead = await optionalSnapshot(styleguideBlocks, "#blocks-table .wp-block-table thead", proseProps);
  report.pages.styleguideBlocks.styles.stripeFigure = await optionalSnapshot(styleguideBlocks, "#blocks-table .wp-block-table.is-style-stripes", proseProps);
  report.pages.styleguideBlocks.styles.defaultTableCell = await optionalSnapshot(styleguideBlocks, "#blocks-table .wp-block-table:not(.is-style-stripes) tbody tr:first-child td:first-child", proseProps);
  report.pages.styleguideBlocks.styles.stripeOddRow = await optionalSnapshot(styleguideBlocks, "#blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(odd)", proseProps);
  report.pages.styleguideBlocks.styles.stripeEvenRow = await optionalSnapshot(styleguideBlocks, "#blocks-table .wp-block-table.is-style-stripes tbody tr:nth-child(even)", proseProps);

  const sgThead = report.pages.styleguideBlocks.styles.tableThead;
  const sgStripeFigure = report.pages.styleguideBlocks.styles.stripeFigure;
  const sgDefaultCell = report.pages.styleguideBlocks.styles.defaultTableCell;
  const sgOdd = report.pages.styleguideBlocks.styles.stripeOddRow;
  const sgEven = report.pages.styleguideBlocks.styles.stripeEvenRow;
  assert(findings, !!sgThead, "styleguide-blocks", "table thead exists", {});
  assert(findings, !!sgStripeFigure, "styleguide-blocks", "stripe table exists", {});
  if (sgThead) assert(findings, sgThead["border-bottom-width"] === "0px", "styleguide-blocks", "core table thead 3px border reset", sgThead);
  if (sgStripeFigure) assert(findings, sgStripeFigure["border-bottom-color"] !== "rgb(240, 240, 240)", "styleguide-blocks", "core stripes wrapper #f0f0f0 border reset", sgStripeFigure);
  if (sgStripeFigure) assert(findings, sgStripeFigure["border-bottom-width"] === "1px", "styleguide-blocks", "stripes wrapper keeps M3 bottom border", sgStripeFigure);
  if (sgDefaultCell) {
    assert(findings, sgDefaultCell["border-top-width"] === "0px", "styleguide-blocks", "default table no top cell border", sgDefaultCell);
    assert(findings, sgDefaultCell["border-bottom-width"] !== "0px", "styleguide-blocks", "default table has bottom separator", sgDefaultCell);
  }
  if (sgOdd) assert(findings, isTransparent(sgOdd["background-color"]), "styleguide-blocks", "stripe odd row background reset", sgOdd);
  if (sgEven) assert(findings, !isTransparent(sgEven["background-color"]), "styleguide-blocks", "stripe even row has M3 band", sgEven);
  await styleguideBlocks.screenshot({ path: path.join(outDir, "styleguide-blocks-table-390.png"), fullPage: true });

  const front = await context.newPage();
  const frontBase = await pageBase(front, "front", "http://localhost:8888/");
  report.pages.front = frontBase;
  report.pages.front.styles = {};
  report.pages.front.styles.fill = await optionalSnapshot(front, ".wp-block-button.is-style-fill .wp-block-button__link", buttonProps);
  report.pages.front.styles.outline = await optionalSnapshot(front, ".wp-block-button.is-style-outline .wp-block-button__link", buttonProps);
  await front.screenshot({ path: path.join(outDir, "front-390.png"), fullPage: true });

  await auditThemeMode(context, findings, report, "light", "http://localhost:8888/", "front");
  await auditThemeMode(context, findings, report, "dark", "http://localhost:8888/", "front");
  await auditThemeMode(context, findings, report, "light", styleguideBlocksUrl, "styleguide-blocks");
  await auditThemeMode(context, findings, report, "dark", styleguideBlocksUrl, "styleguide-blocks");
  await auditPilotThemeToggle(context, findings, report);

  for (const pageReport of [report.pages.pattern, report.pages.prose, report.pages.styleguideBlocks, report.pages.front]) {
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
