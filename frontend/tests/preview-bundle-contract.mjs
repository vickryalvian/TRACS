import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath, URL } from 'node:url';

const publicRoot = fileURLToPath(new URL('../../public/assets/react-dist/', import.meta.url));
const manifestPath = fileURLToPath(
  new URL('../../public/assets/react-dist/.vite/manifest.json', import.meta.url),
);
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const entries = Object.values(manifest).filter((entry) => entry.isEntry);

assert.equal(entries.length, 1, 'Preview build must contain exactly one entry.');
assert.equal(entries[0].name, 'shiftAssignment');
assert.equal(entries[0].src, 'src/modules/shift-assignment/main.jsx');

const script = await stat(`${publicRoot}${entries[0].file}`);
assert.ok(script.size <= 300_000, `Preview JavaScript exceeded 300 KB: ${script.size}`);

const cssFiles = entries[0].css ?? [];
assert.equal(cssFiles.length, 1, 'Preview build must contain one isolated CSS entry.');
const css = await stat(`${publicRoot}${cssFiles[0]}`);
assert.ok(css.size <= 50_000, `Preview CSS exceeded 50 KB: ${css.size}`);

console.log(
  `TRACS preview bundle contract passed (${script.size} B JS, ${css.size} B CSS).`,
);
