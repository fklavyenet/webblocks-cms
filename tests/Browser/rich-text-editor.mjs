// Optional headless regression suite; no frontend build or Node dependency is
// added to the package. Run with an available Playwright module:
// PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs node tests/Browser/rich-text-editor.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'wb-rich-text-test-'));
const fixture = path.join(temp, 'fixture.html');
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE ? pathToFileURL(process.env.PLAYWRIGHT_MODULE).href : 'playwright');
let browser;
try {
  execFileSync(process.env.PHP_BIN || 'php', ['vendor/bin/phpunit', 'tests/Feature/RichTextEditorViewTest.php'], {
    cwd: root, env: { ...process.env, WEBBLOCKS_RICH_TEXT_FIXTURE: fixture }, stdio: 'pipe',
  });
  const html = fs.readFileSync(fixture, 'utf8');
  const version = fs.readFileSync(path.join(root, 'src/Support/WebBlocks.php'), 'utf8').match(/UI_VERSION = '([^']+)'/)[1];
  browser = await chromium.launch({ headless: true, ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}) });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const surface = '[data-wb-rich-text-surface]';
  const editor = '[data-wb-rich-text-editor]';
  const value = () => page.locator('#content').inputValue();
  const button = action => page.locator(`#host-modal ${editor} [data-wb-rich-text-action="${action}"]`);
  async function setup() {
    await page.goto('about:blank');
    await page.setContent(html);
    for (const asset of [`public/cms/webblocks-ui/${version}/webblocks-ui.css`, 'public/cms/css/admin.css']) {
      await page.addStyleTag({ content: fs.readFileSync(path.join(root, asset), 'utf8') });
    }
    for (const asset of [`public/cms/webblocks-ui/${version}/webblocks-ui.js`, 'public/cms/js/admin/rich-text-editor.js']) {
      await page.addScriptTag({ content: fs.readFileSync(path.join(root, asset), 'utf8') });
    }
    await page.evaluate(() => window.WBModal.open(document.querySelector('#host-modal'), document.querySelector('#open-host')));
    await page.locator(`#host-modal ${surface}`).focus();
  }
  async function select(text, end = false) {
    await page.locator(`#host-modal ${surface}`).evaluate((el, { text, end }) => {
      const range = document.createRange();
      if (text) {
        const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode()) && !node.textContent.includes(text)) {}
        if (!node) throw new Error(`Missing text: ${text}`);
        const offset = node.textContent.indexOf(text);
        range.setStart(node, offset); range.setEnd(node, offset + text.length);
      } else {
        range.selectNodeContents(el);
        if (end) range.collapse(false);
      }
      const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
      el.focus();
      el.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
    }, { text, end });
  }
  async function paste(markup, plain = '') {
    await page.locator(`#host-modal ${surface}`).evaluate((el, { markup, plain }) => {
      const data = new DataTransfer();
      data.setData('text/html', markup); data.setData('text/plain', plain);
      el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
    }, { markup, plain });
  }
  const original = '<p>Original <strong>copy</strong>.</p>';
  await setup();
  await select(null, true);
  await paste('<section><h2>Heading</h2><p>First <em>paragraph</em></p><ul><li>List<ul><li>Nested</li></ul></li></ul><table><tr><td>Cell one</td><td>Cell two</td></tr></table><p>Last</p><script>unsafe()</script><img src="x" onerror="unsafe()"></section>');
  const pasted = await value();
  for (const text of ['Heading', 'First', 'paragraph', 'Nested', 'Cell one', 'Cell two', 'Last']) assert.ok(pasted.includes(text), text);
  assert.ok(!/<(?:h2|table|script|img)|onerror|unsafe\(/.test(pasted));
  await page.keyboard.press('Control+z'); assert.equal(await value(), original);
  await page.keyboard.press('Control+Shift+z'); assert.equal(await value(), pasted);
  await page.locator('#host-title').click();
  await button('undo').click(); assert.equal(await value(), original);
  await button('redo').click(); assert.equal(await value(), pasted);
  console.log('PASS: multi-paragraph paste, safe text preservation, undo/redo and blur');

  await setup(); await select('copy');
  await button('code').click(); const code = await value(); assert.ok(code.includes('<code>'));
  await button('undo').click(); assert.equal(await value(), original);
  assert.equal(await page.evaluate(() => window.getSelection().toString()), 'copy');
  await button('redo').click(); assert.equal(await value(), code);
  await button('code').click(); assert.ok(!(await value()).includes('<code>'));
  await button('undo').click(); assert.equal(await value(), code);
  console.log('PASS: direct DOM formatting uses editor history');

  await setup(); await select('copy'); await button('link').click();
  await page.locator('[data-wb-rich-text-link-url]').fill('https://example.test/path');
  await page.locator('[data-wb-rich-text-link-apply]').click();
  const linked = await value(); assert.ok(linked.includes('href="https://example.test/path"'), linked);
  assert.ok(linked.includes('<strong>') && linked.includes('copy'), linked);
  assert.equal((linked.match(/copy/g) || []).length, 1, linked);
  await page.keyboard.press('Control+z'); assert.equal(await value(), original);
  await page.keyboard.press('Control+y'); assert.equal(await value(), linked);
  await select('copy'); await button('link').click();
  await page.locator('[data-wb-rich-text-link-url]').fill('https://example.test/changed');
  await page.locator('[data-wb-rich-text-link-apply]').click();
  assert.ok((await value()).includes('/changed'));
  await page.keyboard.press('Control+z'); assert.equal(await value(), linked);
  await select('copy'); await button('link').click(); await page.locator('[data-wb-rich-text-link-remove]').click();
  assert.equal(await value(), original);
  await page.keyboard.press('Control+z'); assert.equal(await value(), linked);
  console.log('PASS: link creation, editing and removal preserve formatting and history');

  await setup(); await select(null, true); await page.keyboard.type(' abc');
  await page.keyboard.press('Control+z'); assert.equal(await value(), original);
  await page.keyboard.press('Control+Shift+z'); assert.ok((await value()).includes(' abc'));
  await page.keyboard.press('Control+z'); await page.keyboard.type(' replacement');
  assert.equal(await button('redo').isDisabled(), true);
  await page.locator(`#host-modal ${surface}`).evaluate(el => el.dispatchEvent(new InputEvent('beforeinput', { inputType: 'historyUndo', bubbles: true, cancelable: true })));
  assert.equal(await value(), original);
  console.log('PASS: typing groups, redo branching and native history events');

  await setup(); await select(null, true);
  await page.locator(`#host-modal ${surface}`).evaluate(el => {
    el.dispatchEvent(new CompositionEvent('compositionstart', { bubbles: true }));
    for (const text of ['に', '日本語']) {
      el.innerHTML = `<p>Original <strong>copy</strong>.${text}</p>`;
      el.dispatchEvent(new InputEvent('input', { inputType: 'insertCompositionText', isComposing: true, bubbles: true }));
    }
    el.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true, data: '日本語' }));
  });
  await page.locator(`#host-modal ${surface}`).focus();
  await page.keyboard.press('Control+z'); assert.equal(await value(), original);
  await page.keyboard.press('Control+y'); assert.ok((await value()).includes('日本語'));
  console.log('PASS: composition is one history transaction');

  await setup(); await select(null); await paste('<p>One</p><p>Two</p>');
  assert.equal(await page.locator('#host-modal [data-wb-rich-text-word-count]').textContent(), '2 words');
  await button('focus').click();
  const focus = page.locator('[data-wb-rich-text-focus-modal]');
  await focus.waitFor({ state: 'visible' });
  const bounds = await focus.locator(surface).boundingBox(); assert.ok(bounds.width > 700 && bounds.height > 350);
  assert.equal(await page.locator('#content').evaluate(el => el.form.id), 'editor-form');
  await focus.locator(surface).evaluate(el => { const r = document.createRange(); r.selectNodeContents(el); r.collapse(false); const s = window.getSelection(); s.removeAllRanges(); s.addRange(r); el.focus(); });
  await page.keyboard.type(' three');
  const focusedValue = await value();
  assert.equal(await page.evaluate(() => new FormData(document.querySelector('#editor-form')).get('content')), focusedValue);
  await focus.locator('[data-wb-rich-text-action="link"]').click();
  await page.locator('[data-wb-rich-text-link-url]').fill('https://example.test/focused');
  await page.locator('[data-wb-rich-text-link-apply]').click();
  assert.ok((await value()).includes('/focused'));
  await page.keyboard.press('Escape');
  await focus.waitFor({ state: 'hidden' });
  assert.ok(await page.locator('#host-modal').isVisible());
  assert.equal(await page.locator('#content').evaluate(el => el.form.id), 'editor-form');
  await button('undo').click(); assert.equal(await value(), focusedValue);
  await page.locator('#other-form [data-wb-rich-text-surface]').focus(); await page.keyboard.type(' Changed'); await page.keyboard.press('Control+z');
  assert.equal(await page.locator('#other_content').inputValue(), '<p>Independent editor</p>');
  assert.equal(await value(), focusedValue);
  console.log('PASS: focus mode, nested link modal, form ownership, word count and independent fields');

  await page.setViewportSize({ width: 390, height: 844 }); await button('focus').click();
  const mobile = await focus.locator(surface).boundingBox(); assert.ok(mobile.width <= 390 && mobile.height > 200);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth); assert.equal(overflow, false);
  await page.keyboard.press('Escape'); await focus.waitFor({ state: 'hidden' });
  assert.deepEqual(errors, []);
  console.log('PASS: mobile layout and no browser errors');
} finally {
  if (browser) await browser.close();
  fs.rmSync(temp, { recursive: true, force: true });
}
