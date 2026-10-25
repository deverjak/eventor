// Renders every generated diagram HTML under docs/architecture to a PNG next to it
// (diagram only, transparent background, 2x). Run: node docs/architecture/_diagrams/export.mjs
import { chromium } from "@playwright/test";
import { readdirSync, statSync } from "node:fs";
import { join, resolve } from "node:path";
import { pathToFileURL } from "node:url";

const root = resolve(import.meta.dirname, "..");

function* htmlFiles(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) { if (name !== "_export" && name !== "node_modules") yield* htmlFiles(p); }
    else if (name.endsWith(".html")) yield p;
  }
}

const only = process.argv.slice(2);
const browser = await chromium.launch();
const page = await browser.newPage({ deviceScaleFactor: 2, viewport: { width: 1400, height: 900 } });
for (const file of htmlFiles(root)) {
  if (only.length && !only.some((o) => file.includes(o))) continue;
  await page.goto(pathToFileURL(file).href);
  await page.waitForLoadState("networkidle");
  await page.evaluate(() => document.fonts.ready);
  const out = file.replace(/\.html$/, ".png");
  await page.locator("svg").first().screenshot({ path: out });
  console.log("png", out.slice(root.length + 1));
}
await browser.close();
