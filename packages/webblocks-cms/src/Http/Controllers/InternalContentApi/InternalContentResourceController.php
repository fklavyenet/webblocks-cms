<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageDeleter;

class InternalContentResourceController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageDeleter $pageDeleter,
  ) {}

  public function sites(): JsonResponse
  {
    $sites = Site::query()
      ->with(['locales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
      ->primaryFirst()
      ->orderBy('name')
      ->get()
      ->map(fn (Site $site) => $this->presenter->site($site))
      ->values();

    return $this->ok(['sites' => $sites]);
  }

  public function locales(): JsonResponse
  {
    $locales = Locale::query()
      ->orderByDesc('is_default')
      ->orderBy('name')
      ->get()
      ->map(fn (Locale $locale) => $this->presenter->locale($locale))
      ->values();

    return $this->ok(['locales' => $locales]);
  }

  public function pageLayouts(): JsonResponse
  {
    $layouts = PageLayout::query()
      ->with('layoutSlots.slotType')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (PageLayout $layout) => $this->presenter->pageLayout($layout))
      ->values();

    return $this->ok(['page_layouts' => $layouts]);
  }

  public function blockTypes(): JsonResponse
  {
    $blockTypes = BlockType::query()
      ->where('status', 'published')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (BlockType $blockType) => $this->presenter->blockType($blockType))
      ->values();

    return $this->ok(['block_types' => $blockTypes]);
  }

  public function contentContract(): JsonResponse
  {
    $blockContracts = BlockType::query()
      ->where('status', 'published')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (BlockType $blockType) => $this->safeBlockContract($blockType))
      ->values();

    return $this->ok([
      'api' => [
        'prefix' => '/webadmin/api',
        'content_validate' => '/webadmin/api/content/validate',
        'content_apply' => '/webadmin/api/content/apply',
        'page_publish' => '/webadmin/api/pages/{page}/publish',
        'page_owned_blocks_publish' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
        'preview_url_template' => '/webadmin/pages/{page}/preview',
        'modes' => [
          'create_draft_page',
          'replace_existing_draft_page',
          'create_staged_update_for_published_page',
          'replace_staged_page_update',
          'promote_staged_page_update',
        ],
      ],
      'safety' => [
        'draft_only' => false,
        'apply_requires_explicit_user_approval' => true,
        'publishes' => false,
        'page_publish_default_includes_blocks' => false,
        'overwrites_existing_content' => false,
        'draft_slot_replacement' => true,
        'published_page_staged_updates' => true,
        'remote_fetch' => false,
        'media_import' => false,
      ],
      'draft_slot_replacement' => [
        'mode' => 'replace_existing_draft_page',
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'requires_capability' => 'content.apply',
        'requires_page_status' => Page::STATUS_DRAFT,
        'requires_safety_guard' => 'expected_path or expected_updated_at',
        'shared_slot_backed_slots' => 'rejected',
        'publishes' => false,
        'example' => [
          'plan' => [
            'mode' => 'replace_existing_draft_page',
            'site' => 'default',
            'locale' => 'en',
            'page' => [
              'id' => 123,
              'expected_path' => '/contact',
              'status' => 'draft',
            ],
            'replace_slots' => [
              'main' => [
                [
                  'type' => 'plain_text',
                  'translations' => [
                    'content' => 'Replacement draft content.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'publishing' => [
        'publish_page_url_template' => '/webadmin/api/pages/{page}/publish',
        'publish_page_owned_blocks_url_template' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
        'requires_capability' => 'content.publish',
        'default_include_page_owned_blocks' => false,
        'include_page_owned_blocks_field' => 'include_page_owned_blocks',
        'shared_slot_cascade' => 'unsupported',
        'shared_slot_content' => 'excluded and must be reviewed separately',
      ],
      'published_page_staged_updates' => [
        'create_mode' => 'create_staged_update_for_published_page',
        'replace_mode' => 'replace_staged_page_update',
        'promote_mode' => 'promote_staged_page_update',
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'create_requires_capability' => 'content.apply',
        'replace_requires_capability' => 'content.apply',
        'promote_requires_capability' => 'content.apply + content.publish',
        'source_page_status' => Page::STATUS_PUBLISHED,
        'staged_page_status' => Page::STATUS_DRAFT,
        'source_public_route' => 'preserved until explicit promote',
        'staged_public_route' => 'not public because staged page remains draft',
        'preview_url_template' => '/webadmin/pages/{page}/preview',
        'requires_safety_guard' => 'expected_source_path or expected_source_updated_at',
        'shared_slot_backed_slots' => 'rejected for replace/promote',
        'promote_blocks_status' => 'promoted page-owned blocks are written as published',
        'shared_slot_cascade' => 'unsupported',
        'storage' => 'draft page with settings.staged_update metadata',
        'example' => [
          'create' => [
            'plan' => [
              'mode' => 'create_staged_update_for_published_page',
              'site' => 'default',
              'locale' => 'en',
              'page' => [
                'id' => 123,
              ],
              'expected_source_path' => '/docs',
              'managed_slots' => ['main'],
            ],
          ],
          'replace' => [
            'plan' => [
              'mode' => 'replace_staged_page_update',
              'staged_page_id' => 456,
              'expected_source_page_id' => 123,
              'expected_source_path' => '/docs',
              'replace_slots' => [
                'main' => [
                  [
                    'type' => 'plain_text',
                    'translations' => [
                      'content' => 'Replacement staged content.',
                    ],
                  ],
                ],
              ],
            ],
          ],
          'promote' => [
            'plan' => [
              'mode' => 'promote_staged_page_update',
              'staged_page_id' => 456,
              'expected_source_page_id' => 123,
              'expected_source_path' => '/docs',
              'promote_slots' => ['main'],
            ],
          ],
        ],
      ],
      'discovery' => [
        'sites' => '/webadmin/api/sites',
        'locales' => '/webadmin/api/locales',
        'page_layouts' => '/webadmin/api/page-layouts',
        'block_types' => '/webadmin/api/block-types',
        'navigation_menus' => '/webadmin/api/navigation-menus',
        'shared_slots' => '/webadmin/api/shared-slots',
        'page_publish' => '/webadmin/api/pages/{page}/publish',
        'page_owned_blocks_publish' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
      ],
      'recommended_patterns' => [
        'marketing_homepage' => [
          'section -> container -> hero',
          'section -> container -> grid -> card -> card_body',
          'section -> container -> cta',
        ],
        'avoid' => [
          'single rich-text blob for a full page',
          'trusted html fallback when structured blocks can represent the content',
          'full-width hero/cta without a container unless intentionally edge-to-edge',
        ],
      ],
      'block_contracts' => $blockContracts,
    ]);
  }

  public function pages(Request $request): JsonResponse
  {
    $pages = Page::query()
      ->with(['site.locales', 'translations.locale', 'slots.slotType'])
      ->withCount(['slots', 'blocks'])
      ->when($request->filled('site'), function ($query) use ($request) {
        $site = (string) $request->query('site');

        $query->whereHas('site', fn ($siteQuery) => is_numeric($site)
          ? $siteQuery->whereKey((int) $site)
          : $siteQuery->where('handle', $site));
      })
      ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
      ->orderByDesc('created_at')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn (Page $page) => $this->presenter->page($page))
      ->values();

    return $this->ok(['pages' => $pages]);
  }

  public function page(Page $page): JsonResponse
  {
    $page->load([
      'site.locales',
      'translations.locale',
      'slots.slotType',
      'blocks.blockType',
      'blocks.slotType',
      'blocks.textTranslations',
      'blocks.buttonTranslations',
      'blocks.imageTranslations',
    ]);

    return $this->ok(['page' => $this->presenter->page($page, true)]);
  }

  public function deletePage(Page $page): JsonResponse
  {
    $pageId = $page->id;

    $this->pageDeleter->delete($page);

    Log::info('Internal Content API page deleted.', [
      'page_id' => $pageId,
      'type' => 'page',
    ]);

    return response()->json([
      'ok' => true,
      'deleted' => [
        'type' => 'page',
        'id' => $pageId,
      ],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function blocks(Request $request): JsonResponse
  {
    $blocks = Block::query()
      ->with(['blockType', 'slotType', 'textTranslations', 'buttonTranslations', 'imageTranslations'])
      ->when($request->filled('page'), fn ($query) => $query->where('page_id', (int) $request->query('page')))
      ->whereNull('parent_id')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->limit(min(max((int) $request->query('limit', 100), 1), 200))
      ->get()
      ->map(fn (Block $block) => $this->presenter->block($block, false))
      ->values();

    return $this->ok(['blocks' => $blocks]);
  }

  public function block(Block $block): JsonResponse
  {
    $block->load([
      'blockType',
      'slotType',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'children.blockType',
      'children.slotType',
      'children.textTranslations',
      'children.buttonTranslations',
      'children.imageTranslations',
    ]);

    return $this->ok(['block' => $this->presenter->block($block)]);
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function safeBlockContract(BlockType $blockType): array
  {
    $blockTypePayload = $this->presenter->blockType($blockType);
    $contract = $blockTypePayload['contract'] ?? [];

    $payload = [
      'handle' => $blockType->slug,
      'slug' => $blockType->slug,
      'label' => $blockType->name,
      'category' => $blockType->category,
      'status' => $blockType->status,
      'is_active' => $blockType->status === 'published',
      'source_type' => $blockType->source_type,
      'is_system' => (bool) $blockType->is_system,
      'is_container' => (bool) $blockType->is_container,
      'supports_children' => (bool) ($contract['supports_children'] ?? false),
      'allowed_child_handles' => $contract['allowed_child_type_slugs'] ?? null,
      'translatable_fields' => $contract['translatable_fields'] ?? [],
      'translation_family' => $contract['translation_family'] ?? null,
      'translation_family_fields' => $contract['translation_family_fields'] ?? [],
      'shared_settings_fields' => $contract['shared_settings_fields'] ?? [],
      'renderer_root_contract' => $contract['renderer_root_contract'] ?? null,
      'owns_public_root' => (bool) ($contract['owns_public_root_helper'] ?? false),
      'documented_contract' => (bool) ($contract['documented'] ?? false),
      'contract_status' => $contract['current_contract_status'] ?? null,
    ];

    if ($blockType->slug === 'contact_form') {
      $payload['settings_schema'] = [
        'recipient_email' => 'nullable email string; overrides the site and environment recipient fallback chain when present',
        'send_email_notification' => 'boolean; default true',
        'store_submissions' => 'boolean; always true in the native CMS contract',
      ];
      $payload['public_submit_endpoint'] = [
        'method' => 'POST',
        'path' => '/contact-messages',
        'route_name' => 'contact-messages.store',
        'csrf' => 'required for browser submissions',
      ];
      $payload['validation_rules'] = [
        'block_id' => 'required integer existing block id',
        'page_id' => 'nullable integer existing page id',
        'name' => 'required string max 255',
        'email' => 'required RFC email max 255',
        'subject' => 'nullable string max 255',
        'message' => 'required string',
        '_form_check_name' => 'renderer-generated signed metadata; not normal visitor input',
        'form_check_{token}' => 'renderer-generated anti-spam check field; not normal visitor input',
        'submitted_at' => 'required integer timestamp',
      ];
      $payload['spam_behavior'] = [
        'check_field' => 'renderer-generated form_check_{token} field signed by _form_check_name',
        'check_response' => 'generic success redirect without storing or notifying when the generated check field is filled or invalid',
        'classification' => 'stored submissions may be classified as spam from conservative commercial/link/repeat-IP signals',
      ];
      $payload['storage_behavior'] = 'Legitimate submissions are stored in contact_messages before email notification is attempted; notification status fields do not change the editorial message status.';
      $payload['notification_behavior'] = [
        'recipient_order' => ['block recipient_email', 'site contact_recipient_email', 'CONTACT_RECIPIENT_EMAIL', 'MAIL_FROM_ADDRESS'],
        'failure_detail' => 'safe redacted delivery error stored on the message',
      ];
      $payload['admin_review_behavior'] = 'Stored messages appear under /webadmin/contact-messages with editorial status, spam score/reasons, notification status, and safe failure detail.';
    }

    return $payload;
  }
}
