<div class="wb-action-group">
    @if (! ($detail ?? false))
        <a class="wb-action-btn wb-action-btn-view" href="{{ route('admin.engagement.comments.show', ['commentEntry' => $comment] + request()->query()) }}" title="{{ $adminText('view_comment') }}" aria-label="{{ $adminText('view_comment') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
    @endif
    @foreach (['approved' => ['approve', 'check'], 'rejected' => ['reject', 'x'], 'spam' => ['mark_spam', 'shield'], 'hidden' => ['hide', 'eye-off'], 'pending' => ['review_again', 'clock']] as $status => [$label, $icon])
        @if ($comment->status !== $status)
            <form method="POST" action="{{ route('admin.engagement.comments.status', $comment) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                <button class="wb-action-btn" type="submit" title="{{ $adminText($label) }}" aria-label="{{ $adminText($label) }}"><i class="wb-icon wb-icon-{{ $icon }}" aria-hidden="true"></i></button>
            </form>
        @endif
    @endforeach
</div>
