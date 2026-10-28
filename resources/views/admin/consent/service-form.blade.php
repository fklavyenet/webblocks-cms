@php
    $editHandle = old('_consent_service', request('edit'));
    $editHandle = is_string($editHandle) ? $editHandle : null;
    $editing = ($canWrite && $editHandle === 'new') || isset($services[$editHandle]);
    $service = $services[$editHandle] ?? ['handle' => '', 'provider' => 'custom', 'name' => '', 'description' => '', 'category' => 'preferences', 'script_url' => '', 'privacy_url' => '', 'enabled' => true, 'cookies' => []];
    $closeUrl = route('admin.consent.services', ['site' => $site->id]);
    $serviceTab = old('_consent_tab', 'info');
@endphp
@if ($editing)
<div class="wb-modal wb-modal-lg" id="consentServiceModal" role="dialog" aria-modal="true" aria-labelledby="consent-service-title" data-wb-admin-autoload-overlay data-wb-admin-close-url="{{ $closeUrl }}" hidden>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header"><h2 class="wb-modal-title" id="consent-service-title">{{ $service['name'] ?: $t('add_service') }}</h2><a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $t('close_editor') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a></div>
        <form id="consent-service-form" method="POST" action="{{ route('admin.consent.services.update', ['site' => $site->id, 'service' => $service['handle'] ?: '__handle__']) }}" data-consent-service-form data-wb-admin-dirty-form>
            @csrf @method('PUT')
            <input type="hidden" name="_consent_service" value="{{ $editHandle }}">
            <div class="wb-modal-body wb-stack-3">
                @if ($errors->any())<div class="wb-alert wb-alert-danger">{{ $errors->first() }}</div>@endif
                <div class="wb-tabs" data-wb-tabs data-wb-tabs-field="[data-consent-service-tab]">
            <input type="hidden" name="_consent_tab" value="{{ $serviceTab }}" data-consent-service-tab>

                    <div class="wb-tabs-nav" role="tablist" aria-label="{{ $t('services') }}">
                        @foreach (['info' => 'information', 'sources' => 'script_url', 'cookies' => 'cookies'] as $tab => $label)<button type="button" class="wb-tabs-btn {{ $serviceTab === 'consent-service-'.$tab || ($serviceTab === 'info' && $tab === 'info') ? 'is-active' : '' }}" data-wb-tab="consent-service-{{ $tab }}">{{ $t($label) }}</button>@endforeach
                    </div>
                    <div class="wb-tabs-panels">
                        <div id="consent-service-info" class="wb-tabs-panel {{ in_array($serviceTab, ['info', 'consent-service-info'], true) ? 'is-active' : '' }} wb-stack-3">
                            @if (! $service['handle'])<label for="consent-service-handle">{{ $t('handle') }}</label><input id="consent-service-handle" class="wb-input" data-consent-handle pattern="[a-z][a-z0-9-]{0,39}" required maxlength="40" value="{{ old('_new_handle') }}" name="_new_handle">@endif
                            <label for="consent-service-name">{{ $t('name') }}</label><input id="consent-service-name" class="wb-input" name="name" value="{{ old('name', $service['name']) }}" maxlength="160" required @readonly(! $canWrite)>
                            <label for="consent-service-description">{{ $t('description') }}</label><textarea id="consent-service-description" class="wb-textarea" name="description" maxlength="2000" @readonly(! $canWrite)>{{ old('description', $service['description']) }}</textarea>
                            <label for="consent-service-category">{{ $t('category') }}</label><select id="consent-service-category" class="wb-select" name="category" @disabled(! $canWrite)>@foreach (\WebBlocks\Cms\Support\Consent\ConsentPresets::CATEGORIES as $category)<option value="{{ $category }}" @selected(old('category', $service['category']) === $category)>{{ $t($category) }}</option>@endforeach</select>
                            <label for="consent-service-privacy">{{ $t('privacy_url') }}</label><input type="url" id="consent-service-privacy" class="wb-input" name="privacy_url" maxlength="2048" value="{{ old('privacy_url', $service['privacy_url']) }}" @readonly(! $canWrite)>
                            <input type="hidden" name="enabled" value="0"><label class="wb-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $service['enabled'])) @disabled(! $canWrite)><span class="wb-switch-track"></span><span>{{ $t('enabled') }}</span></label>
                        </div>
                        <div id="consent-service-sources" class="wb-tabs-panel {{ $serviceTab === 'consent-service-sources' ? 'is-active' : '' }} wb-stack-3">
                            <p>{{ $t('source_help') }}</p>
                            @if ($canCode && $canWrite && $service['provider'] === 'custom')<label for="consent-service-script">{{ $t('script_url') }}</label><input type="url" id="consent-service-script" class="wb-input" name="script_url" maxlength="2048" value="{{ old('script_url', $service['script_url']) }}">@endif
                        </div>
                        <div id="consent-service-cookies" class="wb-tabs-panel {{ $serviceTab === 'consent-service-cookies' ? 'is-active' : '' }} wb-stack-3">
                            <p>{{ $t('cookie_help') }}</p>
                            <div data-consent-cookie-rows>
                            @foreach (old('cookies', $service['cookies']) as $i => $cookie)
                                @include('webblocks-cms::admin.consent.cookie-row')
                            @endforeach
                            </div>
                            @if ($canWrite)<button type="button" class="wb-btn wb-btn-secondary" data-consent-cookie-add>{{ $t('add_cookie') }}</button><template data-consent-cookie-template>@include('webblocks-cms::admin.consent.cookie-row', ['i' => '__index__', 'cookie' => ['name' => '', 'path' => '/', 'duration' => '']])</template>@endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="wb-modal-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$closeUrl" :cancel-attributes="['data-wb-dismiss' => 'modal']" :show-submit="$canWrite" :submit-label="$t('save_changes')" :delete-href="$canWrite && $service['handle'] ? '#consentDeleteModal' : null" :delete-label="$t('delete')" :delete-attributes="['data-wb-toggle' => 'modal', 'data-wb-target' => '#consentDeleteModal']" />
            </div>
        </form>
    </div>
</div>
@if ($service['handle'] && $canWrite)
<div class="wb-modal" id="consentDeleteModal" role="dialog" aria-modal="true" aria-labelledby="consent-delete-title" hidden><div class="wb-modal-dialog"><div class="wb-modal-header"><h2 id="consent-delete-title" class="wb-modal-title">{{ $t('delete') }}</h2></div><div class="wb-modal-body">{{ $t('delete_confirm') }}</div><form method="POST" action="{{ route('admin.consent.services.destroy', ['site' => $site->id, 'service' => $service['handle']]) }}" class="wb-modal-footer">@csrf @method('DELETE')<button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $t('close_editor') }}</button><button class="wb-btn wb-btn-danger" type="submit">{{ $t('delete') }}</button></form></div></div>
@endif
@endif
