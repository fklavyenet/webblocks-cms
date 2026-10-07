<?php

namespace WebBlocks\Cms\Tests\Feature;

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
<form>
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

    if ($fixturePath = getenv('WEBBLOCKS_ASSET_PICKER_FIXTURE')) {
      file_put_contents($fixturePath, $html);
    }
  }
}
