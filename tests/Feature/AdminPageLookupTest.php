<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Queries\SiteDomainQuery;
use WebBlocks\Cms\Support\Sites\SiteResolver;
use WebBlocks\Cms\Tests\TestCase;

class AdminPageLookupTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function default_locale_and_domain_reads_are_reused_and_models_are_independent(): void
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar', 'domain' => 'northstar.example.test']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'English title', 'slug' => 'english', 'status' => 'draft']);
    $page->load('translations');
    $this->assertSame($en->id, Page::defaultLocaleId());
    $domain = $site->primaryDomain();
    $domain->domain = 'unsaved.example.test';
    $this->assertSame([], $this->queries(function () use ($page, $site): void {
      for ($i = 0; $i < 20; $i++) {
        $this->assertSame('English title', $page->defaultTranslation()->name);
        $this->assertSame('northstar.example.test', $site->primaryDomain()->domain);
      }
    }));
    $site->load('siteDomains');
    $site->siteDomains->first()->domain = 'local-edit.example.test';
    $this->assertSame('local-edit.example.test', $site->primaryDomain()->domain);
    $this->assertSame('local-edit.example.test', $site->domain);
    app()->instance('request', Request::create('/next'));
    $this->assertNotEmpty($this->queries(fn () => $site->fresh()->primaryDomain()));
  }

  #[Test]
  public function locale_and_domain_writes_and_rollback_invalidate_positive_and_negative_results(): void
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $query = app(SiteDomainQuery::class);
    $this->assertNull($query->primary($site));
    $this->assertSame([], $this->queries(fn () => $this->assertNull($query->primary($site))));
    $domain = SiteDomain::create(['site_id' => $site->id, 'domain' => 'new.example.test', 'status' => 'active', 'is_primary' => true]);
    $this->assertSame('new.example.test', $query->primary($site)->domain);
    DB::beginTransaction();
    DB::table('wbcms_locales')->update(['is_default' => false]);
    DB::table('wbcms_locales')->where('id', $tr->id)->update(['is_default' => true]);
    DB::table('wbcms_site_domains')->where('id', $domain->id)->update(['domain' => 'changed.example.test']);
    $this->assertSame($tr->id, Page::defaultLocaleId());
    $this->assertSame('changed.example.test', $query->primary($site)->domain);
    DB::rollBack();
    $this->assertSame($en->id, Page::defaultLocaleId());
    $this->assertSame('new.example.test', $query->primary($site)->domain);
    DB::table('wbcms_locales')->update(['is_default' => false]);
    $this->assertNull(Page::defaultLocaleId());
    $this->assertSame([], $this->queries(fn () => $this->assertNull(Page::defaultLocaleId())));
  }

  #[Test]
  public function active_alias_and_legacy_domain_fallbacks_preserve_the_existing_selection_rules(): void
  {
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar', 'domain' => 'primary.example.test']);
    $site->primaryDomain()->update(['status' => 'inactive']);
    $alias = SiteDomain::create(['site_id' => $site->id, 'domain' => 'alias.example.test', 'status' => 'active', 'is_primary' => false]);
    $resolver = app(SiteResolver::class);
    $this->assertSame($alias->id, $resolver->primaryDomainFor($site)->id);
    $this->assertSame([], $this->queries(fn () => $this->assertSame($alias->id, $resolver->primaryDomainFor($site)->id)));
    // A constrained/unsaved relation must retain its existing primary override.
    $site->setRelation('siteDomains', collect());
    $this->assertSame('primary.example.test', $site->domain);
    $this->assertSame($alias->id, $resolver->primaryDomainFor($site)->id);
    $alias->update(['status' => 'inactive']);
    $this->assertNull($resolver->primaryDomainFor($site));
    $this->assertSame('primary.example.test', $site->domain);
  }

  private function queries(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
  }
}
