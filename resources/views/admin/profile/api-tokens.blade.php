@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $locale = app(AdminLocaleResolver::class)->locale();
    $translator = app(CmsTranslator::class);
    $text = static fn (string $key, array $replace = []) => $translator->admin('profile.api_tokens.'.$key, $locale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $text('title'),
        'description' => $text('description'),
        'actions' => '<a href="'.e(route('admin.profile.edit')).'" class="wb-btn wb-btn-secondary">'.e($text('back')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($createdToken)
        <div class="wb-alert wb-alert-warning wb-mb-4">
            <strong>{{ $text('copy_now') }}</strong>
            <code>{{ $createdToken }}</code>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><div><h2 class="wb-card-title">{{ $text('create_title') }}</h2><p class="wb-card-description">{{ $text('create_description') }}</p></div></div>
            <div class="wb-card-body">
                <form id="personal-api-token-form" method="POST" action="{{ route('admin.profile.api-tokens.store') }}" class="wb-stack wb-gap-4">
                    @csrf
                    <div class="wb-field wb-stack-2">
                        <label for="token_name">{{ $text('name') }}</label>
                        <input id="token_name" class="wb-input" name="name" value="{{ old('name') }}" required maxlength="120">
                    </div>
                    <fieldset class="wb-field wb-stack-2">
                        <legend>{{ $text('sites') }}</legend>
                        @foreach ($sites as $site)
                            <label class="wb-check"><input type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, old('site_ids', $sites->pluck('id')->all())))> <span>{{ $site->name }}</span></label>
                        @endforeach
                    </fieldset>
                    <fieldset class="wb-field wb-stack-2">
                        <legend>{{ $text('permissions') }}</legend>
                        @foreach ($capabilities as $capability)
                            <label class="wb-check"><input type="checkbox" name="capabilities[]" value="{{ $capability }}" @checked(in_array($capability, old('capabilities', ['content.read', 'content.validate', 'content.apply', 'media.read', 'media.upload'])))> <span>{{ $capabilityLabels[$capability] ?? $capability }}</span></label>
                        @endforeach
                    </fieldset>
                    <div class="wb-field wb-stack-2"><label for="expires_in_days">{{ $text('expires') }}</label><select id="expires_in_days" name="expires_in_days" class="wb-select"><option value="30">30 {{ $text('days') }}</option><option value="90" selected>90 {{ $text('days') }}</option><option value="365">365 {{ $text('days') }}</option></select></div>
                </form>
            </div>
            <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" form="personal-api-token-form">{{ $text('create') }}</button></div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><div><h2 class="wb-card-title">{{ $text('existing_title') }}</h2><p class="wb-card-description">{{ $text('api_base', ['url' => $apiBaseUrl]) }}</p></div></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                @forelse ($tokens as $token)
                    <div class="wb-card"><div class="wb-card-body"><div class="wb-stack-2"><strong>{{ $token->name }}</strong><span class="wb-text-sm wb-text-muted">{{ $token->token_preview }} · {{ $token->expires_at?->format('Y-m-d') }}</span><span class="wb-text-sm">{{ implode(', ', $token->capabilities ?? []) }}</span><div class="wb-action-group">@if (! $token->isRevoked())<button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-toggle="modal" data-wb-target="#revoke-personal-api-token-{{ $token->id }}">{{ $text('revoke') }}</button>@endif<button type="button" class="wb-btn wb-btn-danger wb-btn-sm" data-wb-toggle="modal" data-wb-target="#delete-personal-api-token-{{ $token->id }}">{{ $text('delete') }}</button></div></div></div></div>
                @empty
                    <p class="wb-text-muted">{{ $text('empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @foreach ($tokens as $token)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'revoke-personal-api-token-'.$token->id, 'title' => $text('revoke'), 'description' => $text('revoke_confirm'), 'action' => route('admin.profile.api-tokens.revoke', $token), 'method' => 'POST', 'submitLabel' => $text('revoke')])
            <p><strong>{{ $token->name }}</strong></p>
        @endcomponent
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', ['id' => 'delete-personal-api-token-'.$token->id, 'title' => $text('delete'), 'description' => $text('delete_confirm'), 'action' => route('admin.profile.api-tokens.destroy', $token), 'method' => 'DELETE', 'submitLabel' => $text('delete')])
            <p><strong>{{ $token->name }}</strong></p>
        @endcomponent
    @endforeach
@endpush
