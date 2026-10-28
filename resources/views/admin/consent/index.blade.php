@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $t = fn ($key) => $translator->get('consent.'.$key, $adminLocaleCode);
    $sectionTitle = $t($section === 'general' ? 'general' : $section);
    $sectionRoute = 'admin.consent.'.($section === 'general' ? 'settings' : $section);
    $consentContext = ['site' => $site->id] + ($section === 'texts' ? ['locale' => $selectedLocale] : []);
    $closeUrl = route($sectionRoute, $consentContext);
    $canEditIcon = $canWrite ? 'pencil' : 'eye';
    $actionLabel = $t($canWrite ? 'edit' : 'view');
    $rowCount = $section === 'general' ? 4 : ($section === 'services' ? count($services) : count($textRows));
    $filterSelects = [[
        'id' => 'consent-site', 'name' => 'site', 'label' => $t('site'),
        'selected' => $site->id, 'placeholder' => null,
        'options' => $sites->pluck('name', 'id')->all(),
    ]];
    if ($section === 'texts') {
        $filterSelects[] = [
            'id' => 'consent-text-locale', 'name' => 'locale', 'label' => $t('locale'),
            'selected' => $selectedLocale, 'placeholder' => null,
            'options' => $locales->pluck('name', 'code')->all(),
        ];
    }
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $sectionTitle, 'heading' => $sectionTitle])
@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $sectionTitle, 'description' => $t('menu')])
    @include('webblocks-cms::admin.partials.flash')
    <div class="wb-card wb-card-muted" data-consent-filter-card>
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route($sectionRoute), 'selects' => $filterSelects,
                'showReset' => (int) $site->id !== (int) $resetFilters['site'] || ($section === 'texts' && $selectedLocale !== $resetFilters['locale']),
                'resetUrl' => route($sectionRoute, $section === 'texts' ? $resetFilters : ['site' => $resetFilters['site']]),
            ])
        </div>
    </div>
    <section class="wb-card" aria-labelledby="consent-list-title" data-consent-list-card data-consent-section="{{ $section }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <h2 class="wb-card-title" id="consent-list-title">{{ $sectionTitle }}</h2>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $rowCount }}</span>
            </div>
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <a href="{{ route('admin.consent.preview', $consentContext) }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i><span>{{ $t('preview') }}</span></a>
                @if ($section === 'services' && $canWrite)
                    <a href="{{ route($sectionRoute, $consentContext + ['edit' => 'new']) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog">{{ $t('add_service') }}</a>
                @endif
            </div>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            @if (! $settings['enabled'])<div class="wb-alert wb-alert-info">{{ $t('disabled_help') }}</div>@endif
            @if ($section !== 'general')<p class="wb-text-sm wb-text-muted">{{ $t($section === 'services' ? 'services_help' : 'translation_help') }}</p>@endif
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    @include('webblocks-cms::admin.consent.'.$section.'-list')
                </table>
            </div>
        </div>
    </section>
@endsection
@push('overlays')
    @if ($section === 'services')
        @include('webblocks-cms::admin.consent.service-form')
    @else
        @include('webblocks-cms::admin.consent.editor')
    @endif
@endpush
@push('scripts')
    @php($consentAdminScript = public_path('cms/js/admin/consent.js'))
    @if (is_file($consentAdminScript))<script src="{{ asset('cms/js/admin/consent.js') }}?v={{ filemtime($consentAdminScript) }}" defer></script>@endif
@endpush
