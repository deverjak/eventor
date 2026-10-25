// Checks every relative link and image in docs/architecture/**/*.md: the target file must exist,
// and a #fragment must match a heading of the target (GitHub slug rules, simplified).
// Run: node docs/architecture/_diagrams/check-links.mjs
import { readFileSync, readdirSync, statSync, existsSync } from "node:fs";
import { join, resolve, dirname, relative } from "node:path";

const root = resolve(import.meta.dirname, "..");

function* mdFiles(dir) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) {
      if (name !== "out" && name !== "node_modules") yield* mdFiles(p);
    } else if (name.endsWith(".md")) yield p;
  }
}

const slug = (h) =>
  h
    .trim()
    .toLowerCase()
    .replace(/[`*_~]/g, "")
    .replace(/[^\p{L}\p{N}\s-]/gu, "")
    .replace(/\s/g, "-");

const headingsCache = new Map();
function headings(file) {
  if (!headingsCache.has(file)) {
    const text = readFileSync(file, "utf8").replace(/```[\s\S]*?```/g, "");
    const seen = {};
    const set = new Set();
    for (const m of text.matchAll(/^#{1,6}\s+(.+)$/gm)) {
      let s = slug(m[1]);
      if (seen[s] !== undefined) s = `${s}-${++seen[s]}`;
      else seen[s] = 0;
      set.add(s);
    }
    headingsCache.set(file, set);
  }
  return headingsCache.get(file);
}

let bad = 0;
let count = 0;
for (const file of mdFiles(root)) {
  const text = readFileSync(file, "utf8").replace(/```[\s\S]*?```/g, "");
  for (const m of text.matchAll(/!?\[[^\]]*\]\(([^)\s]+)\)/g)) {
    const href = m[1];
    if (/^(https?:|mailto:)/.test(href)) continue;
    count++;
    const [path, frag] = href.split("#");
    const target = path ? resolve(dirname(file), decodeURI(path)) : file;
    const where = relative(root, file);
    if (!existsSync(target)) {
      console.log(`MISSING  ${where} -> ${href}`);
      bad++;
      continue;
    }
    if (frag && target.endsWith(".md") && !headings(target).has(frag)) {
      console.log(`ANCHOR   ${where} -> ${href}`);
      bad++;
    }
  }
}
console.log(`${count} links checked, ${bad} broken`);
process.exit(bad ? 1 : 0);
