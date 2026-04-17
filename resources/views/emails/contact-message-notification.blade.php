<!DOCTYPE html>
<html lang="en">
    <body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
        <h1 style="font-size: 20px; margin-bottom: 16px;">New contact message</h1>

        <p><strong>Name:</strong> {{ $contactMessage->name }}</p>
        <p><strong>Email:</strong> {{ $contactMessage->email }}</p>
        <p><strong>Subject:</strong> {{ $contactMessage->subject ?? '-' }}</p>
        <p><strong>Page:</strong> {{ $contactMessage->page?->title ?? '-' }}</p>
        <p><strong>Source URL:</strong> {{ $contactMessage->source_url ?? '-' }}</p>
        <p><strong>Received:</strong> {{ $contactMessage->created_at?->format('Y-m-d H:i:s') }}</p>

        <div style="margin-top: 20px;">
            <strong>Message</strong>
            <div style="margin-top: 8px; white-space: pre-line;">{{ $contactMessage->message }}</div>
        </div>
    </body>
</html>
