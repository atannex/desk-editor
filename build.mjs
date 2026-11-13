import * as esbuild from 'esbuild';
import { execFileSync, spawn } from 'node:child_process';
import { cpSync, mkdirSync, rmSync } from 'node:fs';

const watch = process.argv.includes('--watch');

mkdirSync('dist', { recursive: true });
cpSync('node_modules/katex/dist/fonts', 'dist/fonts', { recursive: true });

rmSync('dist/chunks', { recursive: true, force: true });

// ES module build with code splitting: heavy, optional features (diagrams, charts,
// collaboration, emoji data, image cropper) live in dist/chunks and load on demand.
const options = {
    entryPoints: { 'desk-editor': 'resources/js/index.js' },
    bundle: true,
    format: 'esm',
    splitting: true,
    target: ['es2020'],
    outdir: 'dist',
    chunkNames: 'chunks/[name]-[hash]',
    minify: !watch,
    sourcemap: watch ? 'inline' : false,
    legalComments: 'none',
    logLevel: 'info',
};

const tailwind = ['node_modules/@tailwindcss/cli/dist/index.mjs', '-i', 'resources/css/desk-editor.css', '-o', 'dist/desk-editor.css'];

if (watch) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
    spawn('node', [...tailwind, '--watch'], { stdio: 'inherit' });
} else {
    await esbuild.build(options);
    execFileSync('node', [...tailwind, '--minify'], { stdio: 'inherit' });
}
