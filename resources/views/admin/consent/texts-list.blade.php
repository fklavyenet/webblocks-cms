<thead><tr><th>{{ $t('text_name') }}</th><th>{{ $t('value') }}</th><th>{{ $t('translation_source') }}</th><th class="wb-table-actions">{{ $t('actions') }}</th></tr></thead>
<tbody>
    @foreach ($textRows as $key => $row)
        <tr>
            <td><strong>{{ $row['label'] }}</strong></td>
            <td><div class="wb-stack wb-gap-1">
                @foreach ($row['fields'] as $field)
                    <div><span class="wb-text-sm wb-text-muted">{{ $field['label'] }}:</span> {{ \Illuminate\Support\Str::limit($field['value'], 140) ?: $t('not_set') }}</div>
                @endforeach
            </div></td>
            <td><span class="wb-status-pill {{ $row['translation_status'] === 'default_value' ? 'wb-status-inactive' : 'wb-status-info' }}">{{ $t($row['translation_status']) }}</span></td>
            <td class="wb-table-actions"><div class="wb-action-group">
                <a href="{{ route($sectionRoute, $consentContext + ['edit' => $key]) }}" class="wb-action-btn {{ $canWrite ? 'wb-action-btn-edit' : '' }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}: {{ $row['label'] }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-{{ $canEditIcon }}" aria-hidden="true"></i></a>
            </div></td>
        </tr>
    @endforeach
</tbody>
