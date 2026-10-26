import { readFile, writeFile } from 'node:fs/promises';
import openapiTS, { astToString } from 'openapi-typescript';

// Run on the host with OPENAPI_URL=http://localhost:<php-port>/api/doc.json,
// or inside Compose with OPENAPI_URL=http://php/api/doc.json.
const source = process.env.OPENAPI_URL || 'http://localhost:8080/api/doc.json';
const output = new URL('../src/api/schema.d.ts', import.meta.url);
try {
  const response = await fetch(new URL(source), { signal: AbortSignal.timeout(15_000), headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error(`Backend documentation returned HTTP ${response.status}.`);
  const document = await response.json();
  const ast = await openapiTS(document);
  const generated = astToString(ast);
  if (process.argv.includes('--check')) {
    const current = await readFile(output, 'utf8').catch(() => '');
    if (current !== generated) throw new Error('OpenAPI types are stale or missing. Run npm run openapi and review the generated changes.');
    console.log('OpenAPI types match the backend.');
  } else {
    await writeFile(output, generated);
    console.log(`Generated src/api/schema.d.ts from ${source}`);
  }
} catch (error) {
  console.error(`OpenAPI generation failed: ${error.message}`);
  console.error('Start the backend and set OPENAPI_URL if its API documentation uses a different port. Existing types were preserved.');
  process.exitCode = 1;
}
