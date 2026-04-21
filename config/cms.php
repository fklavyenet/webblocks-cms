<?php

return [
    'multisite' => [
        'unknown_host_fallback' => env('CMS_MULTISITE_UNKNOWN_HOST_FALLBACK', env('APP_ENV', 'production') !== 'production'),
    ],
    'backup' => [
        'execution' => env('CMS_BACKUP_EXECUTION', 'auto'),
        'dump_timeout_seconds' => env('CMS_BACKUP_DUMP_TIMEOUT_SECONDS', 120),
        'restore_timeout_seconds' => env('CMS_BACKUP_RESTORE_TIMEOUT_SECONDS', 300),
    ],
];
