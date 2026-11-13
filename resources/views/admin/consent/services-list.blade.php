<thead><tr><th>{{ $t('name') }}</th><th>{{ $t('category') }}</th><th>{{ $t('status') }}</th><th>{{ $t('used') }}</th><th class="wb-table-actions">{{ $t('actions') }}</th></tr></thead>
<tbody>
    @foreach ($services as $handle => $service)
        @php($editUrl = route($sectionRoute, $consentContext + ['edit' => $handle]))
        <tr>
            <td><strong>
                @if ($canWrite)
                    <a href="{{ $editUrl }}" class="wb-link" aria-haspopup="dialog">{{ $service['name'] }}</a>
                @else
                    {{ $service['name'] }}
                @endif
            </strong><div class="wb-text-sm wb-text-muted">{{ $service['provider'] === 'custom' ? $t('custom') : $t('presets') }}</div></td>
            <td>{{ $t($service['category']) }}</td>
            <td><span class="wb-status-pill {{ $service['enabled'] ? 'wb-status-active' : 'wb-status-inactive' }}">{{ $t($service['enabled'] ? 'enabled' : 'disabled') }}</span></td>
            <td><span class="wb-status-pill {{ $service['used'] ? 'wb-status-info' : 'wb-status-inactive' }}">{{ $t($service['used'] ? 'used' : 'unused') }}</span></td>
            <td class="wb-table-actions"><div class="wb-action-group">
                <a href="{{ $editUrl }}" class="wb-action-btn {{ $canWrite ? 'wb-action-btn-edit' : '' }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}: {{ $service['name'] }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-{{ $canEditIcon }}" aria-hidden="true"></i></a>
            </div></td>
        </tr>
    @endforeach
</tbody>
