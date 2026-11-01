@if (($candidate->restore_scope ?? 'full_page') !== 'full_page')
    <p>{{ $text('locale_restore.apply_help', ['language' => $localePlan['locale_code'] ?? '', 'scope' => $text('locale_restore.scopes.'.$candidate->restore_scope)]) }}</p>
    <p>{{ $text('locale_restore.preserved') }}</p>
    <label class="wb-cluster wb-cluster-2" for="confirm_locale_restore">
        <input id="confirm_locale_restore" name="confirm_locale_restore" type="checkbox" value="1" required>
        <span>{{ $text('locale_restore.confirm', ['language' => $localePlan['locale_code'] ?? '']) }}</span>
    </label>
@else
    <p>{{ $text('apply_candidate_warning', ['id' => $revision->id]) }}</p>
    @if ($inspection['translation_removals'] !== [])
        <div class="wb-alert wb-alert-warning">
            <div>
                <strong>{{ $text('translation_removal_title') }}</strong>
                <ul>
                    @foreach ($inspection['translation_removals'] as $translation)
                        <li>{{ $translation['name'] }} ({{ $translation['code'] }}) — {{ $translation['path'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @foreach ($inspection['translation_removals'] as $translation)
            <input type="hidden" name="translation_removal_locale_ids[]" value="{{ $translation['locale_id'] }}">
        @endforeach
        <label class="wb-cluster wb-cluster-2" for="confirm_translation_removal">
            <input id="confirm_translation_removal" name="confirm_translation_removal" type="checkbox" value="1" required>
            <span>{{ $text('confirm_translation_removal') }}</span>
        </label>
    @endif
@endif
<label class="wb-cluster wb-cluster-2" for="confirm_apply_candidate">
    <input id="confirm_apply_candidate" name="confirm_apply_candidate" type="checkbox" value="1" required>
    <span>{{ ($candidate->restore_scope ?? 'full_page') === 'full_page' ? $text('confirm_candidate_previewed') : $text('locale_restore.preview_confirm') }}</span>
</label>
