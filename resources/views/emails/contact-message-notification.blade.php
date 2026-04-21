<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="{{ asset('site/css/email.css') }}">
    </head>
    <body class="wb-email-body">
        <h1 class="wb-email-title">New contact message</h1>

        <p><strong>Name:</strong> {{ $contactMessage->name }}</p>
        <p><strong>Email:</strong> {{ $contactMessage->email }}</p>
        <p><strong>Subject:</strong> {{ $contactMessage->subject ?? '-' }}</p>
        <p><strong>Page:</strong> {{ $contactMessage->page?->title ?? '-' }}</p>
        <p><strong>Source URL:</strong> {{ $contactMessage->source_url ?? '-' }}</p>
        <p><strong>Received:</strong> {{ $contactMessage->created_at?->format('Y-m-d H:i:s') }}</p>

        <div class="wb-email-message">
            <strong>Message</strong>
            <div class="wb-email-message-body">{{ $contactMessage->message }}</div>
        </div>
    </body>
</html>
