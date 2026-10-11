<?php

return [
  'search' => [
    'string' => 'Enter a valid text search query.',
    'max' => 'The search query may contain at most :count characters.',
    'terms' => 'The search query may contain at most :count words.',
  ],
  'ip_or_cidr' => 'Enter a valid IP address or CIDR network.',
  'contact_form' => [
    'name_required' => 'Enter your name.',
    'email_required' => 'Enter your email address.',
    'email_valid' => 'Enter a valid email address.',
    'consent_required' => 'Confirm the data-processing notice to continue.',
    'message_required' => 'Enter a message.',
  ],
  'comments' => [
    'body_required' => 'Enter a comment.',
    'body_min' => 'Enter at least :min characters.',
    'body_max' => 'Comments may not be longer than :max characters.',
  ],
  'rating' => [
    'required' => 'Choose a rating.',
    'integer' => 'Choose a valid rating.',
    'min' => 'Choose at least :min star.',
    'max' => 'Choose at most :max stars.',
  ],
];
