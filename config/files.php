<?php

return [
    'disk' => env('FILES_DISK', 'local'),
    'quota_bytes' => env('FILES_QUOTA_BYTES', 1073741824), // 1 GB default
    'base_directory' => env('FILES_BASE_DIR', 'user-files'),
];
