<?php

namespace WebBlocks\Cms\Tests\Feature;

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

    // Optional export for the headless interaction suite: use the actual Blade
    // views and translations rather than maintaining a second toolbar fixture.
    if ($fixturePath = getenv('WEBBLOCKS_RICH_TEXT_FIXTURE')) {
      file_put_contents($fixturePath, $html);
    }
  }
}
