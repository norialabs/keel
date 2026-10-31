<?php

declare(strict_types=1);

return [
    'connection' => env('KEEL_DB_CONNECTION'),

    'tables' => [
        'audit_logs' => env('KEEL_TABLE_AUDIT_LOGS'),
        'otp_challenges' => env('KEEL_TABLE_OTP_CHALLENGES'),
        'invitations' => env('KEEL_TABLE_INVITATIONS'),
    ],

    'table_prefix' => env('KEEL_TABLE_PREFIX', ''),

    'load_migrations' => (bool) env('KEEL_LOAD_MIGRATIONS', true),

    'timestamps' => env('KEEL_TIMESTAMPS', 'tz'),

    'tenancy' => [
        'enabled' => (bool) env('KEEL_TENANCY', true),

        'column' => env('KEEL_TENANT_COLUMN', 'workspace_id'),
        'workspace_guc' => env('KEEL_TENANT_GUC', 'app.workspace_id'),

        'gucs' => [
            'app.workspace_id',
            'app.user_id',
            'app.staff_read',
            'app.platform_write',
            'app.invitation_token',
        ],

        'staff_read_guc' => env('KEEL_STAFF_READ_GUC', 'app.staff_read'),
        'platform_write_guc' => env('KEEL_PLATFORM_WRITE_GUC', 'app.platform_write'),

        'unscoped_tables' => [
            'auth_events',
            'personal_access_tokens',
        ],

        'queue' => env('KEEL_TENANT_QUEUE', 'default'),
        'overlap_expires_after' => (int) env('KEEL_TENANT_OVERLAP_TTL', 3600),
    ],

    'rbac' => [
        'resources' => null,
        'actions' => null,
    ],

    'audit' => [
        'enabled' => (bool) env('KEEL_AUDIT', true),
    ],

    'http' => [
        'security_headers' => [
            'enabled' => (bool) env('KEEL_SECURITY_HEADERS', true),
            'report_only' => (bool) env('KEEL_CSP_REPORT_ONLY', false),
            'report_uri' => env('KEEL_CSP_REPORT_URI'),
            'directives' => [
                'default-src' => ["'self'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'frame-ancestors' => ["'none'"],
                'object-src' => ["'none'"],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'", 'data:'],
                'style-src' => ["'self'", "'unsafe-inline'"],
                'script-src' => ["'self'"],
                'connect-src' => ["'self'"],
            ],
            'api' => [
                'paths' => ['api/*'],
                'directives' => [
                    'default-src' => ["'none'"],
                    'frame-ancestors' => ["'none'"],
                    'base-uri' => ["'none'"],
                    'form-action' => ["'none'"],
                ],
            ],

            'dev_origin' => (bool) env('KEEL_CSP_DEV_ORIGIN', true),
            'dev_origin_directives' => ['script-src', 'style-src', 'font-src'],

            'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'hsts_max_age' => (int) env('KEEL_HSTS_MAX_AGE', 31_536_000),
        ],

        'trusted_proxies' => env('KEEL_TRUSTED_PROXIES'),
    ],

    'log' => [
        'mask' => env('KEEL_LOG_MASK', 'redact'),

        'exception_channel' => env('KEEL_LOG_EXCEPTION_CHANNEL', 'app'),

        'credential_keys' => [],
        'credential_suffixes' => [],
        'pii_keys' => [],

        'verbatim_keys' => ['payload'],

        'address_suffixes' => ['url', 'uri', 'endpoint', 'callback'],
    ],

    'scheduler' => [
        'heartbeat_ttl' => (int) env('KEEL_HEARTBEAT_TTL', 300),
    ],

    'errors' => [
        'codes' => [502, 504],
        'view' => env('KEEL_ERROR_VIEW', 'errors.'),
        'stylesheet' => env('KEEL_ERROR_STYLESHEET', 'resources/css/app.css'),
    ],

    'csv' => [
        'max_bytes' => (int) env('KEEL_CSV_MAX_BYTES', 5_242_880),
    ],

    'money' => [
        'currency' => env('KEEL_CURRENCY', 'KES'),

        'digits' => (int) env('KEEL_MONEY_DIGITS', 2),

        'fraction_digits' => [
            'KES' => 0,
            'TZS' => 0,
            'UGX' => 0,
            'RWF' => 0,
            'NGN' => 0,
            'USD' => 2,
            'EUR' => 2,
            'GBP' => 2,
            'ZAR' => 2,
        ],

        'max_minor' => (int) env('KEEL_MONEY_MAX_MINOR', 1_000_000_000_000),

        'locales' => [
            'EUR' => 'en_IE',
        ],
    ],

    'db' => [
        'disk' => env('KEEL_BACKUP_DISK', 'local'),
        'timeout' => (int) env('KEEL_BACKUP_TIMEOUT', 1800),
        'compression' => (int) env('KEEL_BACKUP_COMPRESSION', 9),
        'gzip' => (bool) env('KEEL_BACKUP_GZIP', true),
        'working_directory' => env('KEEL_BACKUP_WORKDIR'),
        'encryption_key' => env('KEEL_BACKUP_ENCRYPTION_KEY'),

        'attempts' => (int) env('KEEL_BACKUP_ATTEMPTS', 3),

        'tiers' => [
            'hourly' => [
                'prefix' => env('KEEL_BACKUP_HOURLY_PREFIX', 'backups/hourly'),
                'hours' => (int) env('KEEL_BACKUP_HOURLY_HOURS', 48),
            ],
            'daily' => [
                'prefix' => env('KEEL_BACKUP_DAILY_PREFIX', 'backups/daily'),
                'days' => (int) env('KEEL_BACKUP_DAILY_DAYS', 30),
                'hour' => (int) env('KEEL_BACKUP_DAILY_HOUR', 0),
            ],
        ],

        'admin_connection' => env('KEEL_DB_ADMIN_CONNECTION'),

        'superuser_connection' => env('KEEL_DB_SUPERUSER_CONNECTION'),

        'rebuild' => [
            'unrestored' => ['migrations'],

            'verify_commands' => [],
        ],
    ],

    'identity' => [
        'hash_key' => env('KEEL_HASH_KEY'),

        'country' => env('KEEL_COUNTRY', 'KE'),

        'dialling_codes' => [
            'KE' => '254',
            'UG' => '256',
            'TZ' => '255',
            'RW' => '250',
            'ET' => '251',
            'NG' => '234',
            'GH' => '233',
            'ZA' => '27',
        ],
    ],

    'invitations' => [
        'ttl_days' => (int) env('KEEL_INVITATION_TTL_DAYS', 7),

        'hint' => env('KEEL_INVITATION_HINT', 'masked'),

        'token_guc' => env('KEEL_INVITATION_GUC', 'app.invitation_token'),
    ],

    'auth' => [
        'social' => [
            'state_ttl' => (int) env('KEEL_SOCIAL_STATE_TTL', 10),
        ],

        'otp' => [
            'length' => (int) env('KEEL_OTP_LENGTH', 6),
            'ttl' => (int) env('KEEL_OTP_TTL', 10),
            'attempts' => (int) env('KEEL_OTP_ATTEMPTS', 5),
            'throttle' => (int) env('KEEL_OTP_THROTTLE', 60),

            'hint' => env('KEEL_OTP_HINT', 'masked'),
        ],
    ],
];
