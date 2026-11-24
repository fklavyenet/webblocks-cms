@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('site_form.'.$key, $adminLocale, $replace);
  $localizedPageTitle = $adminText('delete_site_title');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $localizedPageTitle,
        'description' => $adminText('delete_site_description'),
        'actions' => '<a href="'.route('admin.sites.edit', $site).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_site')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $site->name }}</strong>
                    <div class="wb-text-sm wb-text-muted"><code>{{ $site->handle }}</code></div>
                </div>

                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $site->name }}">
                        <tbody>
                            <tr><th scope="row">{{ rtrim($adminText('domain_label'), ': ') }}</th><td>{{ $site->canonicalDomain() ?: $adminText('not_set') }}</td></tr>
                            <tr><th scope="row">{{ rtrim($adminText('pages_label'), ': ') }}</th><td>{{ $report->count('pages') }}</td></tr>
                            <tr><th scope="row">{{ rtrim($adminText('blocks_label'), ': ') }}</th><td>{{ $report->count('blocks') }}</td></tr>
                            <tr><th scope="row">{{ rtrim($adminText('navigation_items_label'), ': ') }}</th><td>{{ $report->count('navigation_items') }}</td></tr>
                            <tr><th scope="row">{{ rtrim($adminText('locale_assignments_label'), ': ') }}</th><td>{{ $report->count('site_locales') }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">{{ $adminText('warning') }}</div>
                        <div>{{ $adminText('delete_site_warning') }}</div>
                    </div>
                </div>

                @if ($report->hasBlockers())
                    <div class="wb-alert wb-alert-danger">
                        <div>
                            <div class="wb-alert-title">{{ $adminText('delete_blocked') }}</div>
                            <div>{{ $report->firstBlocker() }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <form method="POST" action="{{ route('admin.sites.destroy', $site) }}" class="wb-stack wb-gap-0">
                @csrf
                @method('DELETE')

                <div class="wb-card-body wb-stack wb-gap-4">
                    <label class="wb-nowrap">
                        <input type="checkbox" name="confirm_delete" value="1" @checked(old('confirm_delete')) @disabled($report->hasBlockers())>
                        <span>{{ $adminText('delete_site_confirm') }}</span>
                    </label>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="route('admin.sites.index')"
                        :show-submit="false"
                        :delete-submit="true"
                        :delete-disabled="$report->hasBlockers()"
                    />
                </div>
            </form>
        </div>
    </div>
@endsection
