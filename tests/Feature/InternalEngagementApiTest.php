<?php

namespace Tests\Feature;

use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;

class InternalEngagementApiTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function engagement_read_endpoints_require_engagement_read_capability(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_READ]);

    $this->withHeader('Authorization', 'Bearer secret-token')
      ->getJson('/webadmin/api/engagement/comments')
      ->assertForbidden()
      ->assertJsonPath('code', 'missing_internal_api_capability')
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::ENGAGEMENT_READ);

    $this->withHeader('Authorization', 'Bearer secret-token')
      ->getJson('/webadmin/api/engagement/ratings')
      ->assertForbidden()
      ->assertJsonPath('code', 'missing_internal_api_capability')
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::ENGAGEMENT_READ);
  }

  #[Test]
  public function engagement_read_endpoint_lists_comments_without_private_visitor_fields(): void
  {
    [$page, , $commentsBlock] = $this->createEngagementPage();
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::ENGAGEMENT_READ]);

    CommentEntry::query()->create([
      'site_id' => $page->site_id,
      'page_id' => $page->id,
      'block_id' => $commentsBlock->id,
      'author_name' => 'Player',
      'body' => 'This game is brilliant.',
      'status' => 'pending',
      'source_url' => '/games/test-game',
      'visitor_hash' => 'private-visitor-hash',
      'ip_hash' => 'private-ip-hash',
      'user_agent' => 'Private User Agent',
      'spam_score' => 0,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer secret-token')
      ->getJson('/webadmin/api/engagement/comments?status=pending');

    $response
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('table_ready', true)
      ->assertJsonPath('comments.0.body', 'This game is brilliant.')
      ->assertJsonPath('comments.0.status', 'pending')
      ->assertJsonPath('summary.by_status.pending', 1);

    $content = (string) $response->getContent();
    $this->assertStringNotContainsString('private-visitor-hash', $content);
    $this->assertStringNotContainsString('private-ip-hash', $content);
    $this->assertStringNotContainsString('Private User Agent', $content);
  }

  #[Test]
  public function engagement_read_endpoint_lists_rating_summary(): void
  {
    [$page, $ratingBlock] = $this->createEngagementPage();
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::ENGAGEMENT_READ]);

    foreach ([5, 4, 5] as $index => $rating) {
      ContentRating::query()->create([
        'site_id' => $page->site_id,
        'page_id' => $page->id,
        'block_id' => $ratingBlock->id,
        'rating_value' => $rating,
        'rating_max' => 5,
        'status' => 'active',
        'source_url' => '/games/test-game',
        'visitor_hash' => 'visitor-'.$index,
        'ip_hash' => 'private-ip-'.$index,
        'user_agent' => 'Private Agent '.$index,
      ]);
    }

    $response = $this->withHeader('Authorization', 'Bearer secret-token')
      ->getJson('/webadmin/api/engagement/ratings?block_id='.$ratingBlock->id);

    $response
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('summary.total', 3)
      ->assertJsonPath('summary.average', 4.67)
      ->assertJsonPath('summary.by_value.5', 2);

    $this->assertStringNotContainsString('private-ip-1', (string) $response->getContent());
  }

  #[Test]
  public function engagement_moderation_requires_moderate_capability_and_updates_comment_status(): void
  {
    [$page, , $commentsBlock] = $this->createEngagementPage();
    $comment = CommentEntry::query()->create([
      'site_id' => $page->site_id,
      'page_id' => $page->id,
      'block_id' => $commentsBlock->id,
      'body' => 'Please approve me.',
      'status' => 'pending',
    ]);

    $this->createInternalApiToken('read-token', [CmsApiTokenCapabilities::ENGAGEMENT_READ]);
    $this->withHeader('Authorization', 'Bearer read-token')
      ->patchJson('/webadmin/api/engagement/comments/'.$comment->id, ['status' => 'approved'])
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::ENGAGEMENT_MODERATE);

    $this->createInternalApiToken('moderate-token', [CmsApiTokenCapabilities::ENGAGEMENT_MODERATE]);
    $this->withHeader('Authorization', 'Bearer moderate-token')
      ->patchJson('/webadmin/api/engagement/comments/'.$comment->id, ['status' => 'approved'])
      ->assertOk()
      ->assertJsonPath('comment.status', 'approved');

    $comment->refresh();

    $this->assertSame('approved', $comment->status);
    $this->assertNotNull($comment->approved_at);
  }

  private function createEngagementPage(): array
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $ratingType = $this->blockType('rating', 'Rating');
    $commentsType = $this->blockType('comments', 'Comments');
    $slug = 'internal-engagement-'.strtolower((string) str()->random(8));
    $path = '/games/'.$slug;

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Test Game',
      'slug' => $slug,
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate([
      'page_id' => $page->id,
      'locale_id' => $site->locales()->firstOrFail()->id,
    ], [
      'site_id' => $site->id,
      'name' => 'Test Game',
      'slug' => $slug,
      'path' => $path,
    ]);

    $ratingBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $ratingType->id,
      'type' => 'rating',
      'source_type' => 'engagement',
      'slot' => 'main',
      'sort_order' => 0,
      'settings' => json_encode(['scale' => 5, 'allow_change' => true, 'show_summary' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    $commentsBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $commentsType->id,
      'type' => 'comments',
      'source_type' => 'engagement',
      'slot' => 'main',
      'sort_order' => 1,
      'settings' => json_encode(['form_enabled' => true, 'show_approved' => true], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => true,
    ]);

    return [$page, $ratingBlock, $commentsBlock];
  }

  private function blockType(string $slug, string $name): BlockType
  {
    return BlockType::query()->create([
      'slug' => $slug,
      'name' => $name,
      'category' => 'system',
      'source_type' => 'engagement',
      'is_system' => true,
      'is_container' => false,
      'status' => 'published',
    ]);
  }

  private function createInternalApiToken(string $token, ?array $capabilities = null): void
  {
    CmsApiToken::query()->create([
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
      'capabilities' => $capabilities,
    ]);
  }
}
