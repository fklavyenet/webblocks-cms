<?php

return [
    'backup' => [
        'execution' => env('CMS_BACKUP_EXECUTION', 'auto'),
        'dump_timeout_seconds' => env('CMS_BACKUP_DUMP_TIMEOUT_SECONDS', 120),
    ],
];
