<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="{{ asset('cms/css/email.css') }}">
    </head>
    <body class="wb-email-body" style="margin:0; padding:24px; background:#f8fafc; color:#0f172a; font-family:Arial, sans-serif; line-height:1.5;">
        @php
            $page = $contactMessage->page;
            $site = $page?->site;
            $siteLabel = $site ? trim((string) ($site->publicDisplayName() ?: $site->canonicalDomain())) : '';
        @endphp

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; max-width:720px;">
            <tr>
                <td>
                    <h1 class="wb-email-title" style="font-size:22px; line-height:1.25; margin:0 0 20px;">New contact message</h1>
                </td>
            </tr>
            <tr>
                <td style="background:#ffffff; border:1px solid #dbe3ef; border-radius:8px; padding:20px;">
                    <div style="font-size:12px; font-weight:bold; letter-spacing:.04em; margin-bottom:14px; text-transform:uppercase; color:#475569;">Visitor message</div>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:0 0 10px; width:96px; color:#64748b;">Name</td>
                            <td style="padding:0 0 10px;"><strong>{{ $contactMessage->name }}</strong></td>
                        </tr>
                        <tr>
                            <td style="padding:0 0 10px; width:96px; color:#64748b;">Email</td>
                            <td style="padding:0 0 10px;"><a href="mailto:{{ $contactMessage->email }}" style="color:#0f766e;">{{ $contactMessage->email }}</a></td>
                        </tr>
                        <tr>
                            <td style="padding:0 0 10px; width:96px; color:#64748b;">Subject</td>
                            <td style="padding:0 0 10px;"><strong>{{ $contactMessage->subject ?: '-' }}</strong></td>
                        </tr>
                        <tr>
                            <td style="padding:0; width:96px; color:#64748b; vertical-align:top;">Message</td>
                            <td class="wb-email-message-body" style="padding:0; font-size:16px; white-space:normal;">{!! nl2br(e($contactMessage->message)) !!}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="height:16px; line-height:16px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:18px;">
                    <div style="font-size:12px; font-weight:bold; letter-spacing:.04em; margin-bottom:12px; text-transform:uppercase; color:#64748b;">Submission details</div>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:6px 16px 6px 0; width:150px; color:#64748b;">Site</td>
                            <td style="padding:6px 0;">{{ $siteLabel !== '' ? $siteLabel : '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; width:150px; color:#64748b;">Page title</td>
                            <td style="padding:6px 0;">{{ $page?->title ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; width:150px; color:#64748b;">Source URL</td>
                            <td style="padding:6px 0;">{{ $contactMessage->source_url ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; width:150px; color:#64748b;">Source path</td>
                            <td style="padding:6px 0;">{{ $contactMessage->sourcePath() }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px 6px 0; width:150px; color:#64748b;">Received</td>
                            <td style="padding:6px 0;">{{ $contactMessage->created_at?->format('Y-m-d H:i:s T') ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="height:16px; line-height:16px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; color:#475569; padding:16px;">
                    <div style="font-size:12px; font-weight:bold; letter-spacing:.04em; margin-bottom:10px; text-transform:uppercase; color:#64748b;">Technical details</div>

                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; font-size:13px;">
                        <tr>
                            <td style="padding:5px 14px 5px 0; width:150px; color:#64748b;">IP address</td>
                            <td style="padding:5px 0;">{{ $contactMessage->ip_address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:5px 14px 5px 0; width:150px; color:#64748b;">User agent</td>
                            <td style="padding:5px 0;">{{ $contactMessage->user_agent ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:5px 14px 5px 0; width:150px; color:#64748b;">Block ID</td>
                            <td style="padding:5px 0;">{{ $contactMessage->block_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:5px 14px 5px 0; width:150px; color:#64748b;">Page ID</td>
                            <td style="padding:5px 0;">{{ $contactMessage->page_id ?? '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
