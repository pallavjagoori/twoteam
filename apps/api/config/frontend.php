<?php

return [
    'manifest_path' => env(
        'CHATWOOT_MANIFEST_PATH',
        public_path('build/chatwoot/.vite/manifest.json'),
    ),
    'sentry_dsn' => env('SENTRY_FRONTEND_DSN', ''),
];
