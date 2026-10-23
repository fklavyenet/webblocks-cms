@php
    $retentionLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $retentionTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $retentionText = static fn (string $key) => $retentionTranslator->get('backup-retention.'.$key, $retentionLocale);
    $retentionPrefix = 'backup-retention-'.($retentionBackup?->id ?? 'create');
@endphp
<div class="wb-stack wb-gap-3">
    <input type="hidden" name="is_protected" value="0">
    <label class="wb-check"><input name="is_protected" type="checkbox" value="1" @checked(old('is_protected', $retentionBackup?->is_protected ?? false))><span>{{ $retentionText('protect') }}</span></label>
    <div class="wb-field wb-stack-2">
        <label for="{{ $retentionPrefix }}-mode">{{ $retentionText('title') }}</label>
        <select class="wb-select" id="{{ $retentionPrefix }}-mode" name="retention_mode" required>
            @foreach (['policy', 'never', 'expires'] as $mode)
                <option value="{{ $mode }}" @selected(old('retention_mode', $retentionBackup?->retention_mode ?? 'policy') === $mode)>{{ $retentionText($mode) }}</option>
            @endforeach
        </select>
    </div>
    <div class="wb-field wb-stack-2">
        <label for="{{ $retentionPrefix }}-expires">{{ $retentionText('expires_at') }}</label>
        <input class="wb-input" id="{{ $retentionPrefix }}-expires" name="expires_at" type="datetime-local" value="{{ old('expires_at', $retentionBackup?->expires_at?->format('Y-m-d\TH:i')) }}" aria-describedby="{{ $retentionPrefix }}-help">
    </div>
    <p class="wb-text-sm wb-text-muted" id="{{ $retentionPrefix }}-help">{{ $retentionText('expiry_help') }}</p>
</div>
