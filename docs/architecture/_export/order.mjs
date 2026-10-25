// Reading order of the architecture book (book.mjs). A file that is not listed here is not in the PDF.
import { existsSync } from "node:fs";
import { join } from "node:path";

const chapters = [
  "01-kontext-a-nasazeni",
  "02-moduly-a-hranice",
  "03-data",
  "04-klicove-cesty",
  "05-stavove-automaty",
  "06-asynchronni-svet",
  "07-integrace",
  "08-prurezove-koncepty",
  "09-symfony-v-architekture",
  "10-kvalita-a-testovani",
  "11-rizika-a-kompromisy",
  "12-plan-implementace",
];

// Only the solution architecture itself: ADRs (docs/adr) and decision papers (navrhy/) stay in the repository.
export function bookOrder(root) {
  return [
    { file: "README.md" },
    { file: "00-slovnik.md" },
    ...chapters.flatMap((c) =>
      c === "06-asynchronni-svet"
        ? [{ file: `${c}/README.md` }, { file: `${c}/katalog-udalosti.md`, answers: true }]
        : [{ file: `${c}/README.md` }],
    ),
  ].filter((d) => existsSync(join(root, d.file)));
}
