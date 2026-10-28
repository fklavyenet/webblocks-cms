@php
    $adminLocaleCode = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $t = fn ($key) => $translator->get('consent.'.$key, $adminLocaleCode);
    $sectionTitle = $t($section === 'general' ? 'general' : $section);
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $sectionTitle, 'heading' => $sectionTitle])
@section('content')
<div class="wb-stack-4">
    @include('webblocks-cms::admin.partials.page-header', ['title' => $sectionTitle, 'description' => $t('menu')])
    @include('webblocks-cms::admin.partials.flash')
    <form method="GET" class="wb-cluster wb-cluster-2">
        <label for="consent-site">{{ $t('site') }}</label>
        <select id="consent-site" name="site" class="wb-select">@foreach ($sites as $option)<option value="{{ $option->id }}" @selected($site->id === $option->id)>{{ $option->name }}</option>@endforeach</select>
        <button class="wb-btn wb-btn-secondary" type="submit">{{ $t('show') }}</button>
        <a href="{{ route('admin.consent.preview', ['site' => $site->id]) }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener">{{ $t('preview') }}</a>
    </form>
    @if (! $settings['enabled'])<div class="wb-alert wb-alert-info">{{ $t('disabled_help') }}</div>@endif
    @if ($section === 'general')
        <form method="POST" action="{{ route('admin.consent.settings.update', $site) }}" class="wb-card">
            @csrf @method('PUT')
            <div class="wb-card-body wb-stack-3">
                <input type="hidden" name="enabled" value="0">
                <label class="wb-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled'])) @disabled(! $canWrite)><span class="wb-switch-track"></span><span>{{ $t('enabled') }}</span></label>
                <div class="wb-field"><label for="consent-days">{{ $t('lifetime_days') }}</label><input id="consent-days" name="lifetime_days" type="number" min="1" max="730" class="wb-input" value="{{ old('lifetime_days', $settings['lifetime_days']) }}" @readonly(! $canWrite)></div>
                <div class="wb-field"><label for="consent-privacy">{{ $t('privacy_url') }}</label><input id="consent-privacy" name="privacy_url" type="text" class="wb-input" value="{{ old('privacy_url', $settings['privacy_url']) }}" @readonly(! $canWrite)></div>
                <div class="wb-field"><label for="consent-position">{{ $t('position') }}</label><select id="consent-position" name="position" class="wb-select" @disabled(! $canWrite)>@foreach (['bottom', 'top'] as $position)<option value="{{ $position }}" @selected(old('position', $settings['position']) === $position)>{{ $t($position) }}</option>@endforeach</select></div>
            </div>
            @if ($canWrite)<div class="wb-card-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ $t('save_changes') }}</button></div>@endif
        </form>
    @elseif ($section === 'services')
        <p>{{ $t('services_help') }}</p>
        <div class="wb-cluster wb-cluster-2">
            @if ($canWrite)<a href="{{ route('admin.consent.services', ['site' => $site->id, 'edit' => 'new']) }}" class="wb-btn wb-btn-primary">{{ $t('add_service') }}</a>@endif
        </div>
        <section class="wb-card">
            <div class="wb-card-body wb-table-wrap"><table class="wb-table">
                <thead><tr><th>{{ $t('name') }}</th><th>{{ $t('category') }}</th><th>{{ $t('enabled') }}</th><th>{{ $t('used') }}</th><th>{{ $t('edit') }}</th></tr></thead>
                <tbody>@foreach ($services as $handle => $service)
                    <tr><td>{{ $service['name'] }}<div class="wb-text-sm wb-text-muted">{{ $service['provider'] === 'custom' ? $t('custom') : $t('presets') }}</div></td><td>{{ $t($service['category']) }}</td><td>{{ $service['enabled'] ? '✓' : '—' }}</td><td>{{ $service['used'] ? $t('used') : $t('unused') }}</td><td><a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ route('admin.consent.services', ['site' => $site->id, 'edit' => $handle]) }}">{{ $t('edit') }}</a></td></tr>
                @endforeach</tbody>
            </table></div>
        </section>
        @include('webblocks-cms::admin.consent.service-form')
    @else
        @php($selectedLocale = old('locale', request('locale', session('consent_locale', $locales->first()?->code ?? 'en'))))
        @php($selectedLocale = $locales->contains('code', $selectedLocale) ? $selectedLocale : ($locales->first()?->code ?? 'en'))
        <form method="GET" class="wb-cluster wb-cluster-2">
            <input type="hidden" name="site" value="{{ $site->id }}">
            <label for="consent-text-locale">{{ $t('locale') }}</label><select id="consent-text-locale" name="locale" class="wb-select">@foreach ($locales as $locale)<option value="{{ $locale->code }}" @selected($selectedLocale === $locale->code)>{{ $locale->name }}</option>@endforeach</select><button class="wb-btn wb-btn-secondary">{{ $t('edit') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.consent.texts.update', $site) }}" class="wb-card">
            @csrf @method('PUT')
            <input type="hidden" name="locale" value="{{ $selectedLocale }}">
            <div class="wb-card-body wb-stack-3">
                <p>{{ $t('translation_help') }}</p>
                @foreach (['title', 'body', 'preferences_title', 'preferences_body'] as $key)
                    @php($stored = $translations->first(fn ($row) => $row->locale === $selectedLocale && $row->key === $key)?->value)
                    <div class="wb-field"><label for="consent-text-{{ $key }}">{{ $t($key) }}</label><textarea id="consent-text-{{ $key }}" class="wb-textarea" name="texts[{{ $key }}]" maxlength="2000" rows="3" placeholder="{{ $translator->get('consent.'.$key, $selectedLocale) }}" @readonly(! $canWrite)>{{ old('texts.'.$key, $stored) }}</textarea></div>
                @endforeach
                @foreach ($services as $handle => $service)
                    <section class="wb-card wb-card-muted" role="group" aria-labelledby="service-text-{{ $handle }}"><div class="wb-card-header"><strong id="service-text-{{ $handle }}">{{ $service['name'] }}</strong></div><div class="wb-card-body wb-stack-2">
                    @foreach (['name', 'description'] as $field)
                        @php($key = 'service.'.$handle.'.'.$field)
                        @php($stored = $translations->first(fn ($row) => $row->locale === $selectedLocale && $row->key === $key)?->value)
                        <label for="consent-text-{{ $handle }}-{{ $field }}">{{ $t($field) }}</label><textarea id="consent-text-{{ $handle }}-{{ $field }}" class="wb-textarea" name="texts[{{ $key }}]" maxlength="2000" rows="2" @readonly(! $canWrite)>{{ old('texts.'.$key, $stored) }}</textarea>
                    @endforeach
                    </div></section>
                @endforeach
            </div>
            @if ($canWrite)<div class="wb-card-footer"><button type="submit" class="wb-btn wb-btn-primary">{{ $t('save_changes') }}</button></div>@endif
        </form>
    @endif
</div>
@endsection
@push('scripts')
@php($consentAdminScript = public_path('cms/js/admin/consent.js'))
@if (is_file($consentAdminScript))<script src="{{ asset('cms/js/admin/consent.js') }}?v={{ filemtime($consentAdminScript) }}" defer></script>@endif
@endpush
