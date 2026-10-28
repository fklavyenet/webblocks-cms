<thead><tr><th>{{ $t('setting') }}</th><th>{{ $t('value') }}</th><th class="wb-table-actions">{{ $t('actions') }}</th></tr></thead>
<tbody>
    @foreach (['enabled', 'lifetime_days', 'privacy_url', 'position'] as $key)
        <tr>
            <td><strong>{{ $t($key) }}</strong></td>
            <td>
                @if ($key === 'enabled')
                    <span class="wb-status-pill {{ $settings[$key] ? 'wb-status-active' : 'wb-status-inactive' }}">{{ $t($settings[$key] ? 'enabled' : 'disabled') }}</span>
                @elseif ($key === 'position')
                    {{ $t($settings[$key]) }}
                @else
                    {{ $settings[$key] ?: $t('not_set') }}
                @endif
            </td>
            <td class="wb-table-actions"><div class="wb-action-group">
                <a href="{{ route($sectionRoute, $consentContext + ['edit' => $key]) }}" class="wb-action-btn {{ $canWrite ? 'wb-action-btn-edit' : '' }}" title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}: {{ $t($key) }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-{{ $canEditIcon }}" aria-hidden="true"></i></a>
            </div></td>
        </tr>
    @endforeach
</tbody>
