// Builds ONE document from every chapter of docs/architecture: a self-contained HTML
// (diagrams embedded) and a PDF rendered from it by Playwright. Links between included documents
// become in-document anchors, other relative links are printed as repository paths.
// Run: node docs/architecture/_export/book.mjs   (diagram PNGs must exist: _diagrams/export.mjs)
// Output: docs/architecture/_export/out/eventor-architektura.{html,pdf} (not committed)
import { chromium } from "@playwright/test";
import { Marked } from "marked";
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from "node:fs";
import { dirname, extname, join, relative, resolve } from "node:path";
import { pathToFileURL } from "node:url";
import { bookOrder } from "./order.mjs";

const root = resolve(import.meta.dirname, "..");
const repo = resolve(root, "../..");
const outDir = join(import.meta.dirname, "out");

// Reading order: _export/order.mjs (shared with measure.mjs, the page budget).
const order = bookOrder(root);

const docId = (file) => "d-" + file.replace(/\.md$/, "").replace(/[^a-zA-Z0-9]+/g, "-").toLowerCase();
const included = new Map(order.map((d) => [resolve(root, d.file), docId(d.file)]));

// Same slug rules as _diagrams/check-links.mjs, so every anchor that passes the link check resolves here.
const slug = (h) =>
  h
    .trim()
    .toLowerCase()
    .replace(/[`*_~]/g, "")
    .replace(/[^\p{L}\p{N}\s-]/gu, "")
    .replace(/\s/g, "-");

const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
const mime = { ".png": "image/png", ".svg": "image/svg+xml", ".jpg": "image/jpeg", ".jpeg": "image/jpeg" };

function repoPath(abs, frag) {
  const rel = relative(repo, abs).split("\\").join("/");
  return `${rel}${frag ? "#" + frag : ""}`;
}

function renderDoc(entry) {
  const abs = resolve(root, entry.file);
  const id = docId(entry.file);
  const seen = {};
  let title = null;
  const md = readFileSync(abs, "utf8")
    .split(/\r?\n/)
    .filter((line) => !/^\[(←|Architektura\]\()/.test(line.trim())) // chapter navigation makes no sense in one document
    .join("\n");

  const rewrite = (href) => {
    if (/^(https?:|mailto:)/.test(href)) return { href, external: true };
    const [path, frag] = href.split("#");
    const target = path ? resolve(dirname(abs), decodeURI(path)) : abs;
    const inDoc = included.get(target);
    if (inDoc) return { href: frag ? `#${inDoc}--${frag}` : `#${inDoc}` };
    return { href: repoPath(target, frag), repo: true };
  };

  const marked = new Marked({ gfm: true });
  marked.use({
    renderer: {
      heading({ tokens, depth, text }) {
        let s = slug(text);
        if (seen[s] !== undefined) s = `${s}-${++seen[s]}`;
        else seen[s] = 0;
        if (depth === 1 && !title) title = text.replace(/[`*_]/g, "");
        return `<h${depth} id="${id}--${s}">${this.parser.parseInline(tokens)}</h${depth}>\n`;
      },
      link({ href, title: t, tokens }) {
        const r = rewrite(href);
        if (r.repo) return `${this.parser.parseInline(tokens)} <code class="path">${esc(r.href)}</code>`;
        const cls = r.external ? ' class="ext"' : "";
        return `<a href="${esc(r.href)}"${cls}${t ? ` title="${esc(t)}"` : ""}>${this.parser.parseInline(tokens)}</a>`;
      },
      image({ href, text }) {
        const file = resolve(dirname(abs), decodeURI(href));
        if (!existsSync(file)) return `<p class="missing">Chybí obrázek: ${esc(href)}</p>`;
        const data = readFileSync(file).toString("base64");
        const type = mime[extname(file).toLowerCase()] ?? "application/octet-stream";
        return `<figure><img src="data:${type};base64,${data}" alt="${esc(text)}"/></figure>`;
      },
    },
  });

  const html = marked.parse(md);
  const classes = ["doc", entry.answers ? "answers" : ""];
  const part = entry.part ? `<div class="part" id="${id}-part"><h1>${esc(entry.part)}</h1></div>\n` : "";
  return {
    id,
    title: title ?? entry.file,
    entry,
    html: `${part}<section class="${classes.join(" ").trim()}" id="${id}">\n${html}</section>\n`,
  };
}

const docs = order.map(renderDoc);

const today = new Date().toISOString().slice(0, 10);

const toc = docs
  .map((d) => {
    const part = d.entry.part ? `<li class="toc-part">${esc(d.entry.part)}</li>` : "";
    const cls = d.entry.answers ? ' class="toc-answers"' : "";
    return `${part}<li${cls}><a href="#${d.id}">${esc(d.title)}</a></li>`;
  })
  .join("\n");

const css = `
@page { size: A4; margin: 18mm 16mm 18mm 16mm; }
:root { --ink:#1f2330; --muted:#5a6377; --rule:#d9dce3; --accent:#c9541f; --paper:#ffffff; --code:#f3f4f7; }
* { box-sizing: border-box; }
html { background: var(--paper); }
body { font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: var(--ink); font-size: 10.5pt; line-height: 1.5; margin: 0 auto; max-width: 960px; padding: 0 16px; }
h1 { font-size: 22pt; font-weight: 600; margin: 0 0 10pt; padding-bottom: 6pt; border-bottom: 2px solid var(--ink); }
h2 { font-size: 15pt; font-weight: 600; margin: 18pt 0 6pt; break-after: avoid; }
h3 { font-size: 12pt; font-weight: 600; margin: 14pt 0 4pt; break-after: avoid; }
h4, h5, h6 { font-size: 10.5pt; margin: 10pt 0 4pt; break-after: avoid; }
p, li { orphans: 3; widows: 3; }
a { color: var(--accent); text-decoration: none; }
a.ext { color: #2e5aa8; }
code { font-family: Consolas, "Cascadia Mono", monospace; font-size: 0.88em; background: var(--code); padding: 0 3px; border-radius: 3px; overflow-wrap: anywhere; }
pre { background: var(--code); padding: 8pt 10pt; border-radius: 4px; white-space: pre-wrap; font-size: 8.5pt; break-inside: avoid; }
pre code { background: none; padding: 0; }
blockquote { margin: 8pt 0; padding: 4pt 12pt; border-left: 3px solid var(--rule); color: var(--muted); }
table { border-collapse: collapse; width: 100%; margin: 8pt 0; font-size: 8.8pt; }
th, td { border: 1px solid var(--rule); padding: 3pt 5pt; vertical-align: top; text-align: left; overflow-wrap: break-word; }
th { background: #f6f7f9; font-weight: 600; }
tr { break-inside: avoid; }
figure { margin: 10pt 0; text-align: center; break-inside: avoid; }
figure img { max-width: 100%; max-height: 235mm; height: auto; }
.missing { color: #b00020; }
code.path { font-size: 0.8em; color: var(--muted); }
section.doc { break-before: page; }
section.doc.answers { break-before: auto; margin-top: 18pt; padding-top: 10pt; border-top: 1px dashed var(--rule); }
section.doc.answers h1 { font-size: 15pt; border-bottom: none; }
section.wide table { font-size: 7.4pt; }
.part { break-before: page; min-height: 230mm; display: flex; align-items: center; }
.part h1 { font-size: 30pt; border: none; }
.cover { min-height: 250mm; display: flex; flex-direction: column; justify-content: center; }
.cover h1 { font-size: 34pt; border: none; margin-bottom: 4pt; }
.cover .sub { font-size: 13pt; color: var(--muted); }
.cover .meta { margin-top: 30pt; font-size: 9.5pt; color: var(--muted); }
nav.toc { break-before: page; }
nav.toc ol { list-style: none; padding: 0; }
nav.toc li { padding: 2pt 0; border-bottom: 1px dotted var(--rule); }
nav.toc li.toc-answers { padding-left: 16pt; font-size: 9.5pt; border-bottom: none; }
nav.toc li.toc-part { font-weight: 600; margin-top: 12pt; border-bottom: 1px solid var(--ink); }
@media screen { section.doc, .part, nav.toc { border-top: 1px solid var(--rule); margin-top: 40px; padding-top: 20px; } .part, .cover { min-height: auto; padding: 40px 0; } }
`;

const html = `<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Eventor · Solution architecture</title>
<style>${css}</style>
</head>
<body>
<div class="cover">
  <h1>Eventor</h1>
  <div class="sub">Solution architecture event platformy: moduly, data, toky, rozhodnutí a invarianty</div>
  <div class="meta">Cílová architektura (stav návrhu před implementací)<br>
  Vygenerováno ${esc(today)} z <code>docs/architecture/**</code> skriptem <code>docs/architecture/_export/book.mjs</code>.</div>
</div>
<nav class="toc"><h1>Obsah</h1><ol>
${toc}
</ol></nav>
${docs.map((d) => d.html).join("\n")}
</body>
</html>
`;

mkdirSync(outDir, { recursive: true });
const htmlPath = join(outDir, "eventor-architektura.html");
const pdfPath = join(outDir, "eventor-architektura.pdf");
writeFileSync(htmlPath, html);
console.log(`html ${relative(repo, htmlPath)} (${(Buffer.byteLength(html) / 1e6).toFixed(1)} MB, ${docs.length} kapitol)`);

if (!process.argv.includes("--html-only")) {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(pathToFileURL(htmlPath).href);
  await page.waitForLoadState("load");
  await page.pdf({
    path: pdfPath,
    format: "A4",
    printBackground: true,
    outline: true,
    tagged: true,
    displayHeaderFooter: true,
    headerTemplate: `<div style="font-size:7pt;color:#8a90a0;width:100%;padding:0 16mm;font-family:Segoe UI,Arial">Eventor · Solution architecture</div>`,
    footerTemplate: `<div style="font-size:7pt;color:#8a90a0;width:100%;padding:0 16mm;text-align:right;font-family:Segoe UI,Arial"><span class="pageNumber"></span> / <span class="totalPages"></span></div>`,
    margin: { top: "18mm", bottom: "18mm", left: "16mm", right: "16mm" },
  });
  await browser.close();
  console.log(`pdf  ${relative(repo, pdfPath)} (${(statSync(pdfPath).size / 1e6).toFixed(1)} MB)`);
}
