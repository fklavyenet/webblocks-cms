<?php

return [
    'recipient_email' => env('CONTACT_RECIPIENT_EMAIL'),
    'minimum_submit_seconds' => (int) env('CONTACT_MINIMUM_SUBMIT_SECONDS', 3),
    'rate_limit_per_minute' => (int) env('CONTACT_RATE_LIMIT_PER_MINUTE', 5),
    'success_message' => env('CONTACT_SUCCESS_MESSAGE', 'Thanks for your message. We will get back to you soon.'),
];
