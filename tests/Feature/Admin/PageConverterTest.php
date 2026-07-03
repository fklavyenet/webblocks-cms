<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PageConverter\PageConversionDraftCreator;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSerializer;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSigner;

class PageConverterTest extends TestCase
{
  use RefreshDatabase;

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(SlotTypeSeeder::class);
    $this->seed(BlockTypeSeeder::class);
    $this->seed(PageLayoutSeeder::class);
  }

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function validPayload(array $overrides = []): array
  {
    return array_merge([
      'site_id' => $this->defaultSite()->id,
      'locale_id' => $this->defaultLocale()->id,
      'page_layout' => 'default',
      'page_title' => 'Converted Static Page',
      'page_path' => 'converted-static-page',
      'conversion_profile' => 'conservative',
      'source_html' => '<main><h1>Converted Static Page</h1><p>Hello.</p></main>',
    ], $overrides);
  }

  private function fixtureHtml(string $fixture): string
  {
    $contents = file_get_contents(base_path('tests/Fixtures/PageConverter/'.$fixture));

    $this->assertNotFalse($contents);

    return $contents;
  }

  private function signedPlanFromResponse($response): array
  {
    $content = $response->getContent();

    preg_match('/name="plan_payload" value="([^"]+)"/', $content, $payloadMatch);
    preg_match('/name="plan_signature" value="([^"]+)"/', $content, $signatureMatch);

    $payload = html_entity_decode($payloadMatch[1] ?? '', ENT_QUOTES, 'UTF-8');
    $signature = html_entity_decode($signatureMatch[1] ?? '', ENT_QUOTES, 'UTF-8');

    return [
      'payload' => $payload,
      'signature' => $signature,
      'plan' => app(PageConversionPlanSerializer::class)->deserialize($payload),
    ];
  }

  private function signedPlanFieldsFromResponse($response): array
  {
    $signedPlan = $this->signedPlanFromResponse($response);

    return [
      'plan_payload' => $signedPlan['payload'],
      'plan_signature' => $signedPlan['signature'],
    ];
  }

  private function signedPlanFields(array $plan): array
  {
    $payload = base64_encode(json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return [
      'plan_payload' => $payload,
      'plan_signature' => app(PageConversionPlanSigner::class)->sign($payload),
    ];
  }

  private function conversionPlan(array $blocks, array $target = []): array
  {
    return [
      'version' => PageConversionPlanSerializer::VERSION,
      'target' => array_merge([
        'site_id' => $this->defaultSite()->id,
        'locale_id' => $this->defaultLocale()->id,
        'page_layout' => 'default',
        'page_title' => 'Converted Structured Page',
        'page_path' => 'converted-structured-page',
        'conversion_profile' => 'conservative',
      ], $target),
      'source' => [
        'type' => 'pasted',
        'name' => 'Pasted HTML',
        'bytes' => 0,
        'content_root_summary' => '<main>',
      ],
      'summary' => [
        'suggestion_count' => count($blocks),
        'fallback_count' => 0,
        'warning_count' => 0,
      ],
      'blocks' => array_map(fn (array $block, int $index): array => array_merge([
        'key' => 'block_'.($index + 1),
        'order' => $index + 1,
        'parent_key' => null,
        'block_type' => $block['block_slug'] ?? $block['block_type'] ?? '',
        'label' => str($block['block_slug'] ?? $block['block_type'] ?? 'Block')->replace(['-', '_'], ' ')->title()->toString(),
        'translated_fields' => [],
        'shared_fields' => [],
        'confidence' => 90,
        'warnings' => [],
        'fallback_flags' => [],
        'source_fragment' => [
          'summary' => 'Test fragment',
          'preview_text' => '',
          'html' => '',
        ],
      ], $block, [
        'block_type' => $block['block_slug'] ?? $block['block_type'] ?? '',
      ]), $blocks, array_keys($blocks)),
    ];
  }

  private function publishBlockType(string $slug, string $name, bool $isContainer = false): BlockType
  {
    return BlockType::query()->updateOrCreate(
      ['slug' => $slug],
      [
        'name' => $name,
        'category' => 'content',
        'description' => $name,
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => $isContainer,
        'sort_order' => 50,
        'status' => 'published',
      ],
    );
  }

  #[Test]
  public function authorized_admin_users_can_open_page_converter(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.pages.converter.index'));

    $response->assertOk();
    $response->assertSeeText('Page Converter');
    $response->assertSeeText('Convert pasted or uploaded static HTML into a draft CMS page made from structured blocks.');
  }

  #[Test]
  public function unauthorized_users_cannot_access_page_converter(): void
  {
    $this->seedFoundation();

    $user = User::factory()->editor()->create(['is_active' => false]);

    $this->actingAs($user)
      ->get(route('admin.pages.converter.index'))
      ->assertForbidden();
  }

  #[Test]
  public function page_converter_entry_appears_in_pages_area(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.pages.index', ['site' => $this->defaultSite()->id]));

    $response->assertOk();
    $response->assertSeeText('Page Converter');
    $response->assertSee('href="'.route('admin.pages.converter.index', ['site_id' => $this->defaultSite()->id]).'"', false);
  }

  #[Test]
  public function form_renders_required_target_source_and_profile_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.pages.converter.index'));

    $response->assertOk();
    $response->assertSee('name="site_id"', false);
    $response->assertSee('name="locale_id"', false);
    $response->assertSee('name="page_layout"', false);
    $response->assertSee('name="page_title"', false);
    $response->assertSee('name="page_path"', false);
    $response->assertSee('name="source_html"', false);
    $response->assertSee('name="source_file"', false);
    $response->assertSee('name="conversion_profile"', false);
    $response->assertSeeText('Conservative');
    $response->assertSeeText('Generic Docs Page');
    $response->assertSeeText('Generic Marketing Page');
    $response->assertSeeText('WebBlocks UI-flavored HTML');
    $response->assertSeeText('Analyze HTML');
  }

  #[Test]
  public function analyze_requires_pasted_html_or_uploaded_html_file(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.analyze'), $this->validPayload(['source_html' => '']));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('source_html');
  }

  #[Test]
  public function analyze_accepts_uploaded_html_file_as_source(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '',
      'source_file' => UploadedFile::fake()->createWithContent('source.htm', '<main><h1>File Source</h1></main>'),
    ]));

    $response->assertOk();
    $response->assertSeeText('Analysis Preview');
    $response->assertSeeText('source.htm');
    $response->assertSeeText('Header');
  }

  #[Test]
  public function analyze_rejects_non_html_uploads(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.analyze'), $this->validPayload([
        'source_html' => '',
        'source_file' => UploadedFile::fake()->createWithContent('source.txt', 'not html'),
      ]));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('source_file');
  }

  #[Test]
  public function analyze_returns_preview_state_and_does_not_create_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'conversion_profile' => 'generic_docs',
    ]));

    $response->assertOk();
    $response->assertSeeText('Analysis Preview');
    $response->assertSeeText('Analysis preview only');
    $response->assertSeeText('Generic Docs Page');
    $response->assertSeeText('header');
    $response->assertSeeText('plain_text');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function analysis_produces_signed_conversion_plan_payload(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><h1>Signed Plan</h1><p>Review copy.</p></main>',
    ]));

    $response->assertOk();
    $response->assertSee('name="plan_payload"', false);
    $response->assertSee('name="plan_signature"', false);

    $signedPlan = $this->signedPlanFromResponse($response);

    $this->assertTrue(app(PageConversionPlanSigner::class)->verify($signedPlan['payload'], $signedPlan['signature']));
    $this->assertSame($this->defaultSite()->id, $signedPlan['plan']['target']['site_id']);
    $this->assertSame($this->defaultLocale()->id, $signedPlan['plan']['target']['locale_id']);
    $this->assertSame('default', $signedPlan['plan']['target']['page_layout']);
    $this->assertSame('Converted Static Page', $signedPlan['plan']['target']['page_title']);
    $this->assertSame('converted-static-page', $signedPlan['plan']['target']['page_path']);
    $this->assertSame('conservative', $signedPlan['plan']['target']['conversion_profile']);
    $this->assertSame(2, $signedPlan['plan']['summary']['suggestion_count']);
    $this->assertSame('block_1', $signedPlan['plan']['blocks'][0]['key']);
    $this->assertSame(1, $signedPlan['plan']['blocks'][0]['order']);
    $this->assertSame('header', $signedPlan['plan']['blocks'][0]['block_slug']);
    $this->assertSame('Signed Plan', $signedPlan['plan']['blocks'][0]['translated_fields']['title']);
    $this->assertArrayHasKey('shared_fields', $signedPlan['plan']['blocks'][0]);
    $this->assertArrayHasKey('source_fragment', $signedPlan['plan']['blocks'][0]);
  }

  #[Test]
  public function review_ui_includes_signed_plan_payload_and_future_create_action_area(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());

    $response->assertOk();
    $response->assertSee('name="plan_payload"', false);
    $response->assertSee('name="plan_signature"', false);
    $response->assertSeeText('Signed plan blocks');
    $response->assertSeeText('Create draft page');
    $response->assertSeeText('The page will be created as draft.');
    $response->assertSeeText('No page has been created yet');
  }

  #[Test]
  public function valid_signed_plan_creates_one_draft_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $initialPageCount = Page::query()->count();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());

    $response = $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $response->assertSessionHasNoErrors();
    $page = Page::query()->with(['translations', 'slots.slotType', 'blocks.textTranslations'])->latest('id')->firstOrFail();

    $response->assertRedirect(route('admin.pages.edit', $page));
    $response->assertSessionHas('status');
    $this->assertSame($initialPageCount + 1, Page::query()->count());
    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertNull($page->published_at);
    $this->assertSame($this->defaultSite()->id, $page->site_id);
    $this->assertSame('default', $page->publicShellPreset());
    $this->assertTrue($page->translations->contains(fn ($translation) => $translation->locale_id === $this->defaultLocale()->id));
    $this->assertSame('Converted Static Page', $page->translationForLocale($this->defaultLocale())?->name);
    $this->assertSame('converted-static-page', $page->translationForLocale($this->defaultLocale())?->slug);
    $this->assertTrue($page->slots->contains(fn (PageSlot $slot) => $slot->slotType?->slug === 'main'));
    $this->assertSame(['header', 'plain_text'], $page->blocks->pluck('type')->all());
  }

  #[Test]
  public function plan_signature_validation_rejects_tampered_payload(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());
    $fields = $this->signedPlanFieldsFromResponse($analysis);
    $fields['plan_payload'] = substr($fields['plan_payload'], 0, -1).($fields['plan_payload'][-1] === 'A' ? 'B' : 'A');
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $fields);

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('plan_payload');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function inaccessible_site_in_submitted_plan_is_rejected(): void
  {
    $this->seedFoundation();

    $site = $this->defaultSite();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);
    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);
    $analysis = $this->actingAs($editor)->post(route('admin.pages.converter.analyze'), $this->validPayload());
    $editor->sites()->sync([$otherSite->id]);
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($editor)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('plan_payload');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function invalid_locale_layout_or_path_in_submitted_plan_is_rejected(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());
    $signedPlan = $this->signedPlanFromResponse($analysis);

    foreach (['locale', 'layout', 'path'] as $case) {
      $plan = $signedPlan['plan'];

      if ($case === 'locale') {
        $plan['target']['locale_id'] = 999999;
      }

      if ($case === 'layout') {
        $plan['target']['page_layout'] = 'missing-layout';
      }

      if ($case === 'path') {
        $plan['target']['page_path'] = '../unsafe';
      }

      $payload = base64_encode(json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

      $response = $this->actingAs($user)
        ->from(route('admin.pages.converter.index'))
        ->post(route('admin.pages.converter.create-draft'), [
          'plan_payload' => $payload,
          'plan_signature' => app(PageConversionPlanSigner::class)->sign($payload),
        ]);

      $response->assertRedirect(route('admin.pages.converter.index'), $case);
      $response->assertSessionHasErrors('plan_payload', null, 'default');
    }
  }

  #[Test]
  public function supported_suggestions_create_blocks_in_order_with_translated_fields(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'Structured Draft',
      'page_path' => 'structured-draft',
      'source_html' => '<main><h2>Heading</h2><p>Plain copy.</p><p>Rich <strong>copy</strong>.</p><pre><code>php artisan test</code></pre><blockquote>Quote copy</blockquote></main>',
    ]));

    $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'structured-draft'))->firstOrFail();
    $blocks = $page->blocks()->with('textTranslations')->orderBy('sort_order')->get();

    $this->assertSame(['header', 'plain_text', 'rich-text', 'code', 'quote'], $blocks->pluck('type')->all());
    $this->assertSame('Heading', $blocks[0]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->title);
    $this->assertSame('Plain copy.', $blocks[1]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
    $this->assertStringContainsString('Rich', (string) $blocks[2]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
    $this->assertSame('php artisan test', $blocks[3]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
    $this->assertSame('Quote copy', $blocks[4]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
  }

  #[Test]
  public function structured_content_header_and_hero_suggestions_create_translated_draft_blocks(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'block_slug' => 'content_header',
        'translated_fields' => [
          'title' => 'Documentation hub',
          'subtitle' => 'Find the implementation notes.',
          'meta' => ['Updated weekly', 'Owner: Docs'],
        ],
        'shared_fields' => ['alignment' => 'center'],
        'source_fragment' => ['summary' => 'Header', 'preview_text' => 'Documentation hub', 'html' => '<header>Documentation hub</header>'],
      ],
      [
        'block_slug' => 'hero',
        'translated_fields' => [
          'eyebrow' => 'Launch',
          'title' => 'Structured hero',
          'body' => 'Created from a signed plan.',
        ],
        'shared_fields' => [
          'variant' => 'accent',
          'layout' => 'centered',
          'title_tag' => 'h2',
        ],
        'source_fragment' => ['summary' => 'Hero', 'preview_text' => 'Structured hero', 'html' => '<section>Structured hero</section>'],
      ],
    ], [
      'page_title' => 'Structured Marketing',
      'page_path' => 'structured-marketing',
    ]);

    $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan))
      ->assertSessionHasNoErrors();

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'structured-marketing'))->firstOrFail();
    $blocks = $page->blocks()->with('textTranslations')->orderBy('sort_order')->get();

    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertSame(['content_header', 'hero'], $blocks->pluck('type')->all());
    $this->assertSame('Documentation hub', $blocks[0]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->title);
    $this->assertSame('Find the implementation notes.', $blocks[0]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->subtitle);
    $this->assertSame('["Updated weekly","Owner: Docs"]', $blocks[0]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->meta);
    $this->assertSame('Launch', $blocks[1]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->subtitle);
    $this->assertSame('Structured hero', $blocks[1]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->title);
    $this->assertSame('Created from a signed plan.', $blocks[1]->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
    $this->assertSame('accent', $blocks[1]->variant);
    $this->assertSame(Page::STATUS_DRAFT, $blocks[1]->status);
  }

  #[Test]
  public function section_shell_is_created_with_warning_when_plan_has_no_explicit_children(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'block_slug' => 'section',
        'translated_fields' => ['title' => 'Feature area'],
        'shared_fields' => ['spacing' => 'lg'],
      ],
    ], [
      'page_title' => 'Section Shell',
      'page_path' => 'section-shell',
    ]);

    $result = app(PageConversionDraftCreator::class)->create($plan, $user);
    $section = $result->page->blocks()->where('type', 'section')->firstOrFail();

    $this->assertSame(1, $result->createdBlockCount);
    $this->assertSame(0, $result->skippedSuggestionCount);
    $this->assertTrue(collect($result->warnings)->contains(fn (string $warning) => str_contains($warning, 'section shell without child blocks')));
    $this->assertSame('Feature area', $section->layoutAdminName());
    $this->assertSame('draft', $result->page->status);
  }

  #[Test]
  public function card_with_explicit_region_children_creates_nested_blocks_in_order(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'card',
        'block_slug' => 'card',
        'translated_fields' => ['title' => 'Composable card'],
      ],
      [
        'key' => 'card_header',
        'parent_key' => 'card',
        'block_slug' => 'card_header',
        'translated_fields' => ['title' => 'Card heading region'],
      ],
      [
        'key' => 'card_body',
        'parent_key' => 'card',
        'block_slug' => 'card_body',
        'translated_fields' => ['title' => 'Card body region'],
      ],
    ], [
      'page_title' => 'Nested Card',
      'page_path' => 'nested-card',
    ]);

    $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan))
      ->assertSessionHasNoErrors();

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'nested-card'))->firstOrFail();
    $card = $page->blocks()->where('type', 'card')->firstOrFail();
    $children = $page->blocks()->where('parent_id', $card->id)->orderBy('sort_order')->get();

    $this->assertSame(['card_header', 'card_body'], $children->pluck('type')->all());
    $this->assertSame([0, 1], $children->pluck('sort_order')->all());
    $this->assertSame('Composable card', $card->setting('layout_name'));
  }

  #[Test]
  public function card_without_explicit_region_children_is_skipped_with_warning(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'block_slug' => 'card',
        'translated_fields' => [
          'title' => 'Summary card',
          'content' => 'This should not be flattened into unsafe HTML.',
        ],
      ],
    ], [
      'page_title' => 'Skipped Card',
      'page_path' => 'skipped-card',
    ]);

    $result = app(PageConversionDraftCreator::class)->create($plan, $user);

    $this->assertSame(0, $result->createdBlockCount);
    $this->assertSame(1, $result->skippedSuggestionCount);
    $this->assertTrue(collect($result->warnings)->contains(fn (string $warning) => str_contains($warning, 'no explicit usable card child region')));
    $this->assertSame(0, $result->page->blocks()->count());
  }

  #[Test]
  public function cta_suggestion_creates_translated_block_without_importing_actions_as_settings(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'block_slug' => 'cta',
        'translated_fields' => [
          'eyebrow' => 'Ready',
          'heading' => 'Start now',
          'body' => 'Use the draft builder to finish this page.',
          'primary_cta_label' => 'Get started',
        ],
        'shared_fields' => [
          'variant' => 'soft',
          'primary_cta_url' => '/start',
        ],
      ],
    ], [
      'page_title' => 'CTA Draft',
      'page_path' => 'cta-draft',
    ]);

    $result = app(PageConversionDraftCreator::class)->create($plan, $user);
    $cta = $result->page->blocks()->with('textTranslations')->where('type', 'cta')->firstOrFail();

    $this->assertSame(1, $result->createdBlockCount);
    $this->assertSame('soft', $cta->variant);
    $this->assertNull($cta->settings);
    $this->assertSame('Ready', $cta->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->subtitle);
    $this->assertSame('Start now', $cta->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->title);
    $this->assertSame('Use the draft builder to finish this page.', $cta->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
    $this->assertTrue(collect($result->warnings)->contains(fn (string $warning) => str_contains($warning, 'without CTA child buttons')));
  }

  #[Test]
  public function accordion_plan_creates_parent_and_faq_items_when_contract_is_available(): void
  {
    $this->seedFoundation();
    $this->publishBlockType('accordion', 'Accordion', true);
    $this->publishBlockType('faq', 'FAQ');

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'accordion',
        'block_slug' => 'accordion',
        'translated_fields' => ['title' => 'Questions'],
      ],
      [
        'key' => 'item_1',
        'parent_key' => 'accordion',
        'block_slug' => 'accordion_item',
        'translated_fields' => [
          'title' => 'How does publishing work?',
          'content' => 'Editors draft and admins publish.',
        ],
      ],
      [
        'key' => 'item_2',
        'parent_key' => 'accordion',
        'block_slug' => 'accordion_item',
        'translated_fields' => [
          'title' => 'Can I localize content?',
          'content' => 'Yes, translated block text is supported.',
        ],
      ],
    ], [
      'page_title' => 'Accordion Draft',
      'page_path' => 'accordion-draft',
    ]);

    $result = app(PageConversionDraftCreator::class)->create($plan, $user);
    $accordion = $result->page->blocks()->where('type', 'accordion')->firstOrFail();
    $items = $result->page->blocks()->where('parent_id', $accordion->id)->orderBy('sort_order')->get();

    $this->assertSame(3, $result->createdBlockCount);
    $this->assertSame(0, $result->skippedSuggestionCount);
    $this->assertSame(Page::STATUS_DRAFT, $result->page->status);
    $this->assertSame('Questions', $accordion->title);
    $this->assertSame(['faq', 'faq'], $items->pluck('type')->all());
    $this->assertSame([0, 1], $items->pluck('sort_order')->all());
    $this->assertSame('How does publishing work?', $items[0]->title);
    $this->assertSame('Editors draft and admins publish.', $items[0]->content);
    $this->assertSame('Can I localize content?', $items[1]->title);
    $this->assertSame('Yes, translated block text is supported.', $items[1]->content);
  }

  #[Test]
  public function accordion_is_skipped_without_broken_parent_when_required_item_type_is_unavailable(): void
  {
    $this->seedFoundation();
    $this->publishBlockType('accordion', 'Accordion', true);

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'accordion',
        'block_slug' => 'accordion',
        'translated_fields' => ['title' => 'Questions'],
      ],
      [
        'key' => 'item_1',
        'parent_key' => 'accordion',
        'block_slug' => 'accordion_item',
        'translated_fields' => [
          'title' => 'Missing type?',
          'content' => 'This should not create a broken parent.',
        ],
      ],
    ], [
      'page_title' => 'Skipped Accordion',
      'page_path' => 'skipped-accordion',
    ]);

    $result = app(PageConversionDraftCreator::class)->create($plan, $user);

    $this->assertSame(0, $result->createdBlockCount);
    $this->assertSame(2, $result->skippedSuggestionCount);
    $this->assertTrue(collect($result->warnings)->contains(fn (string $warning) => str_contains($warning, 'no explicit usable accordion item children')));
    $this->assertSame(0, $result->page->blocks()->count());
  }

  #[Test]
  public function tampered_accordion_item_data_is_rejected_without_creating_page(): void
  {
    $this->seedFoundation();
    $this->publishBlockType('accordion', 'Accordion', true);
    $this->publishBlockType('faq', 'FAQ');

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'accordion',
        'block_slug' => 'accordion',
      ],
      [
        'key' => 'item_1',
        'parent_key' => 'accordion',
        'block_slug' => 'accordion_item',
        'translated_fields' => [
          'title' => 'Question without answer',
          'content' => '',
        ],
      ],
    ], [
      'page_title' => 'Tampered Accordion',
      'page_path' => 'tampered-accordion',
    ]);
    $initialPageCount = Page::query()->count();

    $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan))
      ->assertRedirect(route('admin.pages.converter.index'))
      ->assertSessionHasErrors('plan_payload');

    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function inactive_child_block_type_rejects_plan_without_partial_creation(): void
  {
    $this->seedFoundation();

    BlockType::query()->where('slug', 'card_body')->update(['status' => 'draft']);

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'card',
        'block_slug' => 'card',
        'translated_fields' => ['title' => 'Composable card'],
      ],
      [
        'key' => 'card_body',
        'parent_key' => 'card',
        'block_slug' => 'card_body',
      ],
    ], [
      'page_title' => 'Inactive Child',
      'page_path' => 'inactive-child',
    ]);
    $initialPageCount = Page::query()->count();
    $initialBlockCount = Block::query()->count();

    $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan))
      ->assertRedirect(route('admin.pages.converter.index'))
      ->assertSessionHasErrors('plan_payload');

    $this->assertSame($initialPageCount, Page::query()->count());
    $this->assertSame($initialBlockCount, Block::query()->count());
  }

  #[Test]
  public function html_fallback_creates_explicit_html_block_only_when_present_in_signed_plan(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plainAnalysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'No HTML Fallback',
      'page_path' => 'no-html-fallback',
      'source_html' => '<main><p>Plain copy.</p></main>',
    ]));

    $this->actingAs($user)->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($plainAnalysis));

    $this->assertFalse(Page::query()
      ->whereHas('translations', fn ($query) => $query->where('slug', 'no-html-fallback'))
      ->firstOrFail()
      ->blocks()
      ->where('type', 'html')
      ->exists());

    $fallbackAnalysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'With HTML Fallback',
      'page_path' => 'with-html-fallback',
      'source_html' => '<main><div data-widget="pricing"><span>Custom widget</span><canvas></canvas></div></main>',
    ]));

    $this->actingAs($user)->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($fallbackAnalysis));

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'with-html-fallback'))->firstOrFail();
    $htmlBlock = $page->blocks()->with('textTranslations')->where('type', 'html')->firstOrFail();

    $this->assertStringContainsString('Custom widget', (string) $htmlBlock->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->content);
  }

  #[Test]
  public function unsupported_media_gallery_suggestions_are_skipped_while_safe_section_is_created(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'Skipped Suggestions',
      'page_path' => 'skipped-suggestions',
      'conversion_profile' => 'webblocks_ui',
      'source_html' => '<main><section class="wb-section">Section</section><figure><img src="https://example.test/photo.jpg" alt="Remote"></figure><div class="wb-gallery"><img src="/one.jpg"><img src="/two.jpg"></div></main>',
    ]));

    $response = $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $page = Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'skipped-suggestions'))->firstOrFail();

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '2 block(s) created')
      && str_contains($status, '2 suggestion(s) skipped'));
    $this->assertSame(['section', 'plain_text'], $page->blocks()->pluck('type')->all());
    $this->assertDatabaseCount('wbcms_media', 0);
  }

  #[Test]
  public function duplicate_target_path_is_rejected_and_creates_no_page(): void
  {
    $this->seedFoundation();

    Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Existing',
      'slug' => 'converted-static-page',
      'status' => Page::STATUS_DRAFT,
    ]);

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([], [
      'page_title' => 'Duplicate Path',
      'page_path' => 'converted-static-page',
    ]);
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('plan_payload');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function duplicate_target_path_is_rejected_before_analysis(): void
  {
    $this->seedFoundation();

    Page::query()->create([
      'site_id' => $this->defaultSite()->id,
      'title' => 'Existing',
      'slug' => 'already-used',
      'status' => Page::STATUS_DRAFT,
    ]);

    $user = User::factory()->superAdmin()->create();
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.analyze'), $this->validPayload([
        'page_path' => 'already-used',
      ]));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('page_path');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function inactive_or_invalid_block_type_in_submitted_plan_is_rejected_and_creates_no_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());
    $signedPlan = $this->signedPlanFromResponse($analysis);
    $signedPlan['plan']['blocks'][0]['block_slug'] = 'text';
    $signedPlan['plan']['blocks'][0]['block_type'] = 'text';
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($signedPlan['plan']));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('plan_payload');
    $this->assertSame($initialPageCount, Page::query()->count());
  }

  #[Test]
  public function invalid_card_region_parent_data_is_rejected_without_creating_page(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $plan = $this->conversionPlan([
      [
        'key' => 'header',
        'block_slug' => 'header',
        'translated_fields' => ['title' => 'Not a card'],
      ],
      [
        'key' => 'card_body',
        'parent_key' => 'header',
        'block_slug' => 'card_body',
      ],
    ], [
      'page_title' => 'Invalid Card Region Parent',
      'page_path' => 'invalid-card-region-parent',
    ]);
    $initialPageCount = Page::query()->count();
    $initialBlockCount = Block::query()->count();

    $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFields($plan))
      ->assertRedirect(route('admin.pages.converter.index'))
      ->assertSessionHasErrors('plan_payload');

    $this->assertSame($initialPageCount, Page::query()->count());
    $this->assertSame($initialBlockCount, Block::query()->count());
  }

  #[Test]
  public function draft_creation_does_not_create_media_navigation_shared_slots_or_published_pages(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'Side Effect Check',
      'page_path' => 'side-effect-check',
    ]));
    $initialMediaCount = Media::query()->count();
    $initialNavigationCount = NavigationItem::query()->count();
    $initialSharedSlotCount = SharedSlot::query()->count();
    $initialPublishedPageCount = Page::query()->where('status', Page::STATUS_PUBLISHED)->count();

    $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $this->assertSame($initialMediaCount, Media::query()->count());
    $this->assertSame($initialNavigationCount, NavigationItem::query()->count());
    $this->assertSame($initialSharedSlotCount, SharedSlot::query()->count());
    $this->assertSame($initialPublishedPageCount, Page::query()->where('status', Page::STATUS_PUBLISHED)->count());
  }

  #[Test]
  public function analyzer_prefers_main_content_over_body_content(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<body><h1>Outside Body Title</h1><main><h1>Main Title</h1><p>Main copy.</p></main></body>',
    ]));

    $response->assertOk();
    $response->assertSeeText('<main>');
    $response->assertSeeText('Main Title');
    $response->assertDontSeeText('Outside Body Title');
  }

  #[Test]
  public function analyzer_strips_script_style_and_unsafe_attributes_from_previews(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><p onclick="alert(1)">Safe copy <a href="javascript:alert(1)">link</a>.</p><script>alert("bad")</script><style>.x{color:red}</style></main>',
    ]));

    $response->assertOk();
    $response->assertSeeText('Safe copy link.');
    $response->assertDontSeeText('alert("bad")');
    $response->assertDontSeeText('color:red');
    $response->assertDontSee('onclick', false);
    $response->assertDontSee('javascript:alert', false);
  }

  #[Test]
  public function analyzer_maps_core_html_elements_to_structured_block_suggestions(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><h2>Heading</h2><p>Plain copy.</p><pre><code>php artisan test</code></pre><table><tr><td>Cell</td></tr></table><blockquote>Quote copy</blockquote><ul><li>One</li><li>Two</li></ul></main>',
    ]));

    $response->assertOk();
    $response->assertSeeText('header');
    $response->assertSeeText('plain_text');
    $response->assertSeeText('code');
    $response->assertSeeText('table');
    $response->assertSeeText('quote');
    $response->assertSeeText('list');
  }

  #[Test]
  public function analyzer_maps_webblocks_ui_classes_to_structured_block_suggestions(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'conversion_profile' => 'webblocks_ui',
      'source_html' => '<main><header class="wb-content-header"><h1>Intro</h1></header><section class="wb-section">Section</section><article class="wb-card">Card</article><a class="wb-btn" href="/start">Start</a><div class="wb-alert">Notice</div></main>',
    ]));

    $response->assertOk();
    $response->assertSeeText('content_header');
    $response->assertSeeText('section');
    $response->assertSeeText('card');
    $response->assertSeeText('button_link');
    $response->assertSeeText('callout');
  }

  #[Test]
  public function adjacent_details_elements_produce_one_accordion_plan_with_multiple_items(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><details><summary>First question?</summary><p>First answer.</p></details><details><summary>Second question?</summary><p>Second answer.</p></details></main>',
    ]));
    $signedPlan = $this->signedPlanFromResponse($response);
    $blocks = $signedPlan['plan']['blocks'];

    $response->assertOk();
    $this->assertSame(['accordion', 'accordion_item', 'accordion_item'], array_column($blocks, 'block_slug'));
    $this->assertSame('block_1', $blocks[1]['parent_key']);
    $this->assertSame('block_1', $blocks[2]['parent_key']);
    $this->assertSame('First question?', $blocks[1]['translated_fields']['title']);
    $this->assertSame('First answer.', $blocks[1]['translated_fields']['content']);
    $this->assertSame('Second question?', $blocks[2]['translated_fields']['title']);
    $this->assertSame('Second answer.', $blocks[2]['translated_fields']['content']);
  }

  #[Test]
  public function single_details_element_produces_one_accordion_plan_with_one_item(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><details><summary>Only question?</summary><div>Only answer.</div></details></main>',
    ]));
    $blocks = $this->signedPlanFromResponse($response)['plan']['blocks'];

    $response->assertOk();
    $this->assertSame(['accordion', 'accordion_item'], array_column($blocks, 'block_slug'));
    $this->assertSame('block_1', $blocks[1]['parent_key']);
    $this->assertSame('Only question?', $blocks[1]['translated_fields']['title']);
    $this->assertSame('Only answer.', $blocks[1]['translated_fields']['content']);
  }

  #[Test]
  public function landing_page_sections_create_container_blocks_with_meaningful_children(): void
  {
    $this->seedFoundation();
    $this->publishBlockType('accordion', 'Accordion', true);
    $this->publishBlockType('faq', 'FAQ');

    $user = User::factory()->superAdmin()->create();
    $html = <<<'HTML'
<main>
  <section>
    <h1>Build quiz funnels faster</h1>
    <p>Launch a focused landing page without rebuilding every content block by hand.</p>
    <a class="wb-btn wb-btn-primary" href="/demo">Book a demo</a>
  </section>
  <section>
    <h2>Conversion features</h2>
    <p>Each feature stays editable after import.</p>
    <div class="wb-grid">
      <article class="wb-card">
        <div class="wb-card-header"><h3>Fast setup</h3></div>
        <div class="wb-card-body"><p>Turn static sections into structured CMS blocks.</p></div>
        <div class="wb-card-footer"><a class="wb-btn" href="/start">Start now</a></div>
      </article>
      <article class="wb-card">
        <div class="wb-card-header"><h3>Editor safe</h3></div>
        <div class="wb-card-body"><p>Keep wrapper sections separate from child content.</p></div>
        <div class="wb-card-footer"><a class="wb-btn wb-btn-secondary" href="/learn">Learn more</a></div>
      </article>
    </div>
  </section>
  <section>
    <h2>Questions</h2>
    <p>Common launch answers.</p>
    <details><summary>Can the draft be reviewed?</summary><p>Yes, converted pages remain draft-only.</p></details>
    <details><summary>Are sections editable?</summary><p>Yes, section content becomes child blocks.</p></details>
  </section>
</main>
HTML;

    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'Landing Section Children',
      'page_path' => 'landing-section-children',
      'conversion_profile' => 'webblocks_ui',
      'source_html' => $html,
    ]));
    $signedPlan = $this->signedPlanFromResponse($analysis);
    $blocks = $signedPlan['plan']['blocks'];
    $sectionKeys = collect($blocks)
      ->where('block_slug', 'section')
      ->pluck('key')
      ->all();

    $analysis->assertOk();
    $this->assertSame(0, $signedPlan['plan']['summary']['fallback_count']);
    $this->assertSame(0, $signedPlan['plan']['summary']['warning_count']);
    $this->assertCount(3, $sectionKeys);

    foreach ($sectionKeys as $sectionKey) {
      $this->assertTrue(collect($blocks)->contains(
        fn (array $block): bool => ($block['parent_key'] ?? null) === $sectionKey
      ));
    }

    $result = app(PageConversionDraftCreator::class)->create($signedPlan['plan'], $user);
    $sections = $result->page->blocks()->with('children')->where('type', 'section')->orderBy('sort_order')->get();

    $this->assertSame(Page::STATUS_DRAFT, $result->page->status);
    $this->assertNull($result->page->published_at);
    $this->assertSame(0, $result->skippedSuggestionCount);
    $this->assertSame([], $result->warnings);
    $this->assertCount(3, $sections);

    foreach ($sections as $section) {
      $this->assertGreaterThan(0, $section->children->count());
    }

    $this->assertSame(['header', 'plain_text', 'button_link'], $sections[0]->children->pluck('type')->all());
    $this->assertTrue($sections[1]->children->pluck('type')->contains('card'));
    $this->assertTrue($sections[2]->children->pluck('type')->contains('accordion'));
    $this->assertSame(0, $result->page->blocks()->where('type', 'section')->doesntHave('children')->count());
  }

  #[Test]
  public function details_body_is_sanitized_and_media_is_reported_without_importing_media(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><details><summary onclick="bad()">Media question?</summary><p onmouseover="bad()">Safe answer.</p><script>alert("bad")</script><img src="https://example.test/photo.jpg" alt="Remote"></details></main>',
    ]));
    $blocks = $this->signedPlanFromResponse($response)['plan']['blocks'];

    $response->assertOk();
    $response->assertSeeText('Accordion details contain image media. Media import is not implemented in this phase.');
    $this->assertSame('Media question?', $blocks[1]['translated_fields']['title']);
    $this->assertSame('Safe answer.', $blocks[1]['translated_fields']['content']);
    $this->assertStringNotContainsString('alert("bad")', $blocks[1]['source_fragment']['html']);
    $this->assertStringNotContainsString('onclick', $blocks[1]['source_fragment']['html']);
    $this->assertStringNotContainsString('onmouseover', $blocks[1]['source_fragment']['html']);
    $this->assertDatabaseCount('wbcms_media', 0);
  }

  #[Test]
  public function unknown_complex_fragment_becomes_html_fallback_with_warning(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><div data-widget="pricing"><span>Custom widget</span><canvas></canvas></div></main>',
    ]));

    $response->assertOk();
    $response->assertSeeText('HTML Fallback');
    $response->assertSeeText('html');
    $response->assertSeeText('No high-confidence structured block mapping exists for this fragment yet.');
  }

  #[Test]
  public function image_and_gallery_suggestions_report_media_needed_warnings_without_importing_media(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'source_html' => '<main><figure><img src="https://example.test/photo.jpg" alt="Remote"></figure><div class="wb-gallery"><img src="/one.jpg"><img src="/two.jpg"></div></main>',
    ]));

    $response->assertOk();
    $response->assertSeeText('image');
    $response->assertSeeText('gallery');
    $response->assertSeeText('Media import is not implemented in this phase.');
    $this->assertDatabaseCount('wbcms_media', 0);
  }

  #[Test]
  public function webblocks_ui_fixture_pilot_creates_structured_draft_without_importing_media_or_side_effects(): void
  {
    $this->seedFoundation();
    $this->publishBlockType('accordion', 'Accordion', true);
    $this->publishBlockType('faq', 'FAQ');

    $user = User::factory()->superAdmin()->create();
    $initialMediaCount = Media::query()->count();
    $initialNavigationCount = NavigationItem::query()->count();
    $initialSharedSlotCount = SharedSlot::query()->count();
    $initialPublishedPageCount = Page::query()->where('status', Page::STATUS_PUBLISHED)->count();

    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload([
      'page_title' => 'WebBlocks UI Docs Pilot',
      'page_path' => 'webblocks-ui-docs-pilot',
      'conversion_profile' => 'webblocks_ui',
      'source_html' => $this->fixtureHtml('webblocks-ui-docs-pilot.html'),
    ]));

    $analysis->assertOk();
    $analysis->assertSeeText('Analysis Preview');
    $analysis->assertSeeText('Media import is not implemented in this phase.');
    $analysis->assertSee('name="plan_payload"', false);
    $analysis->assertSee('name="plan_signature"', false);

    $signedPlan = $this->signedPlanFromResponse($analysis);
    $this->assertTrue(app(PageConversionPlanSigner::class)->verify($signedPlan['payload'], $signedPlan['signature']));

    $plan = $signedPlan['plan'];
    $slugs = array_column($plan['blocks'], 'block_slug');

    $this->assertSame(0, $plan['summary']['fallback_count']);
    $this->assertGreaterThanOrEqual(18, $plan['summary']['suggestion_count']);
    $this->assertContains('content_header', $slugs);
    $this->assertContains('hero', $slugs);
    $this->assertContains('card', $slugs);
    $this->assertContains('card_header', $slugs);
    $this->assertContains('card_body', $slugs);
    $this->assertContains('card_footer', $slugs);
    $this->assertContains('button_link', $slugs);
    $this->assertContains('code', $slugs);
    $this->assertContains('table', $slugs);
    $this->assertContains('accordion', $slugs);
    $this->assertContains('accordion_item', $slugs);
    $this->assertContains('image', $slugs);
    $this->assertNotContains('gallery', $slugs);
    $this->assertNotSame(['html'], array_values(array_unique($slugs)));
    $this->assertSame('Build with WebBlocks UI', $plan['blocks'][0]['translated_fields']['title']);

    $firstCardIndex = array_search('card', $slugs, true);
    $this->assertIsInt($firstCardIndex);
    $this->assertSame([
      'card',
      'card_header',
      'header',
      'card_body',
      'plain_text',
      'card_footer',
      'button_link',
    ], array_slice($slugs, $firstCardIndex, 7));

    $imageSuggestion = collect($plan['blocks'])->firstWhere('block_slug', 'image');
    $this->assertIsArray($imageSuggestion);
    $this->assertSame(['Image media was detected. Media import is not implemented in this phase.'], $imageSuggestion['warnings']);

    $create = $this->actingAs($user)
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $create->assertSessionHasNoErrors();
    $page = Page::query()
      ->whereHas('translations', fn ($query) => $query->where('slug', 'webblocks-ui-docs-pilot'))
      ->firstOrFail();
    $createdSlugs = Block::query()
      ->where('page_id', $page->id)
      ->orderBy('id')
      ->pluck('type')
      ->all();

    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertNull($page->published_at);
    $this->assertSame([
      'content_header',
      'section',
      'hero',
      'card',
      'card_header',
      'header',
      'card_body',
      'plain_text',
      'card_footer',
      'button_link',
      'card',
      'card_header',
      'header',
      'card_body',
      'plain_text',
      'card_footer',
      'button_link',
      'code',
      'table',
      'accordion',
      'faq',
      'faq',
    ], $createdSlugs);
    $this->assertNotContains('image', $createdSlugs);
    $this->assertNotContains('gallery', $createdSlugs);
    $this->assertSame($initialMediaCount, Media::query()->count());
    $this->assertSame($initialNavigationCount, NavigationItem::query()->count());
    $this->assertSame($initialSharedSlotCount, SharedSlot::query()->count());
    $this->assertSame($initialPublishedPageCount, Page::query()->where('status', Page::STATUS_PUBLISHED)->count());
  }

  #[Test]
  public function site_scoped_users_cannot_analyze_for_inaccessible_sites(): void
  {
    $this->seedFoundation();

    $site = $this->defaultSite();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other-site',
      'domain' => 'other.example.test',
      'is_primary' => false,
    ]);
    $otherSite->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

    $editor = User::factory()->editor()->create();
    $editor->sites()->sync([$site->id]);

    $response = $this->actingAs($editor)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.analyze'), $this->validPayload([
        'site_id' => $otherSite->id,
      ]));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('site_id');
  }
}
