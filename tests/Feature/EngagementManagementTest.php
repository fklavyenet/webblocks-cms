<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Engagement\StoreRating;
use WebBlocks\Cms\Http\Controllers\Admin\EngagementController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Requests\Admin\EngagementIndexRequest;
use WebBlocks\Cms\Http\Requests\Admin\ModerateCommentsRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\EngagementQuery;
use WebBlocks\Cms\Queries\PublicEngagementQuery;
use WebBlocks\Cms\Support\Engagement\EngagementVisitor;
use WebBlocks\Cms\Tests\TestCase;

class EngagementManagementTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_07_12_140000_ensure_prefixed_engagement_tables.php')->up();
  }

  protected function defineRoutes($router): void
  {
    foreach (['index', 'comments', 'ratings'] as $screen) {
      Route::get('/test-engagement/'.$screen, fn (EngagementIndexRequest $request) => app(EngagementController::class)->{$screen}($request));
    }
    Route::post('/test-engagement/bulk', fn (ModerateCommentsRequest $request) => app(EngagementController::class)->bulkCommentStatus($request));
    Route::get('/test-engagement/comment/{commentEntry}', fn (Request $request, CommentEntry $commentEntry) => app(EngagementController::class)->showComment($request, $commentEntry))->middleware('web');
  }

  private function operator(Site $site)
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $user = Mockery::mock('App\\Models\\User')->makePartial();
    // No approver FK for a synthetic operator. Real installations retain IDs.
    $user->forceFill(['id' => null, 'name' => 'Test Operator']);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $user->shouldReceive('can')->with('manage-site-operations')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleSiteIds')->andReturn(collect([$site->id]));
    $user->shouldReceive('hasSiteAccess')->andReturnUsing(fn ($id) => (int) $id === $site->id);

    return $user;
  }

  private function page(string $handle = 'studio'): Page
  {
    $site = Site::firstOrCreate(['handle' => $handle], ['name' => $handle]);

    return Page::create(['site_id' => $site->id, 'slug' => 'game-'.Page::count(), 'status' => 'published']);
  }

  private function block(Page $page, string $type, array $settings = []): Block
  {
    $blockType = BlockType::firstOrCreate(['slug' => $type], ['name' => $type, 'is_system' => true]);

    return Block::create(['page_id' => $page->id, 'block_type_id' => $blockType->id, 'status' => 'published', 'settings' => json_encode($settings)]);
  }

  private function comment(Page $page, string $status = 'pending', ?Block $block = null): CommentEntry
  {
    return CommentEntry::create(['site_id' => $page->site_id, 'page_id' => $page->id, 'block_id' => $block?->id, 'body' => 'Synthetic feedback '.str_repeat('full text ', 30), 'status' => $status]);
  }

  private function rating(Page $page, int $value = 5, array $extra = []): ContentRating
  {
    return ContentRating::create($extra + ['site_id' => $page->site_id, 'page_id' => $page->id, 'rating_value' => $value, 'rating_max' => 5, 'status' => 'active']);
  }

  #[Test]
  public function page_scope_recovers_orphans_but_never_other_pages_sites_or_unapproved_feedback(): void
  {
    $page = $this->page();
    $old = $this->block($page, 'comments');
    $approved = $this->comment($page, 'approved', $old);
    $this->comment($page, 'pending', $old);
    $old->delete();
    $this->assertNull($approved->fresh()->block_id);
    $replacement = $this->block($page, 'comments', ['data_scope' => 'page']);
    $this->comment($this->page(), 'approved');
    $this->comment($this->page('foreign'), 'approved');
    $query = app(PublicEngagementQuery::class);
    $this->assertSame([$approved->id], $query->comments($replacement)->pluck('id')->all());
    $replacement->update(['settings' => '{}']);
    $this->assertSame(0, $query->comments($replacement)->count());
    $rating = $this->rating($page);
    $this->rating($page, 1, ['status' => 'hidden']);
    $this->rating($page, 9, ['rating_max' => 10]);
    $this->rating($this->page('foreign'));
    $ratingBlock = $this->block($page, 'rating', ['data_scope' => 'page']);
    $this->assertSame([$rating->id], $query->ratings($ratingBlock)->pluck('id')->all());
  }

  #[Test]
  public function a_session_updates_one_page_vote_after_its_block_is_replaced_and_first_vote_can_be_kept(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'rating', ['data_scope' => 'page']);
    $request = Request::create('/game', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $action = app(StoreRating::class);
    $action->execute($block, $request, 5, '/game');
    $vote = ContentRating::firstOrFail();
    $block->delete();
    $replacement = $this->block($page, 'rating', ['data_scope' => 'page']);
    $action->execute($replacement, $request, 3, '/game');
    $this->assertSame(1, ContentRating::count());
    $this->assertSame(3, $vote->fresh()->rating_value);
    $this->assertSame($vote->created_at->toDateTimeString(), $vote->fresh()->created_at->toDateTimeString());
    $replacement->update(['settings' => json_encode(['data_scope' => 'page', 'allow_change' => false])]);
    $action->execute($replacement, $request, 1, '/game');
    $this->assertSame(3, $vote->fresh()->rating_value);
    $different = Request::create('/game', 'POST');
    $different->setLaravelSession(app('session')->driver());
    $different->session()->forget('webblocks.engagement.visitor_id');
    $action->execute($replacement, $different, 4, '/game');
    $this->assertSame(2, ContentRating::count());
  }

  #[Test]
  public function switching_to_page_scope_recognizes_a_legacy_vote_with_a_surviving_block_id(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'rating');
    $request = Request::create('/game', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $vote = $this->rating($page, 5, ['block_id' => $block->id, 'visitor_hash' => app(EngagementVisitor::class)->visitorHash($request, $page->site_id, $block->id)]);
    $block->update(['settings' => json_encode(['data_scope' => 'page'])]);
    app(StoreRating::class)->execute($block, $request, 2, '/game');
    $this->assertSame(1, ContentRating::count());
    $this->assertSame(2, $vote->fresh()->rating_value);
  }

  #[Test]
  public function overview_scopes_all_counts_and_averages_and_keeps_the_backlog_distinct_from_recent_submissions(): void
  {
    $page = $this->page();
    $old = $this->comment($page);
    $old->forceFill(['created_at' => now()->subDays(60)])->save();
    $this->comment($page, 'approved');
    $this->rating($page, 5);
    $this->rating($page, 3);
    $this->rating($page, 1, ['status' => 'hidden']);
    $this->rating($page, 10, ['rating_max' => 10]);
    $foreign = $this->page('foreign');
    $this->comment($foreign);
    $this->rating($foreign, 1);
    $user = $this->operator($page->site);
    $query = app(EngagementQuery::class);
    $data = $query->overview($user, []);
    $this->assertSame(2, $data['commentsCount']);
    $this->assertSame(1, $data['pendingCommentsCount']);
    $this->assertSame(1, $data['comments30']);
    $this->assertSame(4.0, $data['averageRating']);
    $this->assertSame(2, $data['activeRatingsCount']);
    $this->assertSame(4, $data['ratingsCount']);
    $this->assertSame(1, (int) $data['distribution'][3]);
    $summary = $query->pageSummary($user, []);
    $this->assertSame([$page->id], $summary->pluck('id')->all());
    $this->assertSame(2, (int) $summary->first()->vote_count);
    $this->assertSame(1, (int) $summary->first()->pending_count);
  }

  #[Test]
  public function panels_render_real_filters_full_comment_icons_and_distribution_without_marking_or_sending_anything(): void
  {
    $page = $this->page();
    $comment = $this->comment($page);
    $this->rating($page);
    $user = $this->operator($page->site);
    $this->actingAs($user);
    $this->get('/test-engagement/index')->assertOk()->assertSee('Feedback by page')->assertSee('Review pending');
    $this->get('/test-engagement/comments?site='.$page->site_id)->assertOk()->assertSee('engagement-bulk-moderation')->assertSee('wb-icon-eye', false)->assertSee('Submitted from');
    $this->get('/test-engagement/ratings')->assertOk()->assertSee('5 / 5')->assertSee('Rating summary')->assertSee('Updated');
    $this->get('/test-engagement/comment/'.$comment->id)->assertOk()->assertSee($comment->body);
    $this->assertSame('pending', $comment->fresh()->status);
  }

  #[Test]
  public function filters_and_details_refuse_foreign_resources_and_date_filter_includes_the_whole_last_day(): void
  {
    $page = $this->page();
    $comment = $this->comment($page);
    $comment->forceFill(['created_at' => '2026-09-10 23:59:59'])->save();
    $foreign = $this->page('foreign');
    $foreignComment = $this->comment($foreign);
    $this->actingAs($this->operator($page->site));
    $this->getJson('/test-engagement/comments?site='.$foreign->site_id)->assertUnprocessable();
    $this->getJson('/test-engagement/comments?page_id='.$foreign->id)->assertUnprocessable();
    $this->get('/test-engagement/comment/'.$foreignComment->id)->assertForbidden();
    $this->get('/test-engagement/comments?until=2026-09-10')->assertOk()->assertSee('Synthetic feedback');
    $this->get('/test-engagement/comments?from=2026-09-11')->assertOk()->assertDontSee('Synthetic feedback');
  }

  #[Test]
  public function mixed_access_bulk_moderation_is_atomic_and_success_preserves_filters(): void
  {
    $page = $this->page();
    $comment = $this->comment($page);
    $foreign = $this->comment($this->page('foreign'));
    $this->actingAs($this->operator($page->site));
    $this->post('/test-engagement/bulk', ['comment_ids' => [$comment->id, $foreign->id], 'status' => 'approved'])->assertForbidden();
    $this->assertSame('pending', $comment->fresh()->status);
    $this->assertSame('pending', $foreign->fresh()->status);
    $this->post('/test-engagement/bulk', ['comment_ids' => [$comment->id], 'status' => 'approved', 'return_query' => 'site='.$page->site_id.'&status=pending&page=2'])->assertRedirect(route('admin.engagement.comments.index', ['site' => $page->site_id, 'status' => 'pending', 'page' => 2]));
    $this->assertSame('approved', $comment->fresh()->status);
    $this->assertNotNull($comment->fresh()->approved_at);
  }

  #[Test]
  public function feedback_scope_is_writable_through_the_real_block_patch_api_and_existing_votes_render_as_pressed(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'rating');
    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', [], [], [], [], json_encode(['settings' => ['data_scope' => 'page', 'show_summary' => true]]));
    $request->headers->set('Content-Type', 'application/json');
    $response = app(InternalContentResourceController::class)->updateBlock($request, $block);
    $this->assertSame(200, $response->getStatusCode());
    $block = $block->fresh();
    $this->assertSame('page', $block->setting('data_scope'));
    $sessionRequest = Request::create('/game');
    $sessionRequest->setLaravelSession(app('session')->driver());
    app(StoreRating::class)->execute($block, $sessionRequest, 4, '/game');
    $this->app->instance('request', $sessionRequest);
    $this->rating($page, 1, ['status' => 'hidden']);
    $html = view('webblocks-cms::pages.partials.blocks.rating', compact('block', 'page'))->render();
    $this->assertStringContainsString('Average 4 / 5 from 1 rating.', $html);
    $this->assertStringContainsString('value="4" aria-pressed="true"', $html);
    $comments = $this->block($page, 'comments');
    $patch = Request::create('/webadmin/api/blocks/'.$comments->id, 'PATCH', [], [], [], [], json_encode(['settings' => ['data_scope' => 'page', 'show_approved' => true]]));
    $patch->headers->set('Content-Type', 'application/json');
    $this->assertSame(200, app(InternalContentResourceController::class)->updateBlock($patch, $comments)->getStatusCode());
    $this->assertSame('page', $comments->fresh()->setting('data_scope'));
  }

  #[Test]
  public function public_comments_render_only_approved_content_and_paginate_beyond_25(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'comments', ['data_scope' => 'page', 'form_enabled' => false]);
    for ($i = 0; $i < 26; $i++) {
      $this->comment($page, 'approved')->update(['body' => 'Approved '.$i]);
    }
    $this->comment($page)->update(['body' => 'Must remain private']);
    $html = view('webblocks-cms::pages.partials.blocks.comments', compact('block', 'page') + ['errors' => new ViewErrorBag])->render();
    $this->assertStringNotContainsString('Must remain private', $html);
    $this->assertStringContainsString('comments_page_'.$block->id.'=2', $html);
    $this->assertStringContainsString('wb-pagination', $html);
    $this->assertStringNotContainsString('Approved 0<', $html);
  }

  #[Test]
  public function public_comments_separate_named_regions_and_keep_metadata_inline_without_leaking_unapproved_text(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'comments', ['data_scope' => 'page', 'show_author_name' => true]);
    $this->comment($page, 'approved')->update(['author_name' => 'Test Reader', 'body' => "<script>unsafe</script>\nSecond line"]);
    $this->comment($page)->update(['body' => 'Private pending feedback']);
    $html = view('webblocks-cms::pages.partials.blocks.comments', compact('block', 'page') + ['errors' => new ViewErrorBag])->render();
    $document = new \DOMDocument;
    $this->assertTrue($document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING));
    $xpath = new \DOMXPath($document);
    $list = $xpath->query('//section[@aria-labelledby="comments-list-title-'.$block->id.'"]')->item(0);
    $formRegion = $xpath->query('//section[@aria-labelledby="comments-form-title-'.$block->id.'"]')->item(0);
    $this->assertNotNull($list);
    $this->assertNotNull($formRegion);
    $this->assertSame($list->parentNode, $formRegion->parentNode);
    $this->assertSame(0, $xpath->query('.//form', $list)->length);
    $this->assertSame(1, $xpath->query('.//form', $formRegion)->length);
    $this->assertSame(1, $xpath->query('.//article/div[strong and time]', $list)->length);
    $this->assertSame('Comments', $xpath->query('.//h3', $list)->item(0)->textContent);
    $this->assertSame('Leave a comment', $xpath->query('.//h3', $formRegion)->item(0)->textContent);
    $this->assertSame('1', $xpath->query('.//header/span', $list)->item(0)->textContent);
    $this->assertSame(0, $xpath->query('.//script', $list)->length);
    $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
    $this->assertStringContainsString('Second line', $html);
    $this->assertStringNotContainsString('Private pending feedback', $html);
    $this->assertSame('3', $xpath->query('.//textarea', $formRegion)->item(0)->getAttribute('rows'));
    foreach (['_token', 'block_id', 'page_id', 'source_url', '_form_stamp', '_form_check_name'] as $field) {
      $this->assertSame(1, $xpath->query('.//input[@name="'.$field.'"]', $formRegion)->length);
    }
  }

  #[Test]
  public function compact_comments_keep_list_form_and_author_visibility_independent(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'comments', ['data_scope' => 'page', 'form_enabled' => false, 'show_author_name' => false]);
    $this->comment($page, 'approved')->update(['author_name' => 'Private Author Name', 'body' => 'Approved visible text']);
    $render = fn () => view('webblocks-cms::pages.partials.blocks.comments', ['block' => $block, 'page' => $page, 'errors' => new ViewErrorBag])->render();
    $html = $render();
    $this->assertStringContainsString('Approved visible text', $html);
    $this->assertStringNotContainsString('Private Author Name', $html);
    $this->assertStringNotContainsString('comments-form-title-', $html);
    $this->assertStringContainsString('New comments are closed.', $html);
    $block->settings = json_encode(['data_scope' => 'page', 'show_approved' => false]);
    $html = $render();
    $this->assertStringNotContainsString('Approved visible text', $html);
    $this->assertStringNotContainsString('comments-list-title-', $html);
    $this->assertStringContainsString('comments-form-title-', $html);
    $this->assertStringContainsString('<form', $html);
  }

  #[Test]
  public function compact_comment_region_headings_use_the_render_locale(): void
  {
    $page = $this->page();
    $block = $this->block($page, 'comments');
    foreach (['en', 'de', 'fr', 'it', 'es', 'tr'] as $locale) {
      $block->setAttribute('render_locale_code', $locale);
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/blocks.php';
      $html = view('webblocks-cms::pages.partials.blocks.comments', compact('block', 'page') + ['errors' => new ViewErrorBag])->render();
      $this->assertStringContainsString('>'.$catalog['comments']['list_title'].'</h3>', $html);
      $this->assertStringContainsString('>'.$catalog['comments']['form_title'].'</h3>', $html);
    }
  }
}
