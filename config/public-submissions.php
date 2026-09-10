<?php

return [
  'quarantine_score' => (int) env('CMS_SUBMISSION_QUARANTINE_SCORE', 20),
  'spam_score' => (int) env('CMS_SUBMISSION_SPAM_SCORE', 60),
  'fingerprint_window_seconds' => (int) env('CMS_SUBMISSION_FINGERPRINT_WINDOW', 86400),
  'source_window_seconds' => (int) env('CMS_SUBMISSION_SOURCE_WINDOW', 3600),
  'email_window_seconds' => (int) env('CMS_SUBMISSION_EMAIL_WINDOW', 86400),
  'email_quarantine_count' => (int) env('CMS_SUBMISSION_EMAIL_QUARANTINE_COUNT', 4),
  'email_spam_count' => (int) env('CMS_SUBMISSION_EMAIL_SPAM_COUNT', 8),
  'network_window_seconds' => (int) env('CMS_SUBMISSION_NETWORK_WINDOW', 3600),
  'network_quarantine_count' => (int) env('CMS_SUBMISSION_NETWORK_QUARANTINE_COUNT', 12),
  'network_spam_count' => (int) env('CMS_SUBMISSION_NETWORK_SPAM_COUNT', 30),
  'similarity_distance' => (int) env('CMS_SUBMISSION_SIMILARITY_DISTANCE', 10),
];
