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
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
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
  public function unsupported_media_gallery_and_container_suggestions_are_skipped_and_reported(): void
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

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '0 block(s) created')
      && str_contains($status, '3 suggestion(s) skipped'));
    $this->assertSame(0, $page->blocks()->count());
    $this->assertDatabaseCount('media', 0);
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
    $analysis = $this->actingAs($user)->post(route('admin.pages.converter.analyze'), $this->validPayload());
    $initialPageCount = Page::query()->count();

    $response = $this->actingAs($user)
      ->from(route('admin.pages.converter.index'))
      ->post(route('admin.pages.converter.create-draft'), $this->signedPlanFieldsFromResponse($analysis));

    $response->assertRedirect(route('admin.pages.converter.index'));
    $response->assertSessionHasErrors('plan_payload');
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
    $this->assertDatabaseCount('media', 0);
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
