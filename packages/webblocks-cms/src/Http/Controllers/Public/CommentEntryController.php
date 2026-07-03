<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Http\Requests\CommentEntryRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Engagement\CommentSpamScorer;
use WebBlocks\Cms\Support\Engagement\EngagementVisitor;

class CommentEntryController extends Controller
{
  public function store(CommentEntryRequest $request): RedirectResponse
  {
    $payload = $request->payload();
    $block = Block::query()->with(['blockType', 'page.site'])->findOrFail($payload['block_id']);

    abort_unless($block->typeSlug() === 'comments', 404);
    abort_unless($block->status === 'published', 404);
    abort_unless($block->page?->status === 'published', 404);
    abort_unless((bool) $block->setting('form_enabled', true), 404);

    if ($payload['page_id'] && $payload['page_id'] !== $block->page_id) {
      abort(404);
    }

    $redirects = app(ContactFormRedirects::class);
    $sourceUrl = $redirects->baseUrl($payload['source_url'], $block->page?->publicUrl() ?: url('/'));

    if (! Schema::hasTable('wbcms_comment_entries')) {
      return redirect($sourceUrl)
        ->with('comment_success_block_id', $block->id)
        ->with('comment_success_message', 'Comments are temporarily unavailable.');
    }

    $minimumSubmitSeconds = (int) config('contact.minimum_submit_seconds', 3);

    if ($payload['form_check_filled'] || (now()->timestamp - $payload['submitted_at']) < $minimumSubmitSeconds) {
      return redirect($sourceUrl)
        ->with('comment_success_block_id', $block->id)
        ->with('comment_success_message', 'Thanks. Your comment will be reviewed before it appears.');
    }

    $visitor = app(EngagementVisitor::class);
    $siteId = (int) $block->page?->site_id;
    $ipHash = $visitor->ipHash($request->ip());
    $spamSignal = app(CommentSpamScorer::class)->score($payload, $ipHash);

    CommentEntry::query()->create([
      'site_id' => $siteId ?: null,
      'page_id' => $block->page_id,
      'block_id' => $block->id,
      'author_name' => $payload['author_name'],
      'body' => $payload['body'],
      'status' => $spamSignal['is_spam'] ? 'spam' : 'pending',
      'source_url' => $sourceUrl,
      'visitor_hash' => $visitor->visitorHash($request, $siteId, $block->id),
      'ip_hash' => $ipHash,
      'user_agent' => $request->userAgent(),
      'spam_score' => $spamSignal['score'],
      'spam_reasons' => $spamSignal['reasons'] !== [] ? $spamSignal['reasons'] : null,
    ]);

    return redirect($sourceUrl)
      ->with('comment_success_block_id', $block->id)
      ->with('comment_success_message', 'Thanks. Your comment will be reviewed before it appears.');
  }
}
