<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Translations\SiteLocaleValidation;
use WebBlocks\Cms\Tests\TestCase;

class SiteLocaleValidationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function fixture(): array
  {
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic locale fixture', 'handle' => 'synthetic-locale']);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    return [$site, $locale, app(SiteLocaleValidation::class)];
  }

  public function test_transactional_scope_reuses_membership_but_keeps_site_and_locale_boundaries(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    DB::transaction(function () use ($site, $locale, $validation): void {
      $validation->during(function () use ($site, $locale, $validation): void {
        DB::enableQueryLog();
        for ($i = 0; $i < 20; $i++) {
          $this->assertTrue($validation->allows($site->id, $locale->id));
        }
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertFalse($validation->allows($site->id + 100, $locale->id));
        $this->assertFalse($validation->allows($site->id, $locale->id + 100));
      });
    });
  }

  public function test_pivot_and_global_locale_raw_writes_invalidate_positive_and_negative_results(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    DB::transaction(function () use ($site, $locale, $validation): void {
      $validation->during(function () use ($site, $locale, $validation): void {
        $this->assertTrue($validation->allows($site->id, $locale->id));
        $site->locales()->updateExistingPivot($locale->id, ['is_enabled' => false]);
        $this->assertFalse($validation->allows($site->id, $locale->id));
        $site->locales()->updateExistingPivot($locale->id, ['is_enabled' => true]);
        $this->assertTrue($validation->allows($site->id, $locale->id));
        DB::statement('UPDATE WBCMS_LOCALES SET is_enabled = 0 WHERE id = ?', [$locale->id]);
        $this->assertFalse($validation->allows($site->id, $locale->id));
      });
    });
  }

  public function test_nested_rollback_exception_and_scope_exit_discard_cached_membership(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    DB::transaction(function () use ($site, $locale, $validation): void {
      $validation->during(function () use ($site, $locale, $validation): void {
        $this->assertTrue($validation->allows($site->id, $locale->id));
        try {
          DB::transaction(function () use ($site, $locale, $validation): void {
            $site->locales()->updateExistingPivot($locale->id, ['is_enabled' => false]);
            $this->assertFalse($validation->allows($site->id, $locale->id));
            throw new \RuntimeException('Synthetic rollback');
          });
        } catch (\RuntimeException) {
          $this->assertTrue($validation->allows($site->id, $locale->id));
        }
      });
    });
    try {
      DB::transaction(fn () => $validation->during(fn () => throw new \RuntimeException('Synthetic scope failure')));
    } catch (\RuntimeException) {
      $site->locales()->detach($locale->id);
      $this->assertFalse($validation->allows($site->id, $locale->id));
    }
  }

  public function test_outside_a_transaction_scope_never_keeps_a_snapshot(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    $validation->during(function () use ($site, $locale, $validation): void {
      $this->assertTrue($validation->allows($site->id, $locale->id));
      $site->locales()->detach($locale->id);
      $this->assertFalse($validation->allows($site->id, $locale->id));
    });
  }

  public function test_bulk_membership_reads_preserve_dynamic_model_scopes(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    $scopes = Site::getAllGlobalScopes();
    try {
      DB::transaction(fn () => $validation->during(function () use ($site, $locale, $validation, $scopes): void {
        $this->assertTrue($validation->allows($site->id, $locale->id));
        Site::addGlobalScope('synthetic-exclusion', fn ($query) => $query->where('id', '!=', $site->id));
        $this->assertFalse($validation->allows($site->id, $locale->id));
        Site::setAllGlobalScopes($scopes);
        Locale::addGlobalScope('synthetic-exclusion', fn ($query) => $query->where('wbcms_locales.id', '!=', $locale->id));
        $this->assertFalse($validation->allows($site->id, $locale->id));
      }));
    } finally {
      Site::setAllGlobalScopes($scopes);
    }
  }

  public function test_noncanonical_locale_values_retain_the_original_database_comparison(): void
  {
    [$site, $locale, $validation] = $this->fixture();
    $samples = [(string) $locale->id, '0'.$locale->id, $locale->id.'.0', $locale->id.'invalid', null, 1.5];
    $expected = array_map(fn ($value) => $validation->allows($site->id, $value), $samples);
    DB::transaction(fn () => $validation->during(function () use ($site, $validation, $samples, $expected): void {
      foreach ($samples as $index => $value) {
        $this->assertSame($expected[$index], $validation->allows($site->id, $value));
      }
    }));
  }
}
