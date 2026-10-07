// Serve the actual modal fragment for a new tab in the already-open browser.
// Run: node tests/Browser/parent-block-fixture.mjs
// No browser process is launched. Check the Block Info tab's parent options,
// tree order, localized branch context and preservation of the selected parent.
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'wb-parent-test-'));
const fixture = path.join(temp, 'fragment.html');
execFileSync(process.env.PHP_BIN || 'php', ['vendor/bin/phpunit', 'tests/Feature/SlotBlockEditorFragmentSafetyTest.php', '--filter', 'full_and_fragment_parent_options'], {
  cwd: root, env: { ...process.env, WEBBLOCKS_PARENT_FIXTURE: fixture }, stdio: 'pipe',
});
const version = fs.readFileSync(path.join(root, 'src/Support/WebBlocks.php'), 'utf8').match(/UI_VERSION = '([^']+)'/)[1];
const html = `<!doctype html><html lang="en" data-wb-theme="light"><head><meta charset="utf-8"><title>Parent block regression fixture</title>
<link rel="stylesheet" href="/cms/webblocks-ui/${version}/webblocks-ui.css"><link rel="stylesheet" href="/cms/css/admin.css"></head><body>
<button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#slot-block-editor-modal">Open block</button>
${fs.readFileSync(fixture, 'utf8')}
<script src="/cms/webblocks-ui/${version}/webblocks-ui.js"></script>
<script>document.addEventListener('submit', function (event) { event.preventDefault(); });</script>
</body></html>`;
const publicRoot = path.join(root, 'public');
const mimeTypes = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' };
const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (request.method !== 'GET') {
    response.writeHead(405); response.end(); return;
  }
  if (url.pathname === '/') {
    response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); response.end(html);
  } else if (url.pathname.startsWith('/cms/') && mimeTypes[path.extname(url.pathname)]) {
    const file = path.join(publicRoot, url.pathname);
    if (!fs.existsSync(file)) { response.writeHead(404); response.end(); return; }
    response.writeHead(200, { 'Content-Type': mimeTypes[path.extname(file)] }); response.end(fs.readFileSync(file));
  } else {
    response.writeHead(404); response.end();
  }
});
server.listen(0, '127.0.0.1', () => console.log(`Fixture: http://127.0.0.1:${server.address().port}/`));
function cleanup() {
  server.close(); fs.rmSync(temp, { recursive: true, force: true }); process.exit(0);
}
process.on('SIGINT', cleanup);
process.on('SIGTERM', cleanup);
