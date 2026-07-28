<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockMedia as BlockAsset;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PageType;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Media\LegacyAssetPayloadNormalizer;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Sites\SiteDomainManager;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteHandle;
use ZipArchive;

class ImportDataMapper
{
  public function __construct(
    private readonly DatabaseManager $db,
    private readonly SiteDomainNormalizer $domainNormalizer,
    private readonly SiteDomainManager $siteDomainManager,
    private readonly SiteTransferPathGuard $pathGuard,
    private readonly BlockTranslationWriter $blockTranslationWriter,
    private readonly PageAssetPathValidator $pageAssetPathValidator,
    private readonly SharedSlotSourcePageManager $sharedSlotSourcePageManager,
    private readonly LegacyAssetPayloadNormalizer $legacyAssetPayloadNormalizer,
    private readonly CoreBlockTypeCatalogSyncer $coreBlockTypeCatalogSyncer,
    private readonly CoreLayoutCatalogSyncer $coreLayoutCatalogSyncer,
  ) {}

  /**
   * Import the whole package, running steps until it finishes.
   *
   * Kept as the entry point every caller already uses — the CLI command, the
   * API — so they inherit resumability without knowing about steps. Callers
   * that want to show progress drive step() themselves instead.
   */
  public function import(SiteImport $siteImport, SiteImportOptions $options, ZipArchive $archive, array $payload, array &$output = []): Site
  {
    do {
      // No HTTP request to keep alive here, so take a long budget and let the
      // loop run; the per-slice commits still make it resumable if it is killed.
      $result = $this->step($siteImport, $options, $archive, $payload, 30.0);
      $output = array_merge($output, $result->log);

      if ($result->isFailed()) {
        throw new RuntimeException((string) $result->failureMessage);
      }
    } while (! $result->isFinished());

    return Site::query()->findOrFail($siteImport->fresh()->target_site_id);
  }

  /**
   * Run one bounded step and commit whatever it completed.
   *
   * The budget bounds how long the step keeps starting new slices; it cannot
   * interrupt one, which is why the slice sizes in SiteImportPlan matter. Every
   * slice commits on its own, so a step that dies loses at most the slice that
   * was in flight and the next call picks up from the stored cursor.
   *
   * @param  array<string, mixed>  $payload
   */
  public function step(
    SiteImport $siteImport,
    SiteImportOptions $options,
    ZipArchive $archive,
    array $payload,
    float $budgetSeconds = 5.0,
  ): SiteImportStepResult {
    $payload = $this->legacyAssetPayloadNormalizer->normalizePayload($payload);
    $state = $this->loadState($siteImport);
    $log = [];

    if ($state['phase'] === null) {
      return SiteImportStepResult::fromImport($siteImport);
    }

    $deadline = microtime(true) + max(0.5, $budgetSeconds);

    try {
      // Indexing stays deferred for the content phases; the search_index phase
      // calls rebuild(), which is never deferred, and produces the same index
      // for a fraction of the work.
      PublicSearchIndexer::deferring(function () use (&$state, &$log, $siteImport, $options, $archive, $payload, $deadline): void {
        do {
          $sliceLog = [];
          $this->runSlice($state, $options, $archive, $payload, $sliceLog);
          $this->saveState($siteImport, $state, $payload, $sliceLog);
          $log = array_merge($log, $sliceLog);
        } while ($state['phase'] !== null && microtime(true) < $deadline);
      });
    } catch (Throwable $throwable) {
      // The cursor is left where it was so the failed phase is what a resume
      // retries. Files copied so far are deliberately kept: they are the work
      // a resume would otherwise repeat, and removing them belongs to the
      // explicit teardown, not to a step that may well be retried.
      $failure = 'Import failed during '.(string) $state['phase'].': '.$throwable->getMessage();
      $this->saveState($siteImport, $state, $payload, [$failure], SiteImport::STATUS_FAILED, $throwable->getMessage());
      $log[] = $failure;
    }

    return SiteImportStepResult::fromImport($siteImport->fresh(), $log);
  }

  /**
   * @return array<string, mixed>
   */
  private function loadState(SiteImport $siteImport): array
  {
    $stored = $siteImport->resume_state ?? [];
    $resuming = SiteImportPlan::isKnown($siteImport->resume_phase);

    $phase = match (true) {
      $resuming => $siteImport->resume_phase,
      $siteImport->isCompleted() => null,
      default => SiteImportPlan::first(),
    };

    $maps = $stored['maps'] ?? [];

    return [
      'phase' => $phase,
      'offset' => $resuming ? (int) $siteImport->resume_offset : 0,
      'site_id' => $stored['site_id'] ?? null,
      'site_handle' => $stored['site_handle'] ?? null,
      'site_domain' => $stored['site_domain'] ?? null,
      'copied_files' => $stored['copied_files'] ?? [],
      'maps' => [
        'locale' => $maps['locale'] ?? [],
        'folder' => $maps['folder'] ?? [],
        'asset' => $maps['asset'] ?? [],
        'page' => $maps['page'] ?? [],
        'shared_slot_handle' => $maps['shared_slot_handle'] ?? [],
        'shared_slot_source_page' => $maps['shared_slot_source_page'] ?? [],
        'block' => $maps['block'] ?? [],
        'block_media' => $maps['block_media'] ?? [],
      ],
    ];
  }

  /**
   * @param  array<string, mixed>  $state
   * @param  array<string, mixed>  $payload
   * @param  list<string>  $log
   */
  private function saveState(
    SiteImport $siteImport,
    array $state,
    array $payload,
    array $log,
    ?string $status = null,
    ?string $failureMessage = null,
  ): void {
    $finished = $state['phase'] === null;
    $total = SiteImportPlan::total($payload);
    $existing = array_filter(explode(PHP_EOL, (string) $siteImport->output_log));

    $siteImport->forceFill([
      'status' => $status ?? ($finished ? SiteImport::STATUS_COMPLETED : SiteImport::STATUS_PARTIAL),
      'resume_phase' => $state['phase'],
      'resume_offset' => $state['offset'],
      'resume_state' => [
        'site_id' => $state['site_id'],
        'site_handle' => $state['site_handle'],
        'site_domain' => $state['site_domain'],
        'copied_files' => $state['copied_files'],
        'maps' => $state['maps'],
      ],
      'progress_total' => $total,
      'progress_done' => $finished
        ? $total
        : SiteImportPlan::completedUnits((string) $state['phase'], (int) $state['offset'], $payload),
      'heartbeat_at' => now(),
      'target_site_id' => $state['site_id'],
      'imported_site_handle' => $state['site_handle'],
      'imported_site_domain' => $state['site_domain'],
      'output_log' => implode(PHP_EOL, array_merge($existing, $log)),
      'failure_message' => $failureMessage,
    ])->save();
  }

  /**
   * @param  array<string, mixed>  $state
   * @param  array<string, mixed>  $payload
   * @param  list<string>  $log
   */
  private function runSlice(array &$state, SiteImportOptions $options, ZipArchive $archive, array $payload, array &$log): void
  {
    $phase = (string) $state['phase'];

    $run = function () use (&$state, $phase, $options, $archive, $payload, &$log): void {
      $this->runPhaseSlice($state, $phase, $options, $archive, $payload, $log);
    };

    if (SiteImportPlan::needsTransaction($phase)) {
      $this->db->transaction($run);

      return;
    }

    $run();
  }

  /**
   * @param  array<string, mixed>  $state
   * @param  array<string, mixed>  $payload
   * @param  list<string>  $log
   */
  private function runPhaseSlice(array &$state, string $phase, SiteImportOptions $options, ZipArchive $archive, array $payload, array &$log): void
  {
    $listKey = SiteImportPlan::listKey($phase);

    if ($listKey === null) {
      $this->runUnitPhase($state, $phase, $options, $archive, $payload, $log);
      $this->advance($state);

      return;
    }

    $rows = $payload[$listKey] ?? [];
    $offset = (int) $state['offset'];
    $slice = array_slice($rows, $offset, SiteImportPlan::chunkSize($phase));

    if ($slice === []) {
      $this->advance($state);

      return;
    }

    $this->runListPhase($state, $phase, $listKey, $slice, $archive, $payload, $log);
    $state['offset'] = $offset + count($slice);

    if ($state['offset'] >= count($rows)) {
      $this->advance($state);
    }
  }

  /**
   * @param  array<string, mixed>  $state
   */
  private function advance(array &$state): void
  {
    $state['phase'] = SiteImportPlan::next((string) $state['phase']);
    $state['offset'] = 0;
  }

  /**
   * @param  array<string, mixed>  $state
   * @param  array<string, mixed>  $payload
   * @param  list<string>  $log
   */
  private function runUnitPhase(array &$state, string $phase, SiteImportOptions $options, ZipArchive $archive, array $payload, array &$log): void
  {
    switch ($phase) {
      case 'catalogs':
        $this->ensureCatalogsForPayload($payload, $log);
        break;
      case 'locales':
        $state['maps']['locale'] = $this->importLocales($payload, $log);
        break;
      case 'site':
        $site = $this->createSite($payload['site'], $options, $log);
        $state['site_id'] = $site->id;
        $state['site_handle'] = $site->handle;
        break;
      case 'site_locales':
        $this->syncSiteLocales($this->stateSite($state), $payload, $state['maps']['locale'], $log);
        break;
      case 'site_variables':
        $this->importSiteVariables($this->stateSite($state), $payload, $log);
        break;
      case 'asset_folders':
        $state['maps']['folder'] = $this->importAssetFolders($payload, $log);
        break;
      case 'site_branding':
        $this->importSiteBranding($this->stateSite($state), $payload['site'] ?? [], $state['maps']['asset'], $log);
        break;
      case 'site_public_assets':
        $this->importSitePublicAssets($this->stateSite($state), $archive, $payload, $state['copied_files'], $log);
        break;
      case 'shared_slots':
        $result = $this->importSharedSlots($this->stateSite($state), $payload, $log);
        // The models themselves cannot cross a step boundary; the handle map
        // carries their ids and the assignments phase reloads them.
        $state['maps']['shared_slot_handle'] = $result['handle_map'];
        $state['maps']['shared_slot_source_page'] = $result['source_page_map'];
        break;
      case 'shared_slot_assignments':
        $ids = array_values($state['maps']['shared_slot_handle']);
        $this->rebuildSharedSlotAssignments(
          $ids === [] ? [] : SharedSlot::query()->whereKey($ids)->get()->all(),
          $log
        );
        break;
      case 'navigation':
        $this->importNavigation($this->stateSite($state), $payload, $state['maps']['page'], $log);
        break;
      case 'search_index':
        $indexed = app(PublicSearchIndexer::class)->rebuild($this->stateSite($state));
        $log[] = 'Rebuilt the public search index ('.$indexed->indexed.' entries).';
        break;
      case 'domains':
        $site = $this->stateSite($state);
        $this->importSiteDomains($site, $payload, $options, $log);
        $state['site_domain'] = $site->fresh()?->domain;
        break;
      default:
        throw new RuntimeException('Unknown site import phase: '.$phase.'.');
    }
  }

  /**
   * @param  array<string, mixed>  $state
   * @param  list<array<string, mixed>>  $slice
   * @param  array<string, mixed>  $payload
   * @param  list<string>  $log
   */
  private function runListPhase(array &$state, string $phase, string $listKey, array $slice, ZipArchive $archive, array $payload, array &$log): void
  {
    switch ($phase) {
      case 'assets':
        $state['maps']['asset'] += $this->importAssets($archive, ['media' => $slice], $state['maps']['folder'], $state['copied_files'], $log);
        break;
      case 'pages':
        // The translation loop skips rows whose page is not in the map it was
        // given, so every slice sees the whole translation list and creates
        // exactly the translations belonging to the pages it just made.
        $state['maps']['page'] += $this->importPages(
          $this->stateSite($state),
          ['pages' => $slice, 'page_translations' => $payload['page_translations'] ?? []],
          $state['maps']['locale'],
          $state['maps']['asset'],
          $log
        );
        break;
      case 'page_assets':
        $this->importPageAssets($archive, ['page_assets' => $slice], $state['maps']['page'], $state['copied_files'], $log);
        break;
      case 'page_slots':
        $this->importPageSlots(
          ['page_slots' => $slice],
          $this->allPageMap($state),
          $state['maps']['shared_slot_handle'],
          array_keys($state['maps']['shared_slot_source_page']),
          $log
        );
        break;
      case 'blocks':
        $state['maps']['block'] += $this->importBlocks(['blocks' => $slice], $this->allPageMap($state), $state['maps']['asset'], $log);
        break;
      case 'block_parents':
        $this->wireBlockParents(['blocks' => $slice], $state['maps']['block']);
        break;
      case 'block_text_translations':
      case 'block_button_translations':
      case 'block_image_translations':
      case 'block_contact_form_translations':
        // Each of the four lists is its own phase; the method reads whichever
        // keys are present and no-ops on the rest.
        $this->importBlockTranslations([$listKey => $slice], $state['maps']['block'], $state['maps']['locale'], $log);
        break;
      case 'block_translation_storage':
        $this->normalizeBlockTranslationStorage(array_values(array_intersect_key(
          $state['maps']['block'],
          array_flip(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $slice))
        )));
        break;
      case 'block_assets':
        $state['maps']['block_media'] += $this->importBlockAssets(['block_media' => $slice], $state['maps']['block'], $state['maps']['asset'], $log);
        break;
      case 'gallery_translations':
        $this->importBlockGalleryItemTranslations(['block_gallery_item_translations' => $slice], $state['maps']['block_media'], $state['maps']['locale'], $log);
        break;
      default:
        throw new RuntimeException('Unknown site import phase: '.$phase.'.');
    }
  }

  /**
   * Pages plus the synthetic pages behind shared slots, which blocks and slots
   * both reference through the same id space.
   *
   * @param  array<string, mixed>  $state
   * @return array<int, int>
   */
  private function allPageMap(array $state): array
  {
    return array_replace($state['maps']['page'], $state['maps']['shared_slot_source_page']);
  }

  /**
   * @param  array<string, mixed>  $state
   */
  private function stateSite(array $state): Site
  {
    if (! $state['site_id']) {
      throw new RuntimeException('Site import reached a content phase before the site was created.');
    }

    return Site::query()->findOrFail($state['site_id']);
  }

  private function importLocales(array $payload, array &$output): array
  {
    $map = [];

    foreach ($payload['locales'] as $localeData) {
      $code = Locale::normalizeCode((string) ($localeData['code'] ?? ''));

      if (! $code) {
        throw new RuntimeException('Import package contains a locale without a valid code.');
      }

      $locale = Locale::query()->where('code', $code)->first();

      if (! $locale) {
        $locale = Locale::query()->create([
          'code' => $code,
          'name' => (string) ($localeData['name'] ?? Str::upper($code)),
          'is_default' => false,
          'is_enabled' => true,
        ]);

        $output[] = 'Created missing locale ['.$code.'].';
      }

      $map[(int) $localeData['id']] = $locale->id;
    }

    return $map;
  }

  private function ensureCatalogsForPayload(array $payload, array &$output): void
  {
    [$missingBlockTypes, $missingSlotTypes] = $this->missingCatalogIdentifiers($payload);

    if ($missingBlockTypes === [] && $missingSlotTypes === []) {
      return;
    }

    $this->coreLayoutCatalogSyncer->sync();
    $this->coreBlockTypeCatalogSyncer->sync();

    [$missingBlockTypes, $missingSlotTypes] = $this->missingCatalogIdentifiers($payload);

    if ($missingBlockTypes !== [] || $missingSlotTypes !== []) {
      throw new RuntimeException($this->missingCatalogMessage($missingBlockTypes, $missingSlotTypes));
    }

    $output[] = 'Synchronized core block and slot catalogs before import.';
  }

  private function missingCatalogIdentifiers(array $payload): array
  {
    $blockTypeSlugs = $this->requiredBlockTypeSlugs($payload);
    $slotTypeSlugs = $this->requiredSlotTypeSlugs($payload);

    $existingBlockTypeSlugs = $blockTypeSlugs === []
      ? []
      : BlockType::query()->whereIn('slug', $blockTypeSlugs)->pluck('slug')->all();
    $existingSlotTypeSlugs = $slotTypeSlugs === []
      ? []
      : SlotType::query()->whereIn('slug', $slotTypeSlugs)->pluck('slug')->all();

    return [
      array_values(array_diff($blockTypeSlugs, $existingBlockTypeSlugs)),
      array_values(array_diff($slotTypeSlugs, $existingSlotTypeSlugs)),
    ];
  }

  private function requiredBlockTypeSlugs(array $payload): array
  {
    return $this->uniqueIdentifiers(array_map(
      fn (array $blockData) => $blockData['block_type_slug'] ?? $blockData['type'] ?? null,
      $payload['blocks'] ?? [],
    ));
  }

  private function requiredSlotTypeSlugs(array $payload): array
  {
    $pageSlotSlugs = array_map(
      fn (array $slotData) => $slotData['slot_type_slug'] ?? null,
      $payload['page_slots'] ?? [],
    );
    $blockSlotSlugs = array_map(
      fn (array $blockData) => $blockData['slot_type_slug'] ?? $blockData['slot'] ?? null,
      $payload['blocks'] ?? [],
    );

    return $this->uniqueIdentifiers(array_merge($pageSlotSlugs, $blockSlotSlugs));
  }

  private function uniqueIdentifiers(array $identifiers): array
  {
    return collect($identifiers)
      ->filter(fn ($identifier) => is_string($identifier) || is_numeric($identifier))
      ->map(fn ($identifier) => trim((string) $identifier))
      ->filter()
      ->unique()
      ->sort()
      ->values()
      ->all();
  }

  private function missingCatalogMessage(array $blockTypeSlugs, array $slotTypeSlugs): string
  {
    $details = [];

    if ($blockTypeSlugs !== []) {
      $details[] = 'Missing block types: '.implode(', ', $blockTypeSlugs).'.';
    }

    if ($slotTypeSlugs !== []) {
      $details[] = 'Missing slot types: '.implode(', ', $slotTypeSlugs).'.';
    }

    $message = 'Import package references missing block or slot catalog rows.';

    if ($details !== []) {
      $message .= ' '.implode(' ', $details);
    }

    return $message.' Restore the missing catalog rows on the target install, then try again.';
  }

  private function createSite(array $siteData, SiteImportOptions $options, array &$output): Site
  {
    $requestedHandle = SiteHandle::normalize($options->siteHandle ?: (string) ($siteData['handle'] ?? 'imported-site'));
    $handle = $this->availableHandle($requestedHandle !== '' ? $requestedHandle : 'imported-site');

    if ($handle !== $requestedHandle) {
      $output[] = 'Adjusted imported site handle to ['.$handle.'] to avoid collisions.';
    }

    $domain = $options->siteDomain !== null
      ? $this->domainNormalizer->normalize($options->siteDomain)
      : null;

    if ($domain !== null && SiteDomain::query()->where('domain', $domain)->exists()) {
      throw new RuntimeException('Selected site domain already exists locally. Choose a different domain or leave it blank.');
    }

    // The operator's answers win over the package for identity; everything
    // else is the site's own configuration and travels as it was. Media
    // references are deliberately absent here — the assets they point at do
    // not exist yet, so the branding phase rebinds them after the copy.
    return Site::query()->create([
      'name' => $options->siteName,
      'handle' => $handle,
      'domain' => $domain,
      'is_primary' => false,
      'display_name' => $siteData['display_name'] ?? null,
      'tagline' => $siteData['tagline'] ?? null,
      'seo_title' => $siteData['seo_title'] ?? null,
      'seo_description' => $siteData['seo_description'] ?? null,
      'seo_keywords' => $siteData['seo_keywords'] ?? null,
      'contact_recipient_email' => $siteData['contact_recipient_email'] ?? null,
      'timezone' => $siteData['timezone'] ?? null,
      'public_theme_preset' => $siteData['public_theme_preset'] ?? null,
      'custom_head_html' => $siteData['custom_head_html'] ?? null,
      'brand_accent' => $siteData['brand_accent'] ?? null,
      'brand_accent_secondary' => $siteData['brand_accent_secondary'] ?? null,
      'brand_surface' => $siteData['brand_surface'] ?? null,
      'brand_text' => $siteData['brand_text'] ?? null,
      'brand_font_heading' => $siteData['brand_font_heading'] ?? null,
      'brand_font_body' => $siteData['brand_font_body'] ?? null,
    ]);
  }

  /**
   * Rebind the site's media references once the media exists.
   *
   * Favicon and social image are ids in the source install; the site row is
   * written before the assets are copied, so they can only be resolved here.
   *
   * @param  array<string, mixed>  $siteData
   * @param  array<int, int>  $assetMap
   * @param  list<string>  $output
   */
  private function importSiteBranding(Site $site, array $siteData, array $assetMap, array &$output): void
  {
    $favicon = $assetMap[(int) ($siteData['favicon_media_id'] ?? 0)] ?? null;
    $social = $assetMap[(int) ($siteData['social_image_media_id'] ?? 0)] ?? null;

    if ($favicon === null && $social === null) {
      return;
    }

    $site->forceFill(array_filter([
      'favicon_media_id' => $favicon,
      'social_image_media_id' => $social,
    ], static fn ($value) => $value !== null))->save();

    $output[] = 'Rebound the site favicon and social image to the imported media.';
  }

  private function importSiteDomains(Site $site, array $payload, SiteImportOptions $options, array &$output): void
  {
    $importedDomains = collect($payload['site_domains'] ?? []);

    if ($options->siteDomain !== null) {
      $site->refresh();
      $output[] = 'Applied explicit target site domain ['.$site->domain.'] and skipped package domain claims.';

      return;
    }

    if ($importedDomains->isEmpty()) {
      $legacyDomain = $this->domainNormalizer->normalize($payload['site']['domain'] ?? null);

      if ($legacyDomain === null) {
        return;
      }

      $importedDomains = collect([[
        'domain' => $legacyDomain,
        'is_primary' => true,
        'redirect_to_primary' => false,
        'status' => SiteDomain::STATUS_ACTIVE,
      ]]);
    }

    $attached = 0;
    $skipped = 0;

    foreach ($importedDomains as $domainData) {
      $domain = $this->domainNormalizer->normalize($domainData['domain'] ?? null);

      if ($domain === null) {
        continue;
      }

      $conflict = SiteDomain::query()->where('domain', $domain)->first();

      if ($conflict && (int) $conflict->site_id !== (int) $site->id) {
        $skipped++;
        $output[] = 'Skipped imported domain ['.$domain.'] because it already exists locally.';

        continue;
      }

      $this->siteDomainManager->addDomain(
        $site,
        $domain,
        (bool) ($domainData['is_primary'] ?? false),
        (bool) ($domainData['redirect_to_primary'] ?? false),
        (string) ($domainData['status'] ?? SiteDomain::STATUS_ACTIVE),
      );

      $attached++;
    }

    if ($attached > 0) {
      $output[] = 'Imported '.$attached.' site domain record(s).';
    }

    if ($skipped > 0) {
      $output[] = 'Skipped '.$skipped.' conflicting site domain record(s).';
    }
  }

  private function syncSiteLocales(Site $site, array $payload, array $localeMap, array &$output): void
  {
    $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
    $sync = [];

    foreach ($payload['site_locales'] as $siteLocale) {
      $mappedLocaleId = $localeMap[(int) ($siteLocale['locale_id'] ?? 0)] ?? null;

      if (! $mappedLocaleId) {
        continue;
      }

      $sync[$mappedLocaleId] = ['is_enabled' => (bool) ($siteLocale['is_enabled'] ?? true)];
    }

    if ($defaultLocaleId) {
      $sync[$defaultLocaleId] = ['is_enabled' => true];
    }

    if ($sync === []) {
      throw new RuntimeException('Import package does not provide a valid site locale mapping.');
    }

    $site->locales()->sync($sync);
    $output[] = 'Imported '.count($sync).' site locale assignment(s).';
  }

  private function importSiteVariables(Site $site, array $payload, array &$output): void
  {
    $site->siteVariables()->delete();

    $rows = collect($payload['site_variables'] ?? [])
      ->map(function (array $siteVariable) use ($site): array {
        return [
          'site_id' => $site->id,
          'key' => str((string) ($siteVariable['key'] ?? ''))->trim()->snake()->replace('-', '_')->lower()->toString(),
          'label' => $siteVariable['label'] ?? null,
          'value' => $siteVariable['value'] ?? null,
          'sort_order' => max(0, (int) ($siteVariable['sort_order'] ?? 0)),
          'is_enabled' => (bool) ($siteVariable['is_enabled'] ?? true),
          'created_at' => now(),
          'updated_at' => now(),
        ];
      })
      ->filter(fn (array $siteVariable) => preg_match('/^[a-z][a-z0-9_]*$/', $siteVariable['key']) === 1)
      ->unique('key')
      ->values();

    if ($rows->isEmpty()) {
      return;
    }

    $site->siteVariables()->insert($rows->all());
    $output[] = 'Imported '.$rows->count().' site variable record(s).';
  }

  private function importAssetFolders(array $payload, array &$output): array
  {
    $folders = $payload['media_folders'] ?? [];
    $map = [];

    foreach ($folders as $folderData) {
      $folder = MediaFolder::query()->create([
        'parent_id' => null,
        'name' => $folderData['name'] ?? 'Imported Folder',
        'slug' => $folderData['slug'] ?? Str::slug((string) ($folderData['name'] ?? 'imported-folder')),
      ]);

      $map[(int) $folderData['id']] = $folder->id;
    }

    foreach ($folders as $folderData) {
      $newFolderId = $map[(int) $folderData['id']] ?? null;
      $newParentId = $map[(int) ($folderData['parent_id'] ?? 0)] ?? null;

      if ($newFolderId) {
        MediaFolder::query()->whereKey($newFolderId)->update(['parent_id' => $newParentId]);
      }
    }

    if ($map !== []) {
      $output[] = 'Imported '.count($map).' media folder(s).';
    }

    return $map;
  }

  private function importAssets(ZipArchive $archive, array $payload, array $folderMap, array &$copiedFiles, array &$output): array
  {
    $map = [];

    foreach (($payload['media'] ?? []) as $assetData) {
      $diskName = (string) ($assetData['disk'] ?? 'public');
      $sourcePath = (string) ($assetData['path'] ?? '');
      $archiveEntry = 'files/'.$diskName.'/'.$sourcePath;

      $this->pathGuard->assertSafeRelativePath($sourcePath, 'Media path');
      $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive media path');

      if ($archive->locateName($archiveEntry) === false) {
        throw new RuntimeException('Import package is missing media file '.$archiveEntry.'.');
      }

      $targetPath = $this->availableAssetPath($diskName, $sourcePath);
      $stream = $archive->getStream($archiveEntry);

      if (! is_resource($stream)) {
        throw new RuntimeException('Could not read media file '.$archiveEntry.' from import package.');
      }

      Storage::disk($diskName)->writeStream($targetPath, $stream);
      fclose($stream);
      $copiedFiles[] = [$diskName, $targetPath];

      $asset = Media::query()->create([
        'folder_id' => $folderMap[(int) ($assetData['folder_id'] ?? 0)] ?? null,
        'disk' => $diskName,
        'path' => $targetPath,
        'filename' => basename($targetPath),
        'original_name' => $assetData['original_name'] ?? basename($sourcePath),
        'extension' => $assetData['extension'] ?? pathinfo($targetPath, PATHINFO_EXTENSION),
        'mime_type' => $assetData['mime_type'] ?? null,
        'size' => $assetData['size'] ?? null,
        'kind' => $assetData['kind'] ?? Media::KIND_OTHER,
        'visibility' => $assetData['visibility'] ?? 'public',
        'title' => $assetData['title'] ?? null,
        'alt_text' => $assetData['alt_text'] ?? null,
        'caption' => $assetData['caption'] ?? null,
        'description' => $assetData['description'] ?? null,
        'width' => $assetData['width'] ?? null,
        'height' => $assetData['height'] ?? null,
        'duration' => $assetData['duration'] ?? null,
        'uploaded_by' => null,
        'created_at' => $assetData['created_at'] ?? null,
        'updated_at' => $assetData['updated_at'] ?? null,
      ]);

      $map[(int) $assetData['id']] = $asset->id;
    }

    if ($map !== []) {
      $output[] = 'Imported '.count($map).' media record(s) and file(s).';
    }

    return $map;
  }

  private function importPages(Site $site, array $payload, array $localeMap, array $assetMap, array &$output): array
  {
    $map = [];

    foreach ($payload['pages'] as $pageData) {
      $pageTypeSlug = $pageData['page_type_slug'] ?? null;
      $layoutSlug = $pageData['layout_slug'] ?? null;

      $page = Page::query()->create([
        'site_id' => $site->id,
        'title' => $pageData['title'] ?? 'Imported Page',
        'slug' => $pageData['slug'] ?? Str::slug((string) ($pageData['title'] ?? 'imported-page')),
        'page_type' => $pageData['page_type'] ?? 'default',
        'page_type_id' => $pageTypeSlug ? PageType::query()->where('slug', $pageTypeSlug)->value('id') : null,
        'layout_id' => $layoutSlug ? Layout::query()->where('slug', $layoutSlug)->value('id') : null,
        'status' => $pageData['status'] ?? 'draft',
        'settings' => Page::sanitizeSettings($pageData['settings'] ?? null, $pageData['public_shell'] ?? null),
        'created_by_user_id' => null,
        'updated_by_user_id' => null,
        'published_by_user_id' => null,
        'archived_by_user_id' => null,
        'review_requested_by_user_id' => null,
        'created_at' => $pageData['created_at'] ?? null,
        'updated_at' => $pageData['updated_at'] ?? null,
      ]);

      $page->translations()->delete();
      $map[(int) $pageData['id']] = $page->id;
    }

    foreach ($payload['page_translations'] as $translationData) {
      $pageId = $map[(int) ($translationData['page_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if (! $pageId || ! $localeId) {
        continue;
      }

      PageTranslation::query()->create([
        'page_id' => $pageId,
        'site_id' => $site->id,
        'locale_id' => $localeId,
        'name' => $translationData['name'] ?? null,
        'slug' => $translationData['slug'] ?? null,
        'path' => $translationData['path'] ?? null,
        'seo_title' => $translationData['seo_title'] ?? null,
        'seo_description' => $translationData['seo_description'] ?? null,
        'seo_keywords' => $translationData['seo_keywords'] ?? null,
        'og_title' => $translationData['og_title'] ?? null,
        'og_description' => $translationData['og_description'] ?? null,
        'og_image_media_id' => $assetMap[(int) ($translationData['og_image_media_id'] ?? 0)] ?? null,
        'created_at' => $translationData['created_at'] ?? null,
        'updated_at' => $translationData['updated_at'] ?? null,
      ]);
    }

    $output[] = 'Imported '.count($map).' page(s).';

    return $map;
  }

  private function importPageAssets(ZipArchive $archive, array $payload, array $pageMap, array &$copiedFiles, array &$output): void
  {
    $count = 0;

    foreach (($payload['page_assets'] ?? []) as $pageAssetData) {
      $pageId = $pageMap[(int) ($pageAssetData['page_id'] ?? 0)] ?? null;

      if (! $pageId) {
        continue;
      }

      $path = $this->pageAssetPathValidator->normalizeForStorage((string) ($pageAssetData['type'] ?? ''), $pageAssetData['path'] ?? '');

      if ($archive->locateName('files/public/'.$this->pageAssetPathValidator->relativePublicPath($path)) !== false) {
        $this->restorePageAssetFile($archive, $path, $copiedFiles);
      }

      PageAsset::query()->create([
        'page_id' => $pageId,
        'type' => $pageAssetData['type'],
        'path' => $path,
        'load_position' => $pageAssetData['load_position'] ?? PageAsset::defaultLoadPositionFor((string) ($pageAssetData['type'] ?? 'css')),
        'is_defer' => (bool) ($pageAssetData['is_defer'] ?? false),
        'is_async' => (bool) ($pageAssetData['is_async'] ?? false),
        'is_module' => (bool) ($pageAssetData['is_module'] ?? false),
        'is_enabled' => (bool) ($pageAssetData['is_enabled'] ?? true),
        'sort_order' => (int) ($pageAssetData['sort_order'] ?? 0),
        'created_at' => $pageAssetData['created_at'] ?? null,
        'updated_at' => $pageAssetData['updated_at'] ?? null,
      ]);

      $count++;
    }

    $output[] = 'Imported '.$count.' page asset row(s).';
  }

  private function importSitePublicAssets(Site $site, ZipArchive $archive, array $payload, array &$copiedFiles, array &$output): void
  {
    $count = 0;

    foreach (($payload['site_public_assets'] ?? []) as $sitePublicAsset) {
      $sourceRelativePath = $this->canonicalSourceSitePublicAssetPath($sitePublicAsset);

      // Skipping silently is how a site arrives without its stylesheet while
      // the import still reports success. A package that names a file the
      // importer cannot place is a broken package, and says so.
      if ($sourceRelativePath === null) {
        throw new RuntimeException(
          'Import package lists a site asset with no usable path: '
          .(string) ($sitePublicAsset['relative_path'] ?? $sitePublicAsset['path'] ?? 'unknown').'.'
        );
      }

      $targetRelativePath = $this->targetSitePublicAssetPath($site, $sitePublicAsset);
      $archiveEntry = 'files/public/'.$sourceRelativePath;

      $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive site public asset path');

      if ($archive->locateName($archiveEntry) === false) {
        throw new RuntimeException('Import package is missing site public asset file '.$archiveEntry.'.');
      }

      $stream = $archive->getStream($archiveEntry);

      if (! is_resource($stream)) {
        throw new RuntimeException('Could not read site public asset file '.$archiveEntry.' from import package.');
      }

      $targetPath = public_path($targetRelativePath);
      $targetDirectory = dirname($targetPath);

      if (! str_starts_with($targetPath, public_path('site').DIRECTORY_SEPARATOR)) {
        fclose($stream);

        throw new RuntimeException('Site public asset file path is invalid.');
      }

      if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
        fclose($stream);

        throw new RuntimeException('Could not create the site asset directory '.$targetDirectory.'. Check that the web user may write public/site.');
      }

      $contents = $this->rebaseSiteAssetReferences(
        (string) stream_get_contents($stream),
        $targetRelativePath,
        (string) ($payload['site']['handle'] ?? ''),
        $site->handle,
      );
      $written = file_put_contents($targetPath, $contents);
      fclose($stream);

      // Both of the writes above used to have their result discarded, so a
      // site could import "successfully" with none of its assets on disk and
      // nothing anywhere saying so.
      if ($written === false || $written !== strlen((string) $contents)) {
        throw new RuntimeException('Could not write the site asset file '.$targetRelativePath.'.');
      }

      $copiedFiles[] = ['public-root', $targetRelativePath];
      $count++;
    }

    if ($count > 0) {
      $output[] = 'Imported '.$count.' site public override asset file(s).';
    }
  }

  private function restorePageAssetFile(ZipArchive $archive, string $path, array &$copiedFiles): void
  {
    $relativePath = $this->pageAssetPathValidator->relativePublicPath($path);
    $archiveEntry = 'files/public/'.$relativePath;
    $stream = $archive->getStream($archiveEntry);

    if (! is_resource($stream)) {
      throw new RuntimeException('Could not read page asset file '.$archiveEntry.' from import package.');
    }

    $targetPath = public_path($relativePath);
    $targetDirectory = dirname($targetPath);

    if (! str_starts_with($targetPath, public_path('site').DIRECTORY_SEPARATOR) && $targetPath !== public_path('site')) {
      throw new RuntimeException('Page asset file path is invalid.');
    }

    if (! is_dir($targetDirectory)) {
      mkdir($targetDirectory, 0775, true);
    }

    file_put_contents($targetPath, stream_get_contents($stream));
    fclose($stream);
    $copiedFiles[] = ['public-root', $relativePath];
  }

  /**
   * The package path of a site asset, or null when it is not one.
   *
   * This used to accept exactly two filenames, which was fine while the export
   * shipped exactly two files and became a silent gate the moment it shipped a
   * font directory. Any file under `site/{handle}/` is a site asset now; the
   * guard that matters is the path-safety one, which keeps an entry from
   * escaping the directory it claims to be in.
   */
  /**
   * Point a copied stylesheet at its own site's directory.
   *
   * Site assets reference each other by absolute public path — site.css
   * declares `url('/site/default/fonts/x.woff2')` — and the imported site
   * rarely keeps the source handle. Copying the files without rewriting those
   * references gives a site whose fonts are all present on disk and all 404
   * in the browser, which is indistinguishable from not shipping them at all.
   *
   * Only text assets are touched, and only when the handle actually changed;
   * a woff2 is never rewritten.
   */
  private function rebaseSiteAssetReferences(string $contents, string $targetRelativePath, string $sourceHandle, string $targetHandle): string
  {
    if ($sourceHandle === '' || $sourceHandle === $targetHandle) {
      return $contents;
    }

    if (! preg_match('/\.(css|js)$/i', $targetRelativePath)) {
      return $contents;
    }

    return str_replace('/site/'.$sourceHandle.'/', '/site/'.$targetHandle.'/', $contents);
  }

  private function canonicalSourceSitePublicAssetPath(array $sitePublicAsset): ?string
  {
    $relativePath = ltrim((string) ($sitePublicAsset['relative_path'] ?? $sitePublicAsset['path'] ?? ''), '/');

    if (! $this->pathGuard->isSafeRelativePath($relativePath)) {
      return null;
    }

    if (! preg_match('#^site/[a-z0-9]+(?:-[a-z0-9]+)*/.+$#', $relativePath)) {
      return null;
    }

    return $relativePath;
  }

  /**
   * Where that asset belongs under the imported site's own handle.
   *
   * The sub-path is preserved exactly, so a stylesheet that references
   * `../fonts/x.woff2` still resolves after the handle changes.
   *
   * @param  array<string, mixed>  $sitePublicAsset
   */
  private function targetSitePublicAssetPath(Site $site, array $sitePublicAsset): string
  {
    $subPath = (string) ($sitePublicAsset['sub_path'] ?? '');

    if ($subPath === '') {
      $source = ltrim((string) ($sitePublicAsset['relative_path'] ?? $sitePublicAsset['path'] ?? ''), '/');
      $subPath = (string) preg_replace('#^site/[^/]+/#', '', $source);
    }

    $subPath = ltrim(str_replace('\\', '/', $subPath), '/');

    if ($subPath === '' || ! $this->pathGuard->isSafeRelativePath($subPath)) {
      throw new RuntimeException('Site asset path is invalid.');
    }

    return 'site/'.$site->handle.'/'.$subPath;
  }

  private function importSharedSlots(Site $site, array $payload, array &$output): array
  {
    $sharedSlots = [];
    $handleMap = [];
    $sourcePageMap = [];

    foreach (($payload['shared_slots'] ?? []) as $sharedSlotData) {
      $handle = SiteHandle::normalize((string) ($sharedSlotData['handle'] ?? ''));

      if ($handle === '') {
        throw new RuntimeException('Import package contains a shared slot without a valid handle.');
      }

      $sharedSlot = SharedSlot::query()->firstOrNew([
        'site_id' => $site->id,
        'handle' => $handle,
      ]);

      $sharedSlot->fill([
        'name' => $sharedSlotData['name'] ?? str($handle)->headline()->toString(),
        'slot_name' => $sharedSlotData['slot_name'] ?? null,
        'public_shell' => $sharedSlotData['public_shell'] ?? null,
        'is_active' => (bool) ($sharedSlotData['is_active'] ?? true),
        'created_by_user_id' => null,
        'updated_by_user_id' => null,
        'created_at' => $sharedSlotData['created_at'] ?? null,
        'updated_at' => $sharedSlotData['updated_at'] ?? null,
      ]);
      $sharedSlot->save();

      $sourcePage = $this->sharedSlotSourcePageManager->ensureFor($sharedSlot);
      Block::query()->where('page_id', $sourcePage->id)->delete();
      $sharedSlot->slotBlocks()->delete();

      if (array_key_exists('source_page_slug', $sharedSlotData) && is_string($sharedSlotData['source_page_slug']) && trim($sharedSlotData['source_page_slug']) !== '') {
        $sourcePage->forceFill([
          'slug' => $sharedSlotData['source_page_slug'],
          'created_at' => $sharedSlotData['created_at'] ?? $sourcePage->created_at,
          'updated_at' => $sharedSlotData['updated_at'] ?? $sourcePage->updated_at,
        ])->save();
      }

      $sharedSlots[] = $sharedSlot;
      $handleMap[$sharedSlot->handle] = $sharedSlot->id;

      if (! empty($sharedSlotData['source_page_id'])) {
        $sourcePageMap[(int) $sharedSlotData['source_page_id']] = $sourcePage->id;
      }
    }

    if ($sharedSlots !== []) {
      $output[] = 'Imported '.count($sharedSlots).' shared slot(s).';
    }

    return [
      'shared_slots' => $sharedSlots,
      'handle_map' => $handleMap,
      'source_page_map' => $sourcePageMap,
    ];
  }

  private function importPageSlots(array $payload, array $pageMap, array $sharedSlotHandleMap, array $sharedSlotSourcePageExportIds, array &$output): void
  {
    $count = 0;
    $sharedSlotSourcePageExportIds = array_map('intval', $sharedSlotSourcePageExportIds);

    foreach ($payload['page_slots'] as $slotData) {
      $sourcePageId = (int) ($slotData['page_export_id'] ?? $slotData['page_id'] ?? 0);
      $pageId = $pageMap[$sourcePageId] ?? null;
      $slotTypeSlug = $slotData['slot_type_slug'] ?? null;
      $slotTypeId = $slotTypeSlug
        ? SlotType::query()->where('slug', $slotTypeSlug)->value('id')
        : null;

      if (! $slotTypeId) {
        throw new RuntimeException($this->missingCatalogMessage([], $this->uniqueIdentifiers([$slotTypeSlug])));
      }

      if (! $pageId) {
        throw new RuntimeException('Import package references a missing page for a page slot assignment.');
      }

      $sourceType = PageSlot::normalizeRuntimeSourceType($slotData['source_type'] ?? PageSlot::SOURCE_TYPE_PAGE);
      $sharedSlotHandle = trim((string) ($slotData['shared_slot_handle'] ?? ''));
      $sharedSlotId = $sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT
        ? ($sharedSlotHandleMap[$sharedSlotHandle] ?? null)
        : null;

      if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && ! $sharedSlotId) {
        throw new RuntimeException('Import package references a missing shared slot handle for a page slot assignment.');
      }

      $attributes = [
        'page_id' => $pageId,
        'slot_type_id' => $slotTypeId,
        'source_type' => $sourceType,
        'shared_slot_id' => $sharedSlotId,
        'sort_order' => $slotData['sort_order'] ?? 0,
        'settings' => PageSlot::sanitizeSettings($slotData['settings'] ?? null),
        'created_at' => $slotData['created_at'] ?? null,
        'updated_at' => $slotData['updated_at'] ?? null,
      ];

      if (in_array($sourcePageId, $sharedSlotSourcePageExportIds, true)) {
        PageSlot::query()->updateOrCreate(
          [
            'page_id' => $pageId,
            'slot_type_id' => $slotTypeId,
          ],
          $attributes,
        );
      } else {
        PageSlot::query()->create($attributes);
      }

      $count++;
    }

    $output[] = 'Imported '.$count.' page slot assignment(s).';
  }

  private function rebuildSharedSlotAssignments(array $sharedSlots, array &$output): void
  {
    foreach ($sharedSlots as $sharedSlot) {
      $this->sharedSlotSourcePageManager->rebuildAssignments($sharedSlot);
    }

    if ($sharedSlots !== []) {
      $output[] = 'Rebuilt shared slot block assignments for '.count($sharedSlots).' shared slot(s).';
    }
  }

  private function importBlocks(array $payload, array $pageMap, array $assetMap, array &$output): array
  {
    $map = [];

    foreach ($payload['blocks'] as $blockData) {
      $pageId = $pageMap[(int) ($blockData['page_id'] ?? 0)] ?? null;
      $blockTypeSlug = $blockData['block_type_slug'] ?? $blockData['type'] ?? null;
      $slotTypeSlug = $blockData['slot_type_slug'] ?? $blockData['slot'] ?? null;
      $blockTypeId = $blockTypeSlug ? BlockType::query()->where('slug', $blockTypeSlug)->value('id') : null;
      $slotTypeId = $slotTypeSlug ? SlotType::query()->where('slug', $slotTypeSlug)->value('id') : null;

      if (! $blockTypeId || ! $slotTypeId) {
        throw new RuntimeException($this->missingCatalogMessage(
          ! $blockTypeId ? $this->uniqueIdentifiers([$blockTypeSlug]) : [],
          ! $slotTypeId ? $this->uniqueIdentifiers([$slotTypeSlug]) : [],
        ));
      }

      if (! $pageId) {
        throw new RuntimeException('Import package references a missing page for a block.');
      }

      $block = Block::query()->create([
        'page_id' => $pageId,
        'parent_id' => null,
        'type' => $blockData['type'] ?? $blockTypeSlug,
        'block_type_id' => $blockTypeId,
        'source_type' => $blockData['source_type'] ?? 'static',
        'slot' => $blockData['slot'] ?? $slotTypeSlug,
        'slot_type_id' => $slotTypeId,
        'sort_order' => $blockData['sort_order'] ?? 0,
        'title' => $blockData['title'] ?? null,
        'subtitle' => $blockData['subtitle'] ?? null,
        'content' => $blockData['content'] ?? null,
        'url' => $blockData['url'] ?? null,
        'media_id' => $assetMap[(int) ($blockData['media_id'] ?? 0)] ?? null,
        'variant' => $blockData['variant'] ?? null,
        'meta' => $blockData['meta'] ?? null,
        'settings' => $blockData['settings'] ?? null,
        'status' => $blockData['status'] ?? 'draft',
        'is_system' => (bool) ($blockData['is_system'] ?? false),
        'created_at' => $blockData['created_at'] ?? null,
        'updated_at' => $blockData['updated_at'] ?? null,
      ]);

      $map[(int) $blockData['id']] = $block->id;
    }

    $this->wireBlockParents($payload, $map);

    $output[] = 'Imported '.count($map).' block(s).';

    return $map;
  }

  /**
   * Point imported blocks at their imported parents.
   *
   * Separate from the create loop because a chunked import creates blocks a
   * slice at a time: within one slice the map only holds that slice's blocks,
   * so a child whose parent landed in an earlier or later slice would keep a
   * null parent. The step runner replays this over the whole package once the
   * full map exists. The update is idempotent, so running it per slice as well
   * costs a redundant write and changes nothing.
   *
   * @param  array<string, mixed>  $payload
   * @param  array<int, int>  $map
   */
  private function wireBlockParents(array $payload, array $map): void
  {
    foreach (($payload['blocks'] ?? []) as $blockData) {
      $newBlockId = $map[(int) ($blockData['id'] ?? 0)] ?? null;
      $newParentId = $map[(int) ($blockData['parent_id'] ?? 0)] ?? null;

      if ($newBlockId && $newParentId) {
        Block::query()->whereKey($newBlockId)->update(['parent_id' => $newParentId]);
      }
    }
  }

  private function importBlockTranslations(array $payload, array $blockMap, array $localeMap, array &$output): void
  {
    $count = 0;

    foreach (($payload['block_text_translations'] ?? []) as $translationData) {
      $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if ($blockId && $localeId) {

        BlockTextTranslation::query()->create([
          'block_id' => $blockId,
          'locale_id' => $localeId,
          'title' => $translationData['title'] ?? null,
          'eyebrow' => $translationData['eyebrow'] ?? null,
          'subtitle' => $translationData['subtitle'] ?? null,
          'content' => $translationData['content'] ?? null,
          'meta' => $translationData['meta'] ?? null,
          'created_at' => $translationData['created_at'] ?? null,
          'updated_at' => $translationData['updated_at'] ?? null,
        ]);
        $count++;
      }
    }

    foreach (($payload['block_button_translations'] ?? []) as $translationData) {
      $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if ($blockId && $localeId) {
        BlockButtonTranslation::query()->create([
          'block_id' => $blockId,
          'locale_id' => $localeId,
          'title' => $translationData['title'] ?? null,
          'created_at' => $translationData['created_at'] ?? null,
          'updated_at' => $translationData['updated_at'] ?? null,
        ]);
        $count++;
      }
    }

    foreach (($payload['block_image_translations'] ?? []) as $translationData) {
      $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if ($blockId && $localeId) {
        BlockImageTranslation::query()->create([
          'block_id' => $blockId,
          'locale_id' => $localeId,
          'caption' => $translationData['caption'] ?? null,
          'alt_text' => $translationData['alt_text'] ?? null,
          'created_at' => $translationData['created_at'] ?? null,
          'updated_at' => $translationData['updated_at'] ?? null,
        ]);
        $count++;
      }
    }

    foreach (($payload['block_contact_form_translations'] ?? []) as $translationData) {
      $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if ($blockId && $localeId) {
        BlockContactFormTranslation::query()->create([
          'block_id' => $blockId,
          'locale_id' => $localeId,
          'title' => $translationData['title'] ?? null,
          'content' => $translationData['content'] ?? null,
          'submit_label' => $translationData['submit_label'] ?? null,
          'success_message' => $translationData['success_message'] ?? null,
          'created_at' => $translationData['created_at'] ?? null,
          'updated_at' => $translationData['updated_at'] ?? null,
        ]);
        $count++;
      }
    }

    $output[] = 'Imported '.$count.' block translation row(s).';
  }

  /**
   * Give every imported block its canonical translation row.
   *
   * Split out of the translation import, and it has to stay split. The writer
   * creates a canonical row for any block that lacks one, so running it while
   * translations are still being written fills in blocks whose real rows have
   * not landed yet — and the next batch then collides with the placeholder on
   * the (block, locale) unique index. It belongs after every translation list,
   * once.
   *
   * @param  list<int>  $blockIds
   */
  private function normalizeBlockTranslationStorage(array $blockIds): void
  {
    if ($blockIds === []) {
      return;
    }

    Block::query()
      ->whereIn('id', $blockIds)
      ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
      ->orderBy('id')
      ->get()
      ->each(fn (Block $block) => $this->blockTranslationWriter->normalizeCanonicalStorage($block));
  }

  private function importBlockAssets(array $payload, array $blockMap, array $assetMap, array &$output): array
  {
    $count = 0;
    $blockMediaMap = [];

    foreach (($payload['block_media'] ?? []) as $blockAssetData) {
      $blockId = $blockMap[(int) ($blockAssetData['block_id'] ?? 0)] ?? null;
      $assetId = $assetMap[(int) ($blockAssetData['media_id'] ?? 0)] ?? null;

      if (! $blockId || ! $assetId) {
        continue;
      }

      $blockAsset = BlockAsset::query()->create([
        'block_id' => $blockId,
        'media_id' => $assetId,
        'role' => $blockAssetData['role'] ?? null,
        'position' => $blockAssetData['position'] ?? 0,
        'created_at' => $blockAssetData['created_at'] ?? null,
        'updated_at' => $blockAssetData['updated_at'] ?? null,
      ]);

      $sourceBlockMediaId = (int) ($blockAssetData['id'] ?? 0);

      if ($sourceBlockMediaId > 0) {
        $blockMediaMap[$sourceBlockMediaId] = $blockAsset->id;
      }

      $count++;
    }

    $output[] = 'Imported '.$count.' block media link(s).';

    return $blockMediaMap;
  }

  private function importBlockGalleryItemTranslations(array $payload, array $blockMediaMap, array $localeMap, array &$output): void
  {
    $count = 0;

    foreach (($payload['block_gallery_item_translations'] ?? []) as $translationData) {
      $blockMediaId = $blockMediaMap[(int) ($translationData['block_media_id'] ?? 0)] ?? null;
      $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

      if (! $blockMediaId || ! $localeId) {
        continue;
      }

      BlockGalleryItemTranslation::query()->create([
        'block_media_id' => $blockMediaId,
        'locale_id' => $localeId,
        'alt_text' => $translationData['alt_text'] ?? null,
        'caption' => $translationData['caption'] ?? null,
        'overlay_title' => $translationData['overlay_title'] ?? null,
        'overlay_text' => $translationData['overlay_text'] ?? null,
        'created_at' => $translationData['created_at'] ?? null,
        'updated_at' => $translationData['updated_at'] ?? null,
      ]);
      $count++;
    }

    $output[] = 'Imported '.$count.' gallery item translation row(s).';
  }

  private function importNavigation(Site $site, array $payload, array $pageMap, array &$output): void
  {
    $map = [];

    foreach ($payload['navigation_items'] as $itemData) {
      $item = NavigationItem::query()->create([
        'site_id' => $site->id,
        'menu_key' => $itemData['menu_key'] ?? NavigationItem::MENU_PRIMARY,
        'parent_id' => null,
        'page_id' => $pageMap[(int) ($itemData['page_id'] ?? 0)] ?? null,
        'title' => $itemData['title'] ?? null,
        'link_type' => $itemData['link_type'] ?? NavigationItem::LINK_CUSTOM_URL,
        'url' => $itemData['url'] ?? null,
        'target' => $itemData['target'] ?? null,
        'icon' => $itemData['icon'] ?? null,
        'position' => $itemData['position'] ?? 0,
        'visibility' => $itemData['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE,
        'is_system' => (bool) ($itemData['is_system'] ?? false),
        'created_at' => $itemData['created_at'] ?? null,
        'updated_at' => $itemData['updated_at'] ?? null,
      ]);

      $map[(int) $itemData['id']] = $item->id;
    }

    foreach ($payload['navigation_items'] as $itemData) {
      $itemId = $map[(int) ($itemData['id'] ?? 0)] ?? null;
      $parentId = $map[(int) ($itemData['parent_id'] ?? 0)] ?? null;

      if ($itemId && $parentId) {
        NavigationItem::query()->whereKey($itemId)->update(['parent_id' => $parentId]);
      }
    }

    $output[] = 'Imported '.count($map).' navigation item(s).';
  }

  private function availableHandle(string $requestedHandle): string
  {
    $handle = $requestedHandle;

    if (! Site::query()->where('handle', $handle)->exists()) {
      return $handle;
    }

    $handle = $requestedHandle.'-imported';

    if (! Site::query()->where('handle', $handle)->exists()) {
      return $handle;
    }

    $suffix = 2;

    while (Site::query()->where('handle', $handle.'-'.$suffix)->exists()) {
      $suffix++;
    }

    return $handle.'-'.$suffix;
  }

  private function availableAssetPath(string $diskName, string $requestedPath): string
  {
    $this->pathGuard->assertSafeRelativePath($requestedPath, 'Asset path');

    if (! Storage::disk($diskName)->exists($requestedPath)) {
      return $requestedPath;
    }

    $directory = trim(pathinfo($requestedPath, PATHINFO_DIRNAME), '.');
    $filename = pathinfo($requestedPath, PATHINFO_FILENAME);
    $extension = pathinfo($requestedPath, PATHINFO_EXTENSION);
    $candidate = ($directory !== '' ? $directory.'/' : '').$filename.'-'.Str::lower(Str::random(8)).($extension !== '' ? '.'.$extension : '');

    return $candidate;
  }
}
