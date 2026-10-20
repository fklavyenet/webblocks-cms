@php($editorRecovery = $recoveryPage ? app(\WebBlocks\Cms\Support\EditorRecovery\EditorRecovery::class)->form(request()->user(), $recoveryPage, $recoveryBlock ?? null, $recoveryLocale ?? null) : null)
@if ($editorRecovery)
    <div class="wb-card wb-card-muted" data-wb-editor-recovery
         data-endpoint="{{ $editorRecovery['endpoint'] }}" data-identity="{{ $editorRecovery['identity'] }}"
         data-locale="{{ $editorRecovery['locale'] }}" data-block-id="{{ $editorRecovery['block_id'] }}"
         data-roots="{{ $editorRecovery['roots'] }}"
         @foreach (['ready', 'pending', 'protected', 'failed', 'available', 'paused', 'conflict', 'discarded'] as $message)
             data-message-{{ $message }}="{{ __('webblocks-cms::recovery.'.$message) }}"
         @endforeach>
        <input type="hidden" name="_editor_recovery_session" value="{{ old('_editor_recovery_session') }}">
        <input type="hidden" name="_editor_recovery_base" value="{{ $editorRecovery['base'] }}">
        <div class="wb-card-body wb-stack wb-gap-2">
            <strong>{{ __('webblocks-cms::recovery.title') }}</strong>
            <span class="wb-text-sm" data-recovery-status role="status" aria-live="polite">{{ __('webblocks-cms::recovery.ready') }}</span>
            <span class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::recovery.help') }}</span>
            <div class="wb-stack wb-gap-2" data-recovery-choice hidden>
                <p class="wb-alert wb-alert-warning" data-recovery-conflict hidden>{{ __('webblocks-cms::recovery.conflict') }}</p>
                <label class="wb-label" data-recovery-review hidden><input type="checkbox" data-recovery-reviewed> {{ __('webblocks-cms::recovery.reviewed') }}</label>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <button type="button" class="wb-btn wb-btn-primary" data-recovery-restore>{{ __('webblocks-cms::recovery.restore') }}</button>
                    <button type="button" class="wb-btn wb-btn-danger" data-recovery-discard>{{ __('webblocks-cms::recovery.discard') }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-recovery-continue>{{ __('webblocks-cms::recovery.continue') }}</button>
                </div>
            </div>
        </div>
    </div>
@endif
