<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Sites\SiteAssetWriteException;

class InternalSiteController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageLayoutSlotSyncer $slotSyncer,
    private readonly SiteAssetStore $siteAssets,
  ) {}

  public function updatePublicTheme(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('wbcms_sites', 'public_theme_preset')) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Site public theme presets are not available until the latest site schema has been applied.'],
      ]);
    }

    $preset = trim((string) $request->input('public_theme_preset', $request->input('theme')));

    if (! in_array($preset, Site::PUBLIC_THEME_PRESETS, true)) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Public theme preset must be one of: '.implode(', ', Site::PUBLIC_THEME_PRESETS).'.'],
      ]);
    }

    $site->forceFill(['public_theme_preset' => $preset])->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'writes' => [['type' => 'site_public_theme_preset', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function updateCustomHead(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('wbcms_sites', 'custom_head_html')) {
      return $this->validationError([
        ['path' => 'site.custom_head_html', 'message' => 'Custom head HTML is not available until the latest site schema has been applied.'],
      ]);
    }

    if (! $request->has('custom_head_html')) {
      return $this->validationError([
        ['path' => 'custom_head_html', 'message' => 'Provide custom_head_html (send an empty value to clear it).'],
      ]);
    }

    $validator = Validator::make($request->all(), [
      // Raw operator-authored markup for the public <head> (verification meta, SEO,
      // analytics). Nullable/empty clears it. Capped so a single site cannot store an
      // unbounded blob; TEXT holds ~64 KB.
      'custom_head_html' => ['nullable', 'string', 'max:65000'],
    ]);

    if ($validator->fails()) {
      return $this->validationError([
        ['path' => 'custom_head_html', 'message' => $validator->errors()->first('custom_head_html')],
      ]);
    }

    $value = $validator->validated()['custom_head_html'] ?? null;
    $value = is_string($value) && trim($value) === '' ? null : $value;

    $site->forceFill(['custom_head_html' => $value])->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'writes' => [['type' => 'site_custom_head_html', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function updateBranding(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('wbcms_sites', 'favicon_media_id') || ! Schema::hasColumn('wbcms_sites', 'social_image_media_id')) {
      return $this->validationError([
        ['path' => 'site.branding', 'message' => 'Site branding media fields are not available until the latest site schema has been applied.'],
      ]);
    }

    $validator = Validator::make($request->all(), [
      'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
      'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
      'favicon_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
      'social_image_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
    ]);

    if ($validator->fails()) {
      return $this->validationError(collect($validator->errors()->toArray())
        ->map(fn (array $messages, string $field) => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all());
    }

    $data = $validator->validated();

    foreach (['favicon_media_id' => 'Favicon', 'social_image_media_id' => 'Social image'] as $field => $label) {
      if (! array_key_exists($field, $data) || $data[$field] === null) {
        continue;
      }

      $media = Media::query()->find((int) $data[$field]);

      if (! $media?->isImage()) {
        return $this->validationError([
          ['path' => $field, 'message' => $label.' media must be an image from Media.'],
        ]);
      }
    }

    if ($data === []) {
      return $this->validationError([
        ['path' => 'site.branding', 'message' => 'Provide at least one site branding field.'],
      ]);
    }

    $site->forceFill($data)->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales', 'faviconMedia', 'socialImageMedia'])),
      'writes' => [['type' => 'site_branding', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function showAsset(Site $site, string $type): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'asset' => $this->apiAsset($this->siteAssets->read($site, $this->normalizeAssetType($type))),
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function updateAsset(Request $request, Site $site, string $type): JsonResponse
  {
    $type = $this->normalizeAssetType($type);

    $validator = Validator::make($request->all(), [
      'contents' => ['required', 'string', 'max:300000'],
      'expected_checksum' => ['nullable', 'string', 'size:64'],
    ]);

    if ($validator->fails()) {
      return $this->validationError(collect($validator->errors()->toArray())
        ->map(fn (array $messages, string $field) => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all());
    }

    $data = $validator->validated();

    try {
      $asset = $this->siteAssets->write(
        $site,
        $type,
        (string) $data['contents'],
        $data['expected_checksum'] ?? null
      );
    } catch (SiteAssetWriteException $exception) {
      return response()->json([
        'ok' => false,
        'asset' => [
          'type' => $type,
          'readiness' => $exception->readiness,
        ],
        'warnings' => [],
        'errors' => [
          ['path' => 'asset.write', 'message' => $exception->getMessage()],
        ],
      ], 422);
    } catch (RuntimeException $exception) {
      return $this->validationError([
        ['path' => 'expected_checksum', 'message' => $exception->getMessage()],
      ]);
    }

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'asset' => $this->apiAsset($asset),
      'writes' => [['type' => 'site_asset_'.$type, 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function syncPageLayoutSlots(Page $page): JsonResponse
  {
    $before = $page->slots()->count();
    $added = $this->slotSyncer->seedInitialSlots($page, $page->publicShellPreset());
    $page = $page->fresh(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot']);

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($page),
      'added_count' => $added,
      'before_count' => $before,
      'after_count' => $page->slots->count(),
      'writes' => $added > 0 ? [['type' => 'page_layout_slots_sync', 'id' => $page->id]] : [],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function validationError(array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'warnings' => [],
      'errors' => $errors,
    ], 422);
  }

  private function normalizeAssetType(string $type): string
  {
    if (in_array($type, SiteAssetStore::TYPES, true)) {
      return $type;
    }

    throw new HttpResponseException($this->validationError([
      ['path' => 'type', 'message' => 'Site asset type must be one of: '.implode(', ', SiteAssetStore::TYPES).'.'],
    ]));
  }

  private function apiAsset(array $asset): array
  {
    unset($asset['absolute_path']);

    $asset['guidance'] = $asset['type'] === SiteAssetStore::TYPE_CSS
      ? [
        'mode_aware_css' => 'Site CSS should be token-first and mode-aware. Prefer WebBlocks UI/CMS public theme custom properties and inherited wb-* component styles over hard-coded light or dark colors.',
        'mode_aware_contract' => [
          'Keep page, surface, text, muted text, border, and accent roles connected to --wb-public-* tokens.',
          'When brand colors are required, map them through semantic site variables and provide dark-mode values under html[data-mode="dark"] body[data-wb-public-theme].',
          'Use Kontakt-style native WebBlocks UI blocks such as wb-card, wb-input, wb-textarea, and wb-btn as the reference behavior for mode-aware custom CSS.',
        ],
        'avoid' => [
          'Do not hard-code page-wide light backgrounds, dark text, white cards, or one-off dark-mode override palettes unless the design truly cannot use existing public theme tokens.',
          'Do not solve block content or media fields with CSS when native CMS block settings or Media Library relationships exist.',
        ],
        'preferred' => [
          'Use native block structure and settings first.',
          'Use site.css for narrow layout refinements, token mapping, and site-specific composition.',
          'When custom colors are unavoidable, define semantic custom properties with both light and dark values tied to the active mode selectors or public theme tokens.',
        ],
      ]
      : [
        'site_js' => 'Site JS should enhance existing CMS-rendered markup and must not replace native block behavior or duplicate WebBlocks UI mode controls.',
      ];

    if ($asset['type'] === SiteAssetStore::TYPE_CSS) {
      $asset['analysis'] = [
        'mode_awareness' => $this->analyzeCssModeAwareness((string) ($asset['contents'] ?? '')),
      ];
    }

    return $asset;
  }

  private function analyzeCssModeAwareness(string $contents): array
  {
    $css = trim($contents);
    $recommendedTokens = [
      '--wb-public-page-bg',
      '--wb-public-surface',
      '--wb-public-surface-muted',
      '--wb-public-text',
      '--wb-public-muted',
      '--wb-public-border',
      '--wb-public-accent',
    ];

    if ($css === '') {
      return [
        'status' => 'pass',
        'warnings' => [],
        'anti_patterns' => [],
        'signals' => [
          'literal_color_declarations' => 0,
          'uses_public_theme_tokens' => false,
          'has_dark_mode_scope' => false,
        ],
        'recommended_tokens' => $recommendedTokens,
      ];
    }

    preg_match_all('/(?<![\w-])(?:background(?:-color)?|color)\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/i', $css, $literalColorMatches);

    $warnings = [];
    $antiPatterns = [];
    $literalColorCount = count($literalColorMatches[0] ?? []);
    $usesPublicTokens = str_contains($css, '--wb-public-');
    $hasDarkModeScope = preg_match('/html\s*\[\s*data-mode\s*=\s*["\']dark["\']\s*\]|@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/i', $css) === 1;

    $pageWideChecks = [
      'body_theme_background' => [
        '/(?:body(?:\.[\w-]+)?|body\s*\[\s*data-wb-public-theme[^\]]*\])[^{}]*\{[^{}]*background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/is',
        'Body/public-theme selectors set literal background colors; map page background through --wb-public-page-bg or a semantic site variable.',
      ],
      'body_theme_color' => [
        '/(?:body(?:\.[\w-]+)?|body\s*\[\s*data-wb-public-theme[^\]]*\])[^{}]*\{[^{}]*(?<!-)color\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/is',
        'Body/public-theme selectors set literal text colors; map page text through --wb-public-text or a semantic site variable.',
      ],
      'main_background' => [
        '/#main-content[^{}]*\{[^{}]*background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/is',
        '#main-content sets a literal background; use --wb-public-page-bg or a semantic site page background variable.',
      ],
      'section_background' => [
        '/\.wb-section[^{}]*\{[^{}]*background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/is',
        '.wb-section sets a literal background; native public sections should inherit public theme surface/page tokens unless the override is paired with dark-mode values.',
      ],
      'card_background' => [
        '/\.wb-card[^{}]*\{[^{}]*background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/is',
        '.wb-card sets a literal background; card-like native blocks should usually keep WebBlocks UI surface tokens.',
      ],
    ];

    foreach ($pageWideChecks as $key => [$pattern, $message]) {
      if (preg_match($pattern, $css) === 1) {
        $antiPatterns[] = $key;
        $warnings[] = $message;
      }
    }

    if ($literalColorCount > 0 && ! $usesPublicTokens) {
      $warnings[] = 'CSS uses literal color declarations but no --wb-public-* tokens; this often freezes Light/Dark/Auto mode behavior.';
    }

    if ($literalColorCount > 0 && ! $hasDarkModeScope) {
      $warnings[] = 'CSS contains literal colors without an explicit dark-mode scope such as html[data-mode="dark"] body[data-wb-public-theme].';
    }

    return [
      'status' => $warnings === [] ? 'pass' : 'warning',
      'warnings' => array_values(array_unique($warnings)),
      'anti_patterns' => array_values(array_unique($antiPatterns)),
      'signals' => [
        'literal_color_declarations' => $literalColorCount,
        'uses_public_theme_tokens' => $usesPublicTokens,
        'has_dark_mode_scope' => $hasDarkModeScope,
      ],
      'recommended_tokens' => $recommendedTokens,
    ];
  }
}
