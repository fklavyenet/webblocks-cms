<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\System\SystemSettings;

class EngagementBlocksTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function rating_block_updates_existing_visitor_rating_instead_of_creating_empty_comments(): void
  {
    [$page, $ratingBlock] = $this->createEngagementPage();

    $payload = [
      'block_id' => $ratingBlock->id,
      'page_id' => $page->id,
      'source_url' => '/games/test-game',
    ];

    $this->post(route('content-ratings.store'), $payload + ['rating_value' => 3])
      ->assertRedirect('/games/test-game');

    $this->post(route('content-ratings.store'), $payload + ['rating_value' => 5])
      ->assertRedirect('/games/test-game');

    $this->assertSame(1, ContentRating::query()->count());
    $this->assertSame(5, ContentRating::query()->firstOrFail()->rating_value);
    $this->assertSame(0, CommentEntry::query()->count());
  }

  #[Test]
  public function comments_are_pending_by_default_and_only_approved_comments_render_publicly(): void
  {
    [$page, , $commentsBlock] = $this->createEngagementPage();

    $this->post(route('comment-entries.store'), $this->commentPayload($page, $commentsBlock, [
      'body' => 'This game is fun.',
    ]))->assertRedirect('/games/test-game');

    $comment = CommentEntry::query()->firstOrFail();

    $this->assertSame('pending', $comment->status);

    $this->get('/games/test-game')
      ->assertDontSee('This game is fun.');

    $comment->update(['status' => 'approved']);

    $this->get('/games/test-game')
      ->assertSee('This game is fun.');
  }

  #[Test]
  public function comments_with_links_are_quarantined_as_spam(): void
  {
    [$page, , $commentsBlock] = $this->createEngagementPage();

    $this->post(route('comment-entries.store'), $this->commentPayload($page, $commentsBlock, [
      'body' => 'Visit https://example.com for my email me@example.com',
    ]))->assertRedirect('/games/test-game');

    $comment = CommentEntry::query()->firstOrFail();

    $this->assertSame('spam', $comment->status);
    $this->assertGreaterThanOrEqual(60, $comment->spam_score);
  }

  #[Test]
  public function super_admin_can_open_engagement_admin_screens(): void
  {
    $this->createEngagementPage();
    $admin = User::factory()->create([
      'role' => User::ROLE_SUPER_ADMIN,
      'is_active' => true,
    ]);

    $this->actingAs($admin)
      ->get(route('admin.engagement.comments.index'))
      ->assertOk()
      ->assertSee('Comments');

    $this->actingAs($admin)
      ->get(route('admin.engagement.ratings.index'))
      ->assertOk()
      ->assertSee('Ratings');
  }

  #[Test]
  public function public_engagement_blocks_render_controlled_messages_when_tables_are_not_ready(): void
  {
    $this->createEngagementPage();

    Schema::dropIfExists('wbcms_content_ratings');

    $this->get('/games/test-game')
      ->assertOk()
      ->assertSee('Ratings are temporarily unavailable.');
  }

  #[Test]
  public function public_comments_block_renders_controlled_message_when_comment_table_is_not_ready(): void
  {
    $this->createEngagementPage();

    Schema::dropIfExists('wbcms_comment_entries');

    $this->get('/games/test-game')
      ->assertOk()
      ->assertSee('Comments are temporarily unavailable.');
  }

  #[Test]
  public function public_engagement_blocks_use_current_public_locale_copy(): void
  {
    [$page, $ratingBlock, $commentsBlock] = $this->createEngagementPage();
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $turkish = Locale::query()->create([
      'code' => 'tr',
      'name' => 'Turkish',
      'is_default' => false,
      'is_enabled' => true,
    ]);
    $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $turkish->id,
      'name' => 'Test Oyun',
      'slug' => 'test-oyun',
      'path' => '/test-oyun',
    ]);

    $this->get('/tr/test-oyun')
      ->assertOk()
      ->assertSee('Bu icerigi puanla')
      ->assertSee('Henuz puan yok.')
      ->assertSee('Yorum gonder')
      ->assertSee('Henuz onaylanmis yorum yok.');

    $this->post(route('content-ratings.store'), [
      'block_id' => $ratingBlock->id,
      'page_id' => $page->id,
      'source_url' => '/tr/test-oyun',
      'rating_value' => 5,
    ])->assertRedirect('/tr/test-oyun')
      ->assertSessionHas('rating_success_message', 'Puaniniz icin tesekkurler.');

    $this->post(route('comment-entries.store'), $this->commentPayload($page, $commentsBlock, [
      'source_url' => '/tr/test-oyun',
      'body' => 'Guzel oyun.',
    ]))->assertRedirect('/tr/test-oyun')
      ->assertSessionHas('comment_success_message', 'Tesekkurler. Yorumunuz gorunmeden once incelenecek.');

    $commentValidationResponse = $this->from('/tr/test-oyun')
      ->post(route('comment-entries.store'), $this->commentPayload($page, $commentsBlock, [
        'source_url' => '/tr/test-oyun',
        'body' => '',
      ]));

    $this->assertSame(302, $commentValidationResponse->baseResponse->getStatusCode());
    $this->assertStringEndsWith('/tr/test-oyun#comments-'.$commentsBlock->id, (string) $commentValidationResponse->baseResponse->headers->get('Location'));
    $commentErrors = $commentValidationResponse->baseResponse->getSession()->get('errors');
    $commentMessage = is_array($commentErrors)
      ? (data_get($commentErrors, 'body.0') ?? data_get($commentErrors, 'default.messages.body.0'))
      : $commentErrors->first('body');
    $this->assertSame('Bir yorum girin.', $commentMessage);

    $ratingValidationResponse = $this->from('/tr/test-oyun')
      ->post(route('content-ratings.store'), [
        'block_id' => $ratingBlock->id,
        'page_id' => $page->id,
        'source_url' => '/tr/test-oyun',
        'rating_value' => 9,
      ]);

    $this->assertSame(302, $ratingValidationResponse->baseResponse->getStatusCode());
    $this->assertStringEndsWith('/tr/test-oyun#rating-'.$ratingBlock->id, (string) $ratingValidationResponse->baseResponse->headers->get('Location'));
    $ratingErrors = $ratingValidationResponse->baseResponse->getSession()->get('errors');
    $ratingMessage = is_array($ratingErrors)
      ? (data_get($ratingErrors, 'rating_value.0') ?? data_get($ratingErrors, 'default.messages.rating_value.0'))
      : $ratingErrors->first('rating_value');
    $this->assertSame('En fazla 5 yildiz secin.', $ratingMessage);
  }

  #[Test]
  public function engagement_admin_shows_setup_guidance_when_tables_are_not_ready(): void
  {
    $admin = User::factory()->create([
      'role' => User::ROLE_SUPER_ADMIN,
      'is_active' => true,
    ]);

    Schema::dropIfExists('wbcms_comment_entries');
    Schema::dropIfExists('wbcms_content_ratings');

    $this->actingAs($admin)
      ->get(route('admin.engagement.comments.index'))
      ->assertOk()
      ->assertSee('Engagement tables are not ready');

    $this->actingAs($admin)
      ->get(route('admin.engagement.ratings.index'))
      ->assertOk()
      ->assertSee('Engagement tables are not ready');
  }

  #[Test]
  public function engagement_admin_uses_configured_admin_locale_copy(): void
  {
    SystemSetting::query()->updateOrCreate(
      ['key' => SystemSettings::ADMIN_LOCALE],
      ['value' => 'tr'],
    );
    $this->createEngagementPage();
    $admin = User::factory()->create([
      'role' => User::ROLE_SUPER_ADMIN,
      'is_active' => true,
    ]);

    $this->actingAs($admin)
      ->get(route('admin.engagement.comments.index'))
      ->assertOk()
      ->assertSee('Yorumlar')
      ->assertSee('Yorumlarda ara')
      ->assertSee('Yorum bulunamadi');

    $this->actingAs($admin)
      ->get(route('admin.engagement.ratings.index'))
      ->assertOk()
      ->assertSee('Puanlamalar')
      ->assertSee('Puanlama bulunamadi');

    $comment = CommentEntry::query()->create([
      'site_id' => null,
      'page_id' => null,
      'block_id' => null,
      'author_name' => 'Oyuncu',
      'body' => 'Merhaba',
      'status' => 'pending',
      'source_url' => '/test-game',
      'visitor_hash' => 'visitor',
      'ip_hash' => 'ip',
      'user_agent' => 'Test',
      'spam_score' => 0,
    ]);

    $this->actingAs($admin)
      ->patch(route('admin.engagement.comments.status', $comment), ['status' => 'approved'])
      ->assertRedirect()
      ->assertSessionHas('status', 'Yorum durumu guncellendi.');
  }

  private function createEngagementPage(): array
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->where('is_default', true)->firstOrFail();
    $slotType = SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );

    $ratingType = $this->blockType('rating', 'Rating');
    $commentsType = $this->blockType('comments', 'Comments');

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Test Game',
      'slug' => 'test-game',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $locale->id],
      ['site_id' => $site->id, 'name' => 'Test Game', 'slug' => 'test-game', 'path' => '/games/test-game'],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    $ratingBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'rating',
      'block_type_id' => $ratingType->id,
      'source_type' => 'engagement',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'settings' => json_encode(['scale' => 5, 'allow_change' => true, 'show_summary' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $commentsBlock = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'comments',
      'block_type_id' => $commentsType->id,
      'source_type' => 'engagement',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 1,
      'settings' => json_encode(['form_enabled' => true, 'show_approved' => true, 'show_author_name' => false, 'sort_order' => 'newest'], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    return [$page, $ratingBlock, $commentsBlock];
  }

  private function blockType(string $slug, string $name): BlockType
  {
    return BlockType::query()->updateOrCreate(
      ['slug' => $slug],
      [
        'name' => $name,
        'category' => 'system',
        'source_type' => 'engagement',
        'is_system' => true,
        'is_container' => false,
        'sort_order' => 50,
        'status' => 'published',
      ],
    );
  }

  private function commentPayload(Page $page, Block $block, array $overrides = []): array
  {
    return array_merge([
      'block_id' => $block->id,
      'page_id' => $page->id,
      'source_url' => '/games/test-game',
      'author_name' => 'Player',
      'body' => 'Great game.',
      'submitted_at' => now()->subSeconds(10)->timestamp,
      '_form_check_name' => app(ContactFormCheck::class)->signedFieldName($block),
    ], $overrides);
  }
}
