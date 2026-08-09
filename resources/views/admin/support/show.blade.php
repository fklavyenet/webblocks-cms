@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);

    $statusBadges = [
        'new' => 'wb-badge-primary',
        'triaged' => 'wb-badge-warning',
        'converted' => 'wb-badge-success',
        'rejected' => 'wb-badge-danger',
        'closed' => 'wb-badge',
    ];
    $statusLabel = static fn (string $status): string => $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode) === 'support.statuses.'.$status
        ? $status
        : $adminTranslator->admin('support.statuses.'.$status, $adminLocaleCode);
    $typeLabel = static fn (string $type): string => $adminTranslator->admin('support.types.'.$type, $adminLocaleCode) === 'support.types.'.$type
        ? $type
        : $adminTranslator->admin('support.types.'.$type, $adminLocaleCode);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $ticket['title'], 'heading' => $ticket['title']])

@section('content')
    <div class="wb-stack wb-gap-4">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $ticket['title'],
            'description' => '#'.$ticket['number'].' · '.$typeLabel($ticket['type']).' · '.\Illuminate\Support\Carbon::parse($ticket['created_at'])->isoFormat('LLL'),
        ])

        @include('webblocks-cms::admin.partials.flash')

        <div class="wb-cluster wb-cluster-2">
            <span class="wb-badge {{ $statusBadges[$ticket['status']] ?? 'wb-badge' }}">{{ $statusLabel($ticket['status']) }}</span>
            <a class="wb-btn wb-btn-secondary wb-ms-auto" href="{{ route('admin.support.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i>{{ $adminText('support.back') }}
            </a>
        </div>

        <section class="wb-card">
            <div class="wb-card-header">
                <h2 class="wb-card-title">{{ $adminText('support.description') }}</h2>
            </div>
            <div class="wb-card-body">
                <div class="wb-prose">{!! nl2br(e($ticket['body'])) !!}</div>
            </div>
        </section>

        <section class="wb-card">
            <div class="wb-card-header">
                <h2 class="wb-card-title">{{ $adminText('support.conversation') }}</h2>
            </div>
            <div class="wb-card-body wb-stack wb-gap-4">
                @forelse ($comments as $comment)
                    <article class="wb-stack wb-gap-1">
                        <p class="wb-text-sm wb-text-muted">
                            <strong>{{ $comment['author_name'] }}</strong>
                            @if ($comment['author_type'] === 'admin')
                                <span class="wb-badge wb-badge-primary">{{ $adminText('support.author_team') }}</span>
                            @endif
                            &middot; {{ \Illuminate\Support\Carbon::parse($comment['created_at'])->isoFormat('LLL') }}
                        </p>
                        <div class="wb-prose">{!! nl2br(e($comment['body'])) !!}</div>
                    </article>
                @empty
                    <p class="wb-text-muted">{{ $adminText('support.no_replies') }}</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.support.comment', ['ticket' => $ticket['id']]) }}">
                @csrf
                <div class="wb-card-body">
                    <div class="wb-field">
                        <label class="wb-label" for="supportReply">{{ $adminText('support.reply_label') }}</label>
                        <textarea id="supportReply" class="wb-textarea" name="body" rows="4" maxlength="20000" required>{{ old('body') }}</textarea>
                        @error('body')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="wb-card-footer">
                    <button class="wb-btn wb-btn-primary" type="submit">{{ $adminText('support.reply_submit') }}</button>
                </div>
            </form>
        </section>
    </div>
@endsection
