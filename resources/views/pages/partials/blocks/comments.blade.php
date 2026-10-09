@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localeCode = $block->renderLocaleCode();
    $sortOrder = $block->setting('sort_order', 'newest') === 'oldest' ? 'oldest' : 'newest';
    $tableReady = \Illuminate\Support\Facades\Schema::hasTable('wbcms_comment_entries');
    $comments = collect();
    if ($tableReady) {
        $commentsQuery = app(\WebBlocks\Cms\Queries\PublicEngagementQuery::class)->comments($block);
        $comments = ($sortOrder === 'oldest' ? $commentsQuery->oldest() : $commentsQuery->latest())->orderBy('id', $sortOrder === 'oldest' ? 'asc' : 'desc')->paginate(25, ['*'], 'comments_page_'.$block->id)->withQueryString()->fragment('comments-'.$block->id);
    }
    $showApproved = (bool) $block->setting('show_approved', true);
    $showAuthorName = (bool) $block->setting('show_author_name', false);
    $formEnabled = $tableReady && (bool) $block->setting('form_enabled', true);
    $hasTargetedErrors = $errors->any() && (int) old('block_id') === $block->id;
    $success = session('comment_success_block_id') === $block->id ? session('comment_success_message') : null;
    $formCheck = app(\WebBlocks\Cms\Support\Contact\ContactFormCheck::class);
    $formCheckName = $formCheck->fieldName($block);
@endphp

<section class="wb-card wb-public-comments" id="comments-{{ $block->id }}" data-wb-public-block-type="comments">
    @if ($success || ! $tableReady)
        <div class="wb-card-body wb-stack wb-stack-2">
            @if ($success)
                <div class="wb-alert wb-alert-success" role="status">
                    <div>{{ $success }}</div>
                </div>
            @endif
            @if (! $tableReady)
                <div class="wb-alert wb-alert-warning">
                    <div>{{ $translator->get('blocks.comments.unavailable', $localeCode) }}</div>
                </div>
            @endif
        </div>
    @endif

    @if ($tableReady && $showApproved)
        <section class="wb-card-body wb-public-comments__list" aria-labelledby="comments-list-title-{{ $block->id }}">
            <header class="wb-cluster wb-cluster-2">
                <h3 class="wb-card-title" id="comments-list-title-{{ $block->id }}">{{ $translator->get('blocks.comments.list_title', $localeCode) }}</h3>
                <span class="wb-badge">{{ $comments->total() }}</span>
            </header>
            <div class="wb-public-comments__items">
                @forelse ($comments as $comment)
                    <article class="wb-public-comments__item">
                        <div class="wb-cluster wb-cluster-2 wb-public-comments__meta">
                            @if ($showAuthorName && $comment->author_name)
                                <strong>{{ $comment->author_name }}</strong>
                            @endif
                            <time class="wb-text-sm wb-text-muted" datetime="{{ $comment->created_at?->toIso8601String() }}">{{ $comment->created_at?->format('Y-m-d') }}</time>
                        </div>
                        <div class="wb-public-comments__body">
                            @foreach (preg_split('/\R/', $comment->body) as $line)<p>{{ $line }}</p>@endforeach
                        </div>
                    </article>
                @empty
                    <p class="wb-text-sm wb-text-muted">{{ $translator->get('blocks.comments.no_approved', $localeCode) }}</p>
                @endforelse
                @if ($comments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $comments->hasPages())
                    @include('webblocks-cms::pages.partials.blocks.engagement-pagination', ['paginator' => $comments])
                @endif
            </div>
        </section>
    @endif

    @if ($formEnabled)
        <section class="wb-card-body wb-public-comments__form-region" aria-labelledby="comments-form-title-{{ $block->id }}">
            <h3 class="wb-card-title" id="comments-form-title-{{ $block->id }}">{{ $translator->get('blocks.comments.form_title', $localeCode) }}</h3>
            @if ($hasTargetedErrors)
                <div class="wb-alert wb-alert-danger" role="alert">
                    <div>
                        <div class="wb-alert-title">{{ $translator->get('blocks.comments.review_title', $localeCode) }}</div>
                        <div>{{ $errors->first() }}</div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('comment-entries.store') }}" class="wb-stack wb-stack-3 wb-public-comments__form" aria-labelledby="comments-form-title-{{ $block->id }}">
                @csrf
                <input type="hidden" name="block_id" value="{{ $block->id }}">
                <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
                <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">
                <input type="hidden" name="_form_stamp" value="{{ $formCheck->issueStamp($block) }}">
                <input type="hidden" name="_form_check_name" value="{{ $formCheck->signedFieldName($block) }}">

                <div class="wb-sr-only" inert aria-hidden="true">
                    <label for="comment-form-check-{{ $block->id }}">{{ $translator->get('blocks.comments.honeypot_label', $localeCode) }}</label>
                    <input id="comment-form-check-{{ $block->id }}" type="text" name="{{ $formCheckName }}" tabindex="-1" autocomplete="off">
                </div>

                <div class="wb-stack wb-stack-1 wb-public-comments__name">
                    <label for="comment-author-{{ $block->id }}">{{ $translator->get('blocks.comments.name_label', $localeCode) }}</label>
                    <input id="comment-author-{{ $block->id }}" name="author_name" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('author_name') : '' }}" maxlength="80">
                </div>

                <div class="wb-stack wb-stack-1">
                    <label for="comment-body-{{ $block->id }}">{{ $translator->get('blocks.comments.body_label', $localeCode) }}</label>
                    <textarea id="comment-body-{{ $block->id }}" name="body" class="wb-textarea" rows="3" maxlength="1200" required>{{ old('block_id') == $block->id ? old('body') : '' }}</textarea>
                </div>

                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted">{{ $translator->get('blocks.comments.helper', $localeCode) }}</span>
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $translator->get('blocks.comments.submit', $localeCode) }}</button>
                </div>
            </form>
        </section>
    @elseif ($tableReady)
        <div class="wb-card-body wb-text-sm wb-text-muted">{{ $translator->get('blocks.comments.closed', $localeCode) }}</div>
    @endif
</section>
