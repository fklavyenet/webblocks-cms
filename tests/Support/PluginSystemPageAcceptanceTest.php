<?php

namespace WebBlocks\Cms\Tests\Support;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\SystemPages\PrepareSystemPage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Tests\Feature\SystemPagesTest;

require_once dirname(__DIR__).'/Feature/SystemPagesTest.php';

class PluginSystemPageAcceptanceTest extends SystemPagesTest
{
    #[Test]
    public function installed_plugin_defaults_are_native_editable_translated_pages(): void
    {
        $root = getenv('WEBBLOCKS_PLUGIN_TEST_ROOT');
        if (! $root || ! is_file($root.'/webblocks-plugin.json')) {
            throw new \RuntimeException('Set WEBBLOCKS_PLUGIN_TEST_ROOT to an extracted plugin package or source root.');
        }
        $manifest = json_decode(file_get_contents($root.'/webblocks-plugin.json'), true, flags: JSON_THROW_ON_ERROR);
        $definition = app(InstalledPluginDefinitionFactory::class)->make($manifest, $root, true);
        $registry = new PluginRegistry([$manifest['handle'] => true], respectRecoveryMode: false);
        $registry->register($definition);
        app()->instance(PluginRegistry::class, $registry);
        $site = Site::firstOrFail();
        foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $code) {
            $locale = Locale::firstOrCreate(['code' => $code], ['name' => strtoupper($code), 'is_enabled' => true]);
            $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
        }
        app(RequestMemo::class)->clear();
        $this->assertNotEmpty($definition->systemPageDefinitions());
        foreach ($definition->systemPageDefinitions() as $screen) {
            $this->assertNotEmpty($screen->blocks);
            $page = app(PrepareSystemPage::class)->execute($site, $screen, auth()->user());
            $hero = $page->blocks()->where('type', 'hero')->firstOrFail();
            $text = $hero->textTranslations()->firstOrFail();
            $this->assertSame(6, $hero->textTranslations()->count());
            $this->assertSame(6, $page->translations()->count());
            foreach ($hero->textTranslations()->with('locale')->get() as $localized) {
                $this->get('/webadmin/pages/'.$page->id.'/preview?locale='.$localized->locale->code)
                    ->assertOk()->assertSee($localized->title)->assertSee($localized->content);
            }
            $this->assertNotEmpty($text->title);
            $this->assertNotEmpty($text->content);
            $this->assertStringNotContainsString('::', $text->title.$text->content);
            $preview = $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()->assertSee($text->title)->assertSee($text->content);
            $this->assertSame('draft', $page->fresh()->status);
            $originalCount = $page->blocks()->count();
            $text->update(['title' => 'Edited native screen '.$screen->key]);
            $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()->assertSee('Edited native screen '.$screen->key);
            $same = app(PrepareSystemPage::class)->execute($site, $screen, auth()->user());
            $this->assertSame($page->id, $same->id);
            $this->assertSame($originalCount, $page->blocks()->count());
            $this->assertSame('Edited native screen '.$screen->key, $text->fresh()->title);
        }
    }
}
