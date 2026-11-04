<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class NativeDisclosureTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function authored_disclosures_share_ui_structure_and_preserve_form_controls(): void
  {
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $accordion = new Block(['type' => 'accordion']);
    $accordion->setRelation('children', collect([
      new Block(['type' => 'plain_text', 'title' => 'First question', 'content' => 'First answer']),
      new Block(['type' => 'plain_text', 'title' => 'Second question', 'content' => 'Second answer']),
    ]));
    $public = view('webblocks-cms::pages.partials.blocks.accordion', ['block' => $accordion])->render();
    $permissions = view('webblocks-cms::admin.access.partials.permission-list', [
      'permissions' => ['cms.pages.read' => ['group' => 'pages', 'label' => 'Read pages']],
      'selectedPermissions' => ['cms.pages.read'], 'permissionPrefix' => 'synthetic',
    ])->render();
    $builder = view('webblocks-cms::admin.blocks.partials.column-items-editor-row', [
      'index' => 0, 'columnItem' => new Block(['type' => 'column_item', 'title' => 'Synthetic item']),
    ])->render();
    $document = new DOMDocument;
    @$document->loadHTML($public.$permissions.$builder);
    $xpath = new DOMXPath($document);
    $this->assertSame(2, $xpath->query('//details[contains(@class,"wb-accordion-item")]/summary[contains(@class,"wb-accordion-trigger")]')->length);
    $this->assertSame(1, $xpath->query('//details[contains(@class,"wb-collapse-panel")]/summary[contains(@class,"wb-collapse-trigger")]')->length);
    $this->assertSame(0, $xpath->query('//summary[@aria-expanded or @role="button" or @tabindex]')->length);
    $this->assertSame(1, $xpath->query('//input[@name="permissions[]" and @value="cms.pages.read" and @checked]')->length);
    $this->assertSame(0, $xpath->query('//summary//input | //summary//button | //summary//a')->length);
    foreach ($xpath->query('//details') as $details) {
      $this->assertSame(1, $xpath->query('./summary', $details)->length);
      $this->assertSame(1, $xpath->query('./div[contains(@class,"-body")]', $details)->length);
    }
    $body = $xpath->query('//*[@data-wb-builder-item-body and contains(@class,"is-open")]')->item(0);
    $toggle = $xpath->query('//button[@data-wb-builder-item-toggle]')->item(0);
    $this->assertSame($body->getAttribute('id'), $toggle->getAttribute('aria-controls'));
    $this->assertStringStartsWith('builder-item-column-item-', $body->getAttribute('id'));
    if ($path = getenv('WEBBLOCKS_DISCLOSURE_FIXTURE')) {
      $base = WebBlocks::UI_PUBLIC_BASE;
      file_put_contents($path, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CMS disclosure regression</title><link rel="stylesheet" href="'.$base.'/webblocks-ui.css"><link rel="stylesheet" href="'.$base.'/webblocks-icons.css"><link rel="stylesheet" href="/cms/css/admin.css"><script src="'.$base.'/webblocks-ui.js" defer></script><script src="/cms/js/admin/builder-items.js" defer></script><script src="/fixture.js" defer></script></head><body data-wb-i18n-collapse-item="Collapse item" data-wb-i18n-expand-item="Expand item"><main class="wb-container wb-stack wb-gap-4"><h1>CMS disclosure regression</h1><p id="result" role="status">Checking</p><section id="public">'.$public.'</section><section id="permissions">'.$permissions.'</section><section id="builder">'.$builder.'</section><button id="open-modal" class="wb-btn" data-wb-toggle="modal" data-wb-target="#fixture-modal">Open modal</button><div class="wb-modal" id="fixture-modal" role="dialog" aria-modal="true" aria-labelledby="fixture-title" hidden><div class="wb-modal-dialog"><h2 id="fixture-title">Disclosure focus</h2><button id="modal-first" class="wb-btn">First control</button>'.$public.'<button class="wb-btn" data-wb-dismiss="modal">Close</button></div></div></main></body></html>');
    }
  }

  #[Test]
  public function all_cms_disclosure_templates_use_ui_owned_indicators_and_bodies(): void
  {
    $root = dirname(__DIR__, 2);
    foreach (['resources/views', 'src/Support/Updates/Client/Resources/views'] as $directory) {
      foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory)) as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
          continue;
        }
        $source = file_get_contents($file->getPathname());
        preg_match_all('/<details\b[^>]*>/s', $source, $details);
        foreach ($details[0] as $tag) {
          $this->assertMatchesRegularExpression('/\bwb-(?:accordion-item|collapse-panel)\b/', $tag, $file->getPathname());
        }
        $this->assertDoesNotMatchRegularExpression('/<button\b[^>]*class="[^"]*wb-accordion-trigger/s', $source, $file->getPathname());
        $this->assertStringNotContainsString('wb-accordion-icon', $source, $file->getPathname());
        $this->assertDoesNotMatchRegularExpression('/<summary\b[^>]*>(?:(?!<\/summary>)[\s\S])*wb-icon-chevron-(?:up|down)/', $source, $file->getPathname());
      }
    }
  }
}
