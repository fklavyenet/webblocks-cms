<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\Media\PublicMediaSources;
use WebBlocks\Cms\Tests\TestCase;

class PublicResponsiveMediaTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function logo_and_slider_derivatives_reduce_bytes_preserve_ratio_and_alpha_and_reuse_the_cache(): void
  {
    $media = $this->image('synthetic.png', 1881, 1737);
    $service = app(MediaTransformService::class);
    foreach (['logo', 'content-small', 'content'] as $variant) {
      $result = $service->result($media, $variant);
      $this->assertTrue($result->generated);
      $this->assertEqualsWithDelta($media->width / $media->height, $result->width / $result->height, 0.01);
      $bytes = Storage::disk('public')->get($this->diskPath($result->url));
      $this->assertLessThan($media->size, strlen($bytes));
      $decoded = imagecreatefromstring($bytes);
      $this->assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
      $this->assertSame($result->width, imagesx($decoded));
      $this->assertSame($result->height, imagesy($decoded));
      imagedestroy($decoded);
      $this->assertSame('reused', $service->result($media, $variant)->status);
    }
    $logo = $service->result($media, 'logo');
    $this->assertLessThanOrEqual(128, $logo->height);
    $this->assertLessThanOrEqual(1024, $logo->width);
    $wide = $this->image('wide-wordmark.png', 4000, 800, fakeDisk: false);
    $wordmark = $service->result($wide, 'logo');
    $this->assertSame([640, 128], [$wordmark->width, $wordmark->height]);
    if (function_exists('imagewebp')) {
      $webp = $service->result($media, 'logo-webp');
      $bytes = Storage::disk('public')->get($this->diskPath($webp->url));
      $this->assertSame('image/webp', getimagesizefromstring($bytes)['mime']);
      $decoded = imagecreatefromstring($bytes);
      $this->assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
      imagedestroy($decoded);
    }
    $this->assertSame($media->size, Storage::disk('public')->size($media->path));
  }

  #[Test]
  public function unsupported_private_missing_and_failed_formats_keep_honest_fallbacks(): void
  {
    Storage::fake('public');
    $media = new Media(['disk' => 'public', 'path' => 'media/vector.svg', 'extension' => 'svg', 'kind' => 'image', 'visibility' => 'public', 'width' => 1800, 'height' => 900]);
    $sources = app(PublicMediaSources::class);
    $this->assertSame(['url' => $media->url(), 'srcset' => null, 'webp' => null], $sources->forImage($media));
    $media->visibility = 'private';
    $this->assertNull($sources->forImage($media)['url']);
    $media->visibility = 'public';
    $media->extension = 'png';
    $media->path = 'media/missing.png';
    $this->assertSame(['url' => $media->url(), 'srcset' => null, 'webp' => null], $sources->forImage($media));
    Storage::disk('public')->put($media->path, 'invalid image');
    $this->assertSame(['url' => $media->url(), 'srcset' => null, 'webp' => null], $sources->forImage($media));
    $media->width = null;
    $this->assertSame(['url' => $media->url(), 'srcset' => null, 'webp' => null], $sources->forImage($media, logo: true));
    $this->assertNull($sources->forImage(null)['url']);
  }

  #[Test]
  public function public_page_prioritizes_only_its_leading_slide_and_keeps_mobile_and_original_viewer_sources(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    $desktop = $this->image('desktop.png', 2400, 1200);
    $mobile = $this->image('mobile.png', 900, 1200, fakeDisk: false);
    $site = Site::create(['name' => 'Media studio', 'handle' => 'media-studio', 'domain' => 'localhost', 'is_primary' => true]);
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Media homepage', 'slug' => 'home', 'status' => 'published']);
    $slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id]);
    $base = ['page_id' => $page->id, 'slot_type_id' => $slot->id, 'status' => 'published'];
    for ($i = 0; $i < 2; $i++) {
      $slider = Block::create([...$base, 'type' => 'slider', 'sort_order' => $i]);
      foreach ([0, 1] as $order) {
        $slide = Block::create([...$base, 'type' => 'slide', 'media_id' => $desktop->id, 'parent_id' => $slider->id, 'sort_order' => $order, 'settings' => ['background_position' => 'top']]);
        BlockMedia::create(['block_id' => $slide->id, 'media_id' => $mobile->id, 'role' => 'mobile_image', 'position' => 0]);
      }
    }
    $html = $this->get('/')->assertOk()->getContent();
    $xpath = $this->xpath($html);
    $this->assertSame(1, $xpath->query('//img[@fetchpriority="high" and @loading="eager"]')->length);
    $this->assertSame(3, $xpath->query('//img[@fetchpriority="low" and @loading="eager"]')->length);
    $this->assertSame(4, $xpath->query('//img[contains(@class,"wb-slide-media") and @srcset and @sizes="100vw" and @width="2400" and @height="1200"]')->length);
    $this->assertSame(4, $xpath->query('//picture//source[@media="(max-width: 768px)" and not(@type) and @width="900" and @height="1200"]')->length);
    $this->assertStringContainsString('object-position: top;', $html);
    $this->assertSame(2, $xpath->query('//section[@data-wb-slider-autoplay="false"]')->length);
    if (function_exists('imagewebp')) {
      $this->assertSame(8, $xpath->query('//picture//source[@type="image/webp"]')->length);
    }
    // A content block before the carousel removes the speculative LCP hint.
    $intro = Block::create([...$base, 'type' => 'plain_text', 'content' => 'Introductory content', 'sort_order' => -1]);
    $this->assertSame(0, $this->xpath($this->get('/')->assertOk()->getContent())->query('//img[@fetchpriority="high"]')->length);
    $intro->delete();
    $container = Block::create([...$base, 'type' => 'container', 'sort_order' => -1]);
    Block::where('type', 'slider')->update(['parent_id' => $container->id]);
    $this->assertSame(1, $this->xpath($this->get('/')->assertOk()->getContent())->query('//img[@fetchpriority="high"]')->length);
    foreach (['navbar-brand', 'sidebar-brand'] as $type) {
      $brand = new Block(['type' => $type]);
      $brand->setRelation('asset', $desktop);
      $brand->setRelation('renderPage', $page);
      $logo = view('webblocks-cms::pages.partials.blocks.'.$type, ['block' => $brand])->render();
      $this->assertStringContainsString('/logo.png', $logo);
      $this->assertStringNotContainsString($desktop->url(), $logo);
    }
    $image = new Block(['type' => 'image', 'settings' => ['viewer_enabled' => true]]);
    $image->setRelation('media', $desktop);
    $html = view('webblocks-cms::pages.partials.blocks.image', ['block' => $image])->render();
    $this->assertStringContainsString('href="'.$desktop->url().'"', $html);
  }

  private function image(string $name, int $width, int $height, bool $fakeDisk = true): Media
  {
    if (! function_exists('imagecreatetruecolor')) {
      $this->markTestSkipped('GD is optional.');
    }
    if ($fakeDisk) {
      Storage::fake('public');
    }
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    for ($y = 64; $y < $height; $y += 7) {
      imageline($image, 64, $y, $width - 1, $y, imagecolorallocate($image, $y % 256, ($y * 7) % 256, ($y * 11) % 256));
    }
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    Storage::disk('public')->put('media/'.$name, $bytes);

    return Media::create(['disk' => 'public', 'path' => 'media/'.$name, 'filename' => $name, 'extension' => 'png', 'mime_type' => 'image/png', 'kind' => 'image', 'visibility' => 'public', 'width' => $width, 'height' => $height, 'size' => strlen($bytes)]);
  }

  private function diskPath(string $url): string
  {
    return substr($url, strlen(Storage::disk('public')->url('')));
  }

  private function xpath(string $html): DOMXPath
  {
    $document = new DOMDocument;
    @$document->loadHTML($html);

    return new DOMXPath($document);
  }
}
