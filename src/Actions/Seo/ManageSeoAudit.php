<?php

namespace WebBlocks\Cms\Actions\Seo;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;
use WebBlocks\Cms\Support\WebBlocks;

final class ManageSeoAudit
{
  public function start(Site $site): SeoAudit
  {
    return DB::transaction(function () use ($site): SeoAudit {
      Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
      $existing = SeoAudit::query()->where('site_id', $site->id)->whereIn('state', ['queued', 'running', 'paused'])->latest('id')->first();
      if ($existing && hash_equals($existing->source_version, app(SeoSourceVersion::class)->current())) {
        return $existing;
      }
      if ($existing) {
        $existing->update(['state' => 'stale', 'worker_token' => null, 'worker_expires_at' => null]);
      }
      $audit = SeoAudit::create([
        'site_id' => $site->id, 'source_version' => app(SeoSourceVersion::class)->current(),
        'cms_version' => WebBlocks::version(), 'upper_page_id' => Page::query()->where('site_id', $site->id)->max('id') ?? 0,
      ]);
      foreach ($site->enabledLocales()->pluck('code') as $locale) {
        DB::table('wbcms_seo_audit_locales')->insert(['audit_id' => $audit->id, 'locale' => $locale]);
      }

      return $audit->refresh();
    });
  }

  public function transition(SeoAudit $audit, string $action): SeoAudit
  {
    return DB::transaction(function () use ($audit, $action): SeoAudit {
      Site::query()->whereKey($audit->site_id)->lockForUpdate()->firstOrFail();
      $audit = SeoAudit::query()->lockForUpdate()->findOrFail($audit->id);
      $allowed = match ($action) {
        'pause' => ['queued', 'running'],
        'resume' => ['paused'],
        'retry' => ['partial', 'failed'],
        default => [],
      };
      abort_unless(in_array($audit->state, $allowed, true), 409);
      if ($action !== 'pause') {
        abort_if(SeoAudit::query()->where('site_id', $audit->site_id)->whereKeyNot($audit->id)->whereIn('state', ['queued', 'running', 'paused'])->exists(), 409);
      }
      if ($action !== 'pause' && ! hash_equals($audit->source_version, app(SeoSourceVersion::class)->current())) {
        return tap($audit)->update(['state' => 'stale', 'worker_token' => null, 'worker_expires_at' => null]);
      }
      if ($action === 'retry') {
        DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->whereIn('state', ['unavailable', 'partial'])->update(['state' => 'pending']);
        DB::table('wbcms_seo_audit_findings')->where('audit_id', $audit->id)->whereIn('code', ['duplicate_title', 'duplicate_description', 'no_incoming_links'])->delete();
        if ($audit->phase !== 'inventory') {
          $audit->phase = 'inspect';
          $audit->cursor = 0;
        }
        $audit->processed = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('state', '!=', 'pending')->count();
      }
      $audit->fill(['state' => $action === 'pause' ? 'paused' : 'queued', 'worker_token' => null, 'worker_expires_at' => null, 'completed_at' => null])->save();

      return $audit;
    });
  }
}
