@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('engagement.'.$key, $adminLocaleCode, $replace);
    $returnQuery = $returnQuery ?? http_build_query(request()->query());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('view_comment'), 'heading' => $adminText('view_comment')])
@section('content')
<div class="wb-stack wb-gap-4">
    @include('webblocks-cms::admin.partials.page-header', ['title' => $adminText('view_comment'), 'description' => $adminText('comments_description')])
    @include('webblocks-cms::admin.partials.flash')
    <a class="wb-btn wb-btn-secondary" href="{{ route('admin.engagement.comments.index').($returnQuery !== '' ? '?'.$returnQuery : '') }}">{{ $adminText('view_comments') }}</a>
    <section class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between"><strong>{{ $comment->author_name ?: $adminText('anonymous') }}</strong><span class="wb-status-pill {{ $comment->statusClass() }}">{{ $adminText('status_'.$comment->status) }}</span></div>
        <div class="wb-card-body wb-stack wb-gap-3">
            @foreach (preg_split('/\R/', $comment->body) as $line)<p>{{ $line }}</p>@endforeach
            <p>{{ $adminText('source') }}: {{ $comment->sourceLabel() }} · {{ $comment->site?->name }}</p>
            <p>{{ $adminText('submitted') }}: {{ $comment->created_at?->format('Y-m-d H:i') }}</p>
            <p>{{ $adminText('spam') }}: {{ $comment->spam_score }}</p>
            @foreach ($comment->spamReasonLabels() as $reason)<p class="wb-text-sm wb-text-muted">{{ $reason }}</p>@endforeach
            <p class="wb-text-sm wb-text-muted">{{ $adminText('spam_help') }}</p>
            <p class="wb-text-sm wb-text-muted">{{ $comment->status === 'approved' ? $adminText('public_approved') : $adminText('public_not_approved') }}</p>
            @if (! $comment->block_id)<p class="wb-text-sm wb-text-muted">{{ $adminText('previous_block') }}</p>@endif
        </div>
        <div class="wb-card-footer">@include('webblocks-cms::admin.engagement.comment-actions', ['detail' => true])</div>
    </section>
</div>
@endsection
