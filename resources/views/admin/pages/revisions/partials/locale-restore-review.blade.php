<div class="wb-card">
    <div class="wb-card-header"><strong>{{ $text('locale_restore.title') }}</strong></div>
    <div class="wb-card-body wb-stack wb-gap-3">
        <form method="GET" action="{{ route('admin.pages.revisions.show', [$page, $revision]) }}" class="wb-stack wb-gap-3">
            <label for="restore_scope">{{ $text('locale_restore.scope') }}</label>
            <select class="wb-select" id="restore_scope" name="restore_scope">
                @foreach (['full_page', 'page_fields', 'page_locale'] as $scopeOption)
                    <option value="{{ $scopeOption }}" @selected($restoreScope === $scopeOption)>{{ $text('locale_restore.scopes.'.$scopeOption) }}</option>
                @endforeach
            </select>
            <label for="restore_locale_id">{{ $text('locale_restore.language') }}</label>
            <select class="wb-select" id="restore_locale_id" name="locale_id">
                @foreach ($restoreLocales as $restoreLocale)
                    <option value="{{ $restoreLocale->id }}" @selected((int) $restoreLocaleId === (int) $restoreLocale->id)>{{ $restoreLocale->name }} ({{ $restoreLocale->code }})</option>
                @endforeach
            </select>
            <p class="wb-text-sm wb-text-muted">{{ $text('locale_restore.language_help') }}</p>
            <button type="submit" class="wb-btn wb-btn-secondary">{{ $text('locale_restore.review') }}</button>
        </form>
        @if ($localePlan)
            @if (isset($localePlan['proposed_url']))<p>{{ $text('locale_restore.proposed_url') }}: {{ $localePlan['proposed_url'] }}</p>@endif
            <p>{{ $text('locale_restore.preserved') }}</p>
            <p>{{ $text('locale_restore.preview_languages') }}</p>
            @if ($restoreScope === 'page_locale')
                <div class="wb-alert wb-alert-info">{{ $text('locale_restore.excluded') }}</div>
            @endif
            @foreach ($localePlan['issues'] as $issue)
                <div class="wb-alert wb-alert-danger" role="alert">
                    {{ $text('locale_restore.errors.'.$issue['code']) }}
                    @if (isset($issue['entity']))
                        {{ $text('locale_restore.entities.'.$issue['entity']) }} #{{ $issue['owner_id'] }}:
                        @foreach ($issue['fields'] as $affectedField){{ $text('locale_restore.fields.'.$affectedField) }}{{ $loop->last ? '' : ', ' }}@endforeach
                    @endif
                    @if (isset($issue['locale_ids']))
                        {{ $restoreLocales->whereIn('id', $issue['locale_ids'])->pluck('code')->implode(', ') }}
                    @endif
                </div>
            @endforeach
            @if ($localePlan['changes'] === [])
                <p>{{ $text('locale_restore.no_changes') }}</p>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        <thead><tr><th>{{ $text('locale_restore.item') }}</th><th>{{ $text('field') }}</th><th>{{ $text('current_page') }}</th><th>{{ $text('selected_version') }}</th></tr></thead>
                        <tbody>
                            @foreach ($localePlan['changes'] as $change)
                                @foreach ($change['after'] as $field => $value)
                                    @if (($change['before'][$field] ?? null) !== $value || $change['before'] === null)
                                        <tr>
                                            <td>{{ $text('locale_restore.entities.'.$change['entity']) }} {{ $change['entity'] === 'page' ? '' : '#'.$change['owner_id'] }}</td>
                                            <td>{{ $text('locale_restore.fields.'.$field) }}</td>
                                            <td>{{ is_array($change['before'][$field] ?? null) ? json_encode($change['before'][$field], JSON_UNESCAPED_UNICODE) : ($change['before'][$field] ?? '—') }}</td>
                                            <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?? '—') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
