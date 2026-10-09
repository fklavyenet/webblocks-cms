<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Actions\Engagement\StoreRating;
use WebBlocks\Cms\Http\Requests\ContentRatingRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
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

    app(StoreRating::class)->execute($block, $request, $payload['rating_value'], $sourceUrl);

    return redirect($sourceUrl)
      ->with('rating_success_block_id', $block->id)
      ->with('rating_success_message', $this->translator->public('engagement.rating_submitted', $localeCode));
  }
}
