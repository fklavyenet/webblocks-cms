<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

class PageConverterTest extends TestCase
{
  use RefreshDatabase;

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
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
