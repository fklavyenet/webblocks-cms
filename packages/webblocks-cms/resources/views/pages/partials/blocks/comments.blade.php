@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localeCode = $block->renderLocaleCode();
    $sortOrder = $block->setting('sort_order', 'newest') === 'oldest' ? 'oldest' : 'newest';
    $tableReady = \Illuminate\Support\Facades\Schema::hasTable('wbcms_comment_entries');
    $comments = collect();
    if ($tableReady) {
        $commentsQuery = \WebBlocks\Cms\Models\CommentEntry::query()
            ->where('block_id', $block->id)
            ->where('status', 'approved');
        $comments = ($sortOrder === 'oldest' ? $commentsQuery->oldest() : $commentsQuery->latest())->limit(25)->get();
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
    <div class="wb-card-body wb-stack wb-gap-4">
        @if ($success)
            <div class="wb-alert wb-alert-success">
                <div>{{ $success }}</div>
            </div>
        @endif

        @if (! $tableReady)
            <div class="wb-alert wb-alert-warning">
                <div>{{ $translator->get('blocks.comments.unavailable', $localeCode) }}</div>
            </div>
        @endif

        @if ($tableReady && $showApproved)
            <div class="wb-stack wb-gap-3">
                @forelse ($comments as $comment)
                    <article class="wb-stack wb-gap-2">
                        @if ($showAuthorName && $comment->author_name)
                            <strong>{{ $comment->author_name }}</strong>
                        @endif
                        <p>{{ $comment->body }}</p>
                    </article>
                @empty
                    <div class="wb-text-sm wb-text-muted">{{ $translator->get('blocks.comments.no_approved', $localeCode) }}</div>
                @endforelse
            </div>
        @endif

        @if ($formEnabled)
            @if ($hasTargetedErrors)
                <div class="wb-alert wb-alert-danger">
                    <div>
                        <div class="wb-alert-title">{{ $translator->get('blocks.comments.review_title', $localeCode) }}</div>
                        <div>{{ $errors->first() }}</div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('comment-entries.store') }}" class="wb-stack wb-gap-3">
                @csrf
                <input type="hidden" name="block_id" value="{{ $block->id }}">
                <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
                <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">
                <input type="hidden" name="submitted_at" value="{{ now()->timestamp }}">
                <input type="hidden" name="_form_check_name" value="{{ $formCheck->signedFieldName($block) }}">

                <div class="wb-form-check" inert aria-hidden="true">
                    <label for="comment-form-check-{{ $block->id }}">{{ $translator->get('blocks.comments.honeypot_label', $localeCode) }}</label>
                    <input id="comment-form-check-{{ $block->id }}" type="text" name="{{ $formCheckName }}" tabindex="-1" autocomplete="off">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="comment-author-{{ $block->id }}">{{ $translator->get('blocks.comments.name_label', $localeCode) }}</label>
                    <input id="comment-author-{{ $block->id }}" name="author_name" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('author_name') : '' }}" maxlength="80">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="comment-body-{{ $block->id }}">{{ $translator->get('blocks.comments.body_label', $localeCode) }}</label>
                    <textarea id="comment-body-{{ $block->id }}" name="body" class="wb-textarea" rows="5" maxlength="1200" required>{{ old('block_id') == $block->id ? old('body') : '' }}</textarea>
                </div>

                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted">{{ $translator->get('blocks.comments.helper', $localeCode) }}</span>
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $translator->get('blocks.comments.submit', $localeCode) }}</button>
                </div>
            </form>
        @elseif ($tableReady)
            <div class="wb-alert wb-alert-info">
                <div>{{ $translator->get('blocks.comments.closed', $localeCode) }}</div>
            </div>
        @endif
    </div>
</section>
