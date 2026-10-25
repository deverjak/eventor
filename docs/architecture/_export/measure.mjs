// Page budget: estimates how many A4 pages each document of the book takes in the PDF.
// Run after book.mjs: node docs/architecture/_export/measure.mjs
// The estimate lays the HTML out at the printable width and divides each section's height by the printable height.
import { chromium } from "@playwright/test";
import { join } from "node:path";
import { pathToFileURL } from "node:url";

const htmlPath = join(import.meta.dirname, "out", "eventor-architektura.html");
const mm = 96 / 25.4;
const width = Math.round((210 - 32) * mm);
const pageHeight = (297 - 36) * mm;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width, height: 1000 } });
await page.goto(pathToFileURL(htmlPath).href);
await page.emulateMedia({ media: "print" });
const rows = await page.$$eval("section.doc", (els) =>
  els.map((el) => ({ title: el.querySelector("h1")?.textContent ?? el.id, h: el.getBoundingClientRect().height })),
);
await browser.close();

let total = 0;
for (const r of rows) {
  const pages = r.h / pageHeight;
  total += Math.ceil(pages);
  console.log(`${pages.toFixed(1).padStart(5)}  ${r.title}`);
}
console.log(`~${total} stran (bez obálky, obsahu a předělů)`);
