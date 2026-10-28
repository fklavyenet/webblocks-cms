@php
    $editorKey = $section === 'general' ? $editSetting : $editText;
    $editorTitle = $editorKey ? ($section === 'general' ? $t($editorKey) : $textRows[$editorKey]['label']) : null;
@endphp
@if ($editorKey)
<div class="wb-modal wb-modal-lg" id="consentEditorModal" role="dialog" aria-modal="true" aria-labelledby="consent-editor-title" data-wb-admin-autoload-overlay data-wb-admin-close-url="{{ $closeUrl }}" hidden>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="consent-editor-title">{{ $editorTitle }}</h2>
            <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $t('close_editor') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
        </div>
        <form method="POST" action="{{ route($section === 'general' ? 'admin.consent.settings.update' : 'admin.consent.texts.update', $site) }}" data-wb-admin-dirty-form>
            @csrf @method('PUT')
            <div class="wb-modal-body wb-stack wb-gap-3">
                @if ($errors->any())<div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>@endif
                @if ($section === 'texts')
                    @php($oldTexts = is_array(old('texts')) ? old('texts') : [])
                    <input type="hidden" name="_consent_text" value="{{ $editorKey }}">
                    <input type="hidden" name="locale" value="{{ $selectedLocale }}">
                    <p class="wb-text-sm wb-text-muted">{{ $t('translation_help') }}</p>
                    <div class="wb-field">
                        <label for="consent-editor-value" class="wb-label">{{ $editorTitle }}</label>
                        <textarea id="consent-editor-value" name="texts[{{ $editorKey }}]" class="wb-textarea" maxlength="2000" rows="6" @readonly(! $canWrite)>{{ array_key_exists($editorKey, $oldTexts) ? $oldTexts[$editorKey] : $textRows[$editorKey]['stored'] }}</textarea>
                    </div>
                    <div class="wb-stack wb-gap-1"><strong>{{ $t('current_value') }}</strong><p>{{ $textRows[$editorKey]['value'] ?: $t('not_set') }}</p></div>
                @else
                    <input type="hidden" name="_consent_setting" value="{{ $editorKey }}">
                    @if ($editorKey === 'enabled')
                        <input type="hidden" name="enabled" value="0">
                        <label class="wb-switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled'])) @disabled(! $canWrite)><span class="wb-switch-track"></span><span>{{ $t('enabled') }}</span></label>
                    @elseif ($editorKey === 'position')
                        <div class="wb-field"><label for="consent-editor-value" class="wb-label">{{ $editorTitle }}</label><select id="consent-editor-value" name="position" class="wb-select" @disabled(! $canWrite)>@foreach (['bottom', 'top'] as $position)<option value="{{ $position }}" @selected(old('position', $settings['position']) === $position)>{{ $t($position) }}</option>@endforeach</select></div>
                    @else
                        <div class="wb-field"><label for="consent-editor-value" class="wb-label">{{ $editorTitle }}</label><input id="consent-editor-value" name="{{ $editorKey }}" type="{{ $editorKey === 'lifetime_days' ? 'number' : 'text' }}" @if ($editorKey === 'lifetime_days') min="1" max="730" @else maxlength="2048" @endif class="wb-input" value="{{ old($editorKey, $settings[$editorKey]) }}" @readonly(! $canWrite)></div>
                    @endif
                @endif
            </div>
            <div class="wb-modal-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$closeUrl" :cancel-attributes="['data-wb-dismiss' => 'modal']" :show-submit="$canWrite" :submit-label="$t('save_changes')" />
            </div>
        </form>
    </div>
</div>
@endif
