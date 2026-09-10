<?php

return [
  'quarantine_score' => (int) env('CMS_SUBMISSION_QUARANTINE_SCORE', 20),
  'spam_score' => (int) env('CMS_SUBMISSION_SPAM_SCORE', 60),
  'fingerprint_window_seconds' => (int) env('CMS_SUBMISSION_FINGERPRINT_WINDOW', 86400),
  'source_window_seconds' => (int) env('CMS_SUBMISSION_SOURCE_WINDOW', 3600),
  'similarity_distance' => (int) env('CMS_SUBMISSION_SIMILARITY_DISTANCE', 10),
];
