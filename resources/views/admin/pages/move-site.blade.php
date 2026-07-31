@php
    $pageMoveSiteLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pageMoveSiteText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_move_site.'.$key, $pageMoveSiteLocale, $replace);
    $pageTitle = $pageMoveSiteText('title');
    $siteName = $page->site?->name ?? $pageMoveSiteText('site_fallback');
    $backUrl = route('admin.pages.edit', $page);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $pageMoveSiteText('description'),
        'actions' => '<a href="'.$backUrl.'" class="wb-btn wb-btn-secondary">'.e($pageMoveSiteText('back_to_page')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $page->title }}</strong>
                    <div class="wb-text-sm wb-text-muted">{{ $pageMoveSiteText('current_site', ['site' => $siteName]) }}</div>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>{{ $pageMoveSiteText('current_public_path') }}</strong> {{ $page->publicPath() ?? $pageMoveSiteText('not_routable') }}</div>
                    <div><strong>{{ $pageMoveSiteText('workflow') }}</strong> {{ $page->workflowLabel() }}</div>
                    <div><strong>{{ $pageMoveSiteText('translations') }}</strong> {{ $page->translations->pluck('locale.code')->filter()->implode(', ') ?: $pageMoveSiteText('none') }}</div>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">{{ $pageMoveSiteText('warning') }}</div>
                        <div>{{ $pageMoveSiteText('warning_text') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.pages.move-site.store', $page) }}" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-field">
                        <label for="target_site_id">{{ $pageMoveSiteText('target_site') }}</label>
                        <select id="target_site_id" name="target_site_id" class="wb-select" required>
                            <option value="">{{ $pageMoveSiteText('choose_site') }}</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('target_site_id') === (int) $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                        @error('target_site_id')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="$backUrl"
                        :submit-label="$pageMoveSiteText('move_submit')"
                    />
                </form>
            </div>
        </div>
    </div>
@endsection
