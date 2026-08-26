@if (count($health['checks'] ?? []) > 0)
    <div class="wb-table-wrap">
        <table class="wb-table">
            <thead>
                <tr>
                    <th>{{ $healthText('check') }}</th>
                    <th>{{ $healthText('status') }}</th>
                    <th>{{ $healthText('details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($health['checks'] as $check)
                    @php
                        $checkClass = match ($check['status']) {
                            'healthy' => 'wb-status-active',
                            'warning', 'error', 'incompatible' => 'wb-status-danger',
                            default => 'wb-status-pending',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $check['name'] }}</strong></td>
                        <td><span class="wb-status {{ $checkClass }}">{{ ucfirst($check['status']) }}</span></td>
                        <td>{{ $check['message'] !== '' ? $check['message'] : $healthText('no_details') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="wb-text-sm wb-text-muted">{{ $healthText('no_checks') }}</div>
@endif
