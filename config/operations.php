<?php

return [
    'alert_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPS_ALERT_EMAILS', env('SAAS_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS', '')))),
    ))),
    'public_health_url' => env('OPS_HEALTH_URL', rtrim(env('APP_URL', ''), '/').'/healthz'),
    'queue_backlog_limit' => (int) env('OPS_QUEUE_BACKLOG_LIMIT', 250),
    'queue_oldest_minutes' => (int) env('OPS_QUEUE_OLDEST_MINUTES', 10),
    'backup_max_age_hours' => (int) env('OPS_BACKUP_MAX_AGE_HOURS', 26),
    'restore_database' => env('BACKUP_RESTORE_DATABASE'),
    'alert_cooldown_minutes' => (int) env('OPS_ALERT_COOLDOWN_MINUTES', 60),
];
