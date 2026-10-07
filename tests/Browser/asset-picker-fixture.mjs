// Serve the real Blade fixture for testing in a new tab of the already-open
// browser. This script never launches a browser process.
// Run: node tests/Browser/asset-picker-fixture.mjs
// Verify initial scroll/requests, search and replacement in the primary overlay,
// and persisted selection/removal/addition in the inline compact gallery.
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'wb-asset-picker-test-'));
const fixture = path.join(temp, 'fixture.html');
execFileSync(process.env.PHP_BIN || 'php', ['vendor/bin/phpunit', 'tests/Feature/AssetPickerViewTest.php'], {
  cwd: root, env: { ...process.env, WEBBLOCKS_ASSET_PICKER_FIXTURE: fixture }, stdio: 'pipe',
});
const version = fs.readFileSync(path.join(root, 'src/Support/WebBlocks.php'), 'utf8').match(/UI_VERSION = '([^']+)'/)[1];
const assets = [
  `cms/webblocks-ui/${version}/webblocks-ui.css`, 'cms/css/admin.css',
  `cms/webblocks-ui/${version}/webblocks-ui.js`, 'cms/js/admin/asset-picker.js',
];
let html = fs.readFileSync(fixture, 'utf8').replaceAll('http://localhost/storage/', '/storage/');
html = html.replace('<body ', '<head><title>Media picker regression fixture</title>' + assets.slice(0, 2).map(url => `<link rel="stylesheet" href="/${url}">`).join('') + '<style>#spacer{height:1800px}#test-status{position:fixed;bottom:0;left:0;z-index:999999;background:white;color:black;padding:8px}</style></head><body ')
  .replace('</body>', assets.slice(2).map(url => `<script src="/${url}"></script>`).join('') + `<output id="test-status"></output><script>
document.querySelector('#open-host').addEventListener('click', function () {
  window.WBModal.open(document.querySelector('#host-modal'), this);
  window.WebBlocksCmsAdminAssetPicker.init(document.querySelector('#host-modal'));
});
setInterval(function () {
  document.querySelector('#test-status').textContent = 'Editor scroll: ' + document.querySelector('#host-modal .wb-modal-body').scrollTop
    + '; Materialized rows: ' + document.querySelectorAll('[data-wb-asset-card]').length
    + '; Image requests: ' + performance.getEntriesByType('resource').filter(entry => entry.initiatorType === 'img').length;
}, 100);
</script></body>`);
const server = http.createServer((request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (url.pathname === '/') {
    response.writeHead(200, { 'Content-Type': 'text/html' }); response.end(html);
  } else if (assets.includes(url.pathname.slice(1))) {
    response.writeHead(200, { 'Content-Type': url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript' });
    response.end(fs.readFileSync(path.join(root, 'public', url.pathname)));
  } else if (/^\/storage\/media\/image-\d+\.jpg$/.test(url.pathname)) {
    response.writeHead(200, { 'Content-Type': 'image/svg+xml' });
    response.end('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="lightblue"/></svg>');
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
