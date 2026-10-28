<thead><tr><th>{{ $t('text_name') }}</th><th>{{ $t('value') }}</th><th>{{ $t('translation_source') }}</th><th class="wb-table-actions">{{ $t('actions') }}</th></tr></thead>
<tbody>
    @foreach ($textRows as $key => $row)
        <tr>
            <td><strong>{{ $row['label'] }}</strong></td>
            <td>{{ \Illuminate\Support\Str::limit($row['value'], 160) ?: $t('not_set') }}</td>
            <td><span class="wb-status-pill {{ $row['customized'] ? 'wb-status-info' : 'wb-status-inactive' }}">{{ $t($row['customized'] ? 'customized' : 'default_value') }}</span></td>
            <td class="wb-table-actions"><div class="wb-action-group">
                <a href="{{ route($sectionRoute, $consentContext + ['edit' => $key]) }}" class="wb-action-btn {{ $canWrite ? 'wb-action-btn-edit' : '' }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}: {{ $row['label'] }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-{{ $canEditIcon }}" aria-hidden="true"></i></a>
            </div></td>
        </tr>
    @endforeach
</tbody>
