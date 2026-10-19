<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use WebBlocks\Cms\Tests\TestCase;

class RichTextEditorViewTest extends TestCase
{
  public function test_editor_controls_and_shared_dialogs_render_for_multiple_fields(): void
  {
    $html = Blade::render(<<<'BLADE'
<!doctype html><html lang="en" data-wb-theme="light"><body>
<button id="open-host" type="button">Open block</button>
<div class="wb-modal" id="host-modal" role="dialog" aria-modal="true" aria-labelledby="host-title" hidden><div class="wb-modal-dialog"><div class="wb-modal-header"><h2 id="host-title">Block editor</h2></div><div class="wb-modal-body">
<form id="editor-form">
@include('webblocks-cms::admin.blocks.types.partials.rich-text-editor', ['value' => '<p>Original <strong>copy</strong>.</p>'])
<button type="submit">Save block</button>
</form></div></div></div>
<form id="other-form">
@include('webblocks-cms::admin.blocks.types.partials.rich-text-editor', ['inputName' => 'other_content', 'inputId' => 'other_content', 'value' => '<p>Independent editor</p>'])
</form>
@stack('overlays')
</body></html>
BLADE);

    $this->assertSame(2, substr_count($html, 'data-wb-rich-text-editor '));
    $this->assertSame(1, substr_count($html, 'data-wb-rich-text-focus-modal hidden'));
    $this->assertSame(1, substr_count($html, 'data-wb-rich-text-link-modal'));
    $this->assertStringContainsString('data-word-count-plural=":count words"', $html);
    $this->assertStringContainsString('data-wb-rich-text-action="undo"', $html);
    $this->assertStringContainsString('data-wb-rich-text-action="redo"', $html);
    $this->assertStringContainsString('data-placeholder="Write body copy…"', $html);

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    foreach (['content' => ['editor-form', '<p>Original <strong>copy</strong>.</p>'], 'other_content' => ['other-form', '<p>Independent editor</p>']] as $id => [$form, $value]) {
      $input = $xpath->query('//textarea[@id="'.$id.'"]')->item(0);
      $this->assertSame($id, $input->getAttribute('name'));
      $this->assertSame($value, $input->textContent);
      $this->assertTrue($input->hasAttribute('hidden'));
      $this->assertSame($form, $xpath->query('ancestor::form', $input)->item(0)->getAttribute('id'));
      $root = $input->parentNode;
      $surface = $xpath->query('.//*[@data-wb-rich-text-surface]', $root)->item(0);
      $this->assertSame($id.'__surface', $surface->getAttribute('id'));
      $this->assertSame('true', $surface->getAttribute('contenteditable'));
      $this->assertSame('textbox', $surface->getAttribute('role'));
      $this->assertSame('true', $surface->getAttribute('aria-multiline'));
      $actions = [];
      foreach ($xpath->query('.//button[@data-wb-rich-text-action]', $root) as $button) {
        $this->assertSame('button', $button->getAttribute('type'));
        $actions[] = $button->getAttribute('data-wb-rich-text-action');
        if (in_array($button->getAttribute('data-wb-rich-text-action'), ['undo', 'redo'], true)) {
          $this->assertTrue($button->hasAttribute('disabled'));
        }
      }
      $this->assertEqualsCanonicalizing(['undo', 'redo', 'bold', 'italic', 'strikethrough', 'code', 'link', 'bullet-list', 'numbered-list', 'outdent', 'indent', 'quote', 'clear', 'focus'], $actions);
    }
    foreach (['link', 'focus'] as $name) {
      $dialog = $xpath->query('//*[@data-wb-rich-text-'.$name.'-modal]')->item(0);
      $this->assertSame(0, $xpath->query('ancestor::form', $dialog)->length);
      $this->assertTrue($dialog->hasAttribute('hidden'));
      $this->assertSame('dialog', $dialog->getAttribute('role'));
      foreach ($xpath->query('.//button', $dialog) as $button) {
        $this->assertSame('button', $button->getAttribute('type'));
      }
    }

    // Export the actual Blade fixture for the optional PHP browser router.
    if ($fixturePath = getenv('WEBBLOCKS_RICH_TEXT_FIXTURE')) {
      file_put_contents($fixturePath, $html);
    }
  }
}
