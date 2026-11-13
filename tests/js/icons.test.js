import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = join(process.cwd(), 'resources/js');
const files = (dir) => readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    return statSync(path).isDirectory() ? files(path) : path.endsWith('.js') ? [path] : [];
});

describe('icons', () => {
    it('every icon referenced in the UI exists in the icon set', () => {
        const icons = readFileSync(join(root, 'ui/icons.js'), 'utf8');
        const used = new Set();
        for (const file of files(root)) {
            const src = readFileSync(file, 'utf8');
            for (const m of src.matchAll(/(?:icon|iconButton)\(\s*['"]([A-Z][A-Za-z0-9]+)['"]/g)) used.add(m[1]);
            for (const m of src.matchAll(/\bicon:\s*['"]([A-Z][A-Za-z0-9]+)['"]/g)) used.add(m[1]);
        }
        const missing = [...used].filter((name) => !icons.includes(`  ${name}:`));
        expect(missing).toEqual([]);
        expect(used.size).toBeGreaterThan(60);
    });
});
