<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Tests\TestCase;

class AssetPickerViewTest extends TestCase
{
  public function test_picker_results_remain_inert_until_the_picker_opens(): void
  {
    $assets = collect(range(1, 250))->map(function (int $id): Media {
      $asset = new Media([
        'disk' => 'public', 'path' => 'media/image-'.$id.'.jpg',
        'filename' => 'image-'.$id.'.jpg', 'title' => 'Image '.$id,
        'kind' => Media::KIND_IMAGE, 'visibility' => 'public',
        'extension' => 'jpg', 'width' => 100, 'height' => 100,
      ]);
      $asset->id = $id;
      $asset->setRelation('folder', null);

      return $asset;
    });
    $html = Blade::render(<<<'BLADE'
<!doctype html><html lang="en" data-wb-theme="light"><body data-wb-i18n-selected="Selected" data-wb-i18n-select="Select">
<button id="open-host" type="button">Open block</button>
<div class="wb-modal" id="host-modal" role="dialog" aria-modal="true" aria-labelledby="host-title" hidden><div class="wb-modal-dialog"><div class="wb-modal-header"><h2 id="host-title">Block editor</h2><button type="button" class="wb-modal-close" data-wb-dismiss="modal">Close</button></div><div class="wb-modal-body">
<form id="asset-picker-form">
@include('webblocks-cms::admin.media.asset-picker-panel', ['name' => 'primary', 'inputId' => 'primary', 'panelMode' => 'overlay', 'selectedAsset' => $assets->first(), 'showUpload' => false])
<div id="spacer"></div>
@include('webblocks-cms::admin.media.asset-picker-panel', ['name' => 'mobile', 'inputId' => 'mobile', 'panelMode' => 'overlay', 'showUpload' => false])
@include('webblocks-cms::admin.media.asset-picker-panel', ['name' => 'gallery', 'inputId' => 'gallery', 'mode' => 'multiple', 'selectedAssets' => [$assets->last()], 'resultsVariant' => 'compact-list', 'showUpload' => false])
</form></div></div></div>
@stack('overlays')
</body></html>
BLADE, ['assets' => $assets, 'assetPickerAssets' => $assets]);

    $this->assertSame(3, substr_count($html, '<template data-wb-picker-assets-template>'));
    $this->assertStringContainsString('value="1"', $html);
    $this->assertStringContainsString('value="250"', $html);

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $this->assertSame(0, $xpath->query('//*[@data-wb-asset-card and not(ancestor::template)]')->length);
    $templates = $xpath->query('//template[@data-wb-picker-assets-template]');
    $this->assertSame(3, $templates->length);
    foreach ($templates as $template) {
      $this->assertSame(250, $xpath->query('.//*[@data-wb-asset-card]', $template)->length);
      $this->assertSame(250, $xpath->query('.//img[@loading="lazy" and @decoding="async"]', $template)->length);
      $first = $xpath->query('.//*[@data-wb-asset-select or @data-wb-asset-toggle]', $template)->item(0);
      $payload = json_decode($first->getAttribute('data-wb-asset'), true, 512, JSON_THROW_ON_ERROR);
      $this->assertSame(1, $payload['id']);
      $this->assertSame('Image 1', $payload['title']);
    }
    foreach (['primary' => ['primary', '1'], 'mobile' => ['mobile', ''], 'gallery' => ['gallery[]', '250']] as $name => [$field, $value]) {
      $root = $xpath->query('//*[@data-wb-asset-picker-panel and @data-wb-picker-name="'.$name.'"]')->item(0);
      $input = $xpath->query('.//input[@data-wb-picker-selected-input]', $root)->item(0);
      $this->assertSame($field, $input->getAttribute('name'));
      $this->assertSame($value, $input->getAttribute('value'));
      $this->assertSame('asset-picker-form', $xpath->query('ancestor::form', $input)->item(0)->getAttribute('id'));
      $opener = $xpath->query('.//button[@data-wb-picker-open]', $root)->item(0);
      $panelId = $opener->getAttribute('aria-controls');
      $this->assertSame(1, $xpath->query('//*[@data-wb-picker-panel and @id="'.$panelId.'"]')->length);
    }

    if ($fixturePath = getenv('WEBBLOCKS_ASSET_PICKER_FIXTURE')) {
      file_put_contents($fixturePath, $html);
    }
  }
}
