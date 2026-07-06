@php
    $flashErrors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('flash.'.$key, $adminLocale, $replace);
@endphp

@if (session('status'))
    <div class="wb-alert wb-alert-success">
        <div>
            <div class="wb-alert-title">{{ $adminText('success') }}</div>
            <div>
                {{ session('status') }}

                @if (session('status_action'))
                    @php($statusAction = session('status_action'))
                    <a href="{{ $statusAction['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $statusAction['label'] ?? $adminText('view_page') }}</a>
                @endif
            </div>
        </div>
    </div>
@endif

@if ($flashErrors->has('system_update'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('update_failed') }}</div>
            <div>{{ $flashErrors->first('system_update') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->has('system_backup'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('backup_failed') }}</div>
            <div>{{ $flashErrors->first('system_backup') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->has('site_delete'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('delete_failed') }}</div>
            <div>{{ $flashErrors->first('site_delete') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->has('locale_lifecycle'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('locale_action_blocked') }}</div>
            <div>{{ $flashErrors->first('locale_lifecycle') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->has('user_lifecycle'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('user_action_blocked') }}</div>
            <div>{{ $flashErrors->first('user_lifecycle') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->has('system_restore'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('restore_failed') }}</div>
            <div>{{ $flashErrors->first('system_restore') }}</div>
        </div>
    </div>
@endif

@if ($flashErrors->any() && ! $flashErrors->has('system_update') && ! $flashErrors->has('system_backup') && ! $flashErrors->has('site_delete') && ! $flashErrors->has('locale_lifecycle') && ! $flashErrors->has('user_lifecycle') && ! $flashErrors->has('system_restore'))
    <div class="wb-alert wb-alert-danger">
        <div>
            <div class="wb-alert-title">{{ $adminText('validation_error') }}</div>
            <div>{{ $flashErrors->getBag('default')->first() }}</div>
        </div>
    </div>
@endif
