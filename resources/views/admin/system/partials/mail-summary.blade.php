@php
    $unavailable = $systemText('mail_not_applicable');
    $empty = $systemText('not_configured');
    $smtp = $summary['smtp'];
    $credentials = fn (bool $configured) => $systemText($configured ? 'mail_configured' : 'not_configured');
    $rows = [
        'mailer' => $summary['mailer'] ?: $empty,
        'host' => $smtp ? ($summary['host'] ?: $empty) : $unavailable,
        'port' => $smtp ? ($summary['port'] ?: $empty) : $unavailable,
        'encryption' => $smtp ? ($summary['encryption'] ?: $systemText('none')) : $unavailable,
        'from_address' => $summary['from_address'] ?: $empty,
        'username' => $smtp ? $credentials($summary['username_configured']) : $unavailable,
        'password_secret' => $smtp ? $credentials($summary['password_configured']) : $unavailable,
    ];
@endphp
<div class="wb-card-body wb-stack wb-gap-2">
    <table class="wb-table wb-table-sm" aria-label="{{ $systemText($mode === 'custom' ? 'custom_mail_configuration' : 'environment_mail_configuration') }}">
        <tbody>
            @foreach ($rows as $label => $value)
                <tr>
                    <th scope="row" class="wb-text-muted">{{ $systemText($label) }}</th>
                    <td class="wb-admin-break-anywhere">{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <details class="wb-collapse-panel">
        <summary class="wb-collapse-trigger">{{ $systemText('mail_more_details') }}</summary>
        <div class="wb-collapse-body">
            <table class="wb-table wb-table-sm">
                <tbody>
                    @foreach ([
                        'from_name' => $summary['from_name'] ?: $empty,
                        'reply_to_address' => $summary['reply_to_address'] ?: $empty,
                        'timeout' => $smtp ? ($summary['timeout'] ?: $empty) : $unavailable,
                    ] as $label => $value)
                        <tr>
                            <th scope="row" class="wb-text-muted">{{ $systemText($label) }}</th>
                            <td class="wb-admin-break-anywhere">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
