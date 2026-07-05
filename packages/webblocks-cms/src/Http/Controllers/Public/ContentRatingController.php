<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Http\Requests\ContentRatingRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Engagement\EngagementVisitor;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

class ContentRatingController extends Controller
{
  public function __construct(
    private readonly CmsTranslator $translator,
    private readonly PublicLocaleContext $localeContext,
  ) {}

  public function store(ContentRatingRequest $request): RedirectResponse
  {
    $payload = $request->payload();
    $block = Block::query()->with(['blockType', 'page.site'])->findOrFail($payload['block_id']);

    abort_unless($block->typeSlug() === 'rating', 404);
    abort_unless($block->status === 'published', 404);
    abort_unless($block->page?->status === 'published', 404);

    if ($payload['page_id'] && $payload['page_id'] !== $block->page_id) {
      abort(404);
    }

    $redirects = app(ContactFormRedirects::class);
    $sourceUrl = $redirects->baseUrl($payload['source_url'], $block->page?->publicUrl() ?: url('/'));
    $localeCode = $this->localeContext->forBlockSource($block, $payload['source_url']);

    if (! Schema::hasTable('wbcms_content_ratings')) {
      return redirect($sourceUrl)
        ->with('rating_success_block_id', $block->id)
        ->with('rating_success_message', $this->translator->public('engagement.ratings_unavailable', $localeCode));
    }

    $visitor = app(EngagementVisitor::class);
    $siteId = (int) $block->page?->site_id;
    $visitorHash = $visitor->visitorHash($request, $siteId, $block->id);
    $allowChange = (bool) $block->setting('allow_change', true);

    $existing = ContentRating::query()
      ->where('block_id', $block->id)
      ->where('visitor_hash', $visitorHash)
      ->first();

    if (! $existing || $allowChange) {
      ContentRating::query()->updateOrCreate(
        ['block_id' => $block->id, 'visitor_hash' => $visitorHash],
        [
          'site_id' => $siteId ?: null,
          'page_id' => $block->page_id,
          'rating_value' => $payload['rating_value'],
          'rating_max' => 5,
          'status' => 'active',
          'source_url' => $sourceUrl,
          'ip_hash' => $visitor->ipHash($request->ip()),
          'user_agent' => $request->userAgent(),
        ],
      );
    }

    return redirect($sourceUrl)
      ->with('rating_success_block_id', $block->id)
      ->with('rating_success_message', $this->translator->public('engagement.rating_submitted', $localeCode));
  }
}
