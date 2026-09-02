import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath, URL } from 'node:url';

const publicRoot = fileURLToPath(new URL('../../public/assets/react-dist/', import.meta.url));
const manifestPath = fileURLToPath(
  new URL('../../public/assets/react-dist/.vite/manifest.json', import.meta.url),
);
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
const entries = Object.values(manifest).filter((entry) => entry.isEntry);
const entriesByName = Object.fromEntries(entries.map((entry) => [entry.name, entry]));

assert.deepEqual(
  Object.keys(entriesByName).sort(),
  ['clients', 'shiftAssignment'],
  'Preview build must contain the approved React entries.',
);
assert.equal(entriesByName.shiftAssignment.src, 'src/modules/shift-assignment/main.jsx');
assert.equal(entriesByName.clients.src, 'src/modules/clients/main.jsx');

for (const entry of entries) {
  const script = await stat(`${publicRoot}${entry.file}`);
  assert.ok(script.size <= 300_000, `${entry.name} JavaScript exceeded 300 KB: ${script.size}`);

  const cssFiles = entry.css ?? [];
  assert.ok(cssFiles.length >= 1, `${entry.name} preview build must contain CSS.`);
  for (const cssFile of cssFiles) {
    const css = await stat(`${publicRoot}${cssFile}`);
    assert.ok(css.size <= 50_000, `${entry.name} CSS exceeded 50 KB: ${css.size}`);
  }
}

console.log(`TRACS preview bundle contract passed (${entries.length} entries).`);
