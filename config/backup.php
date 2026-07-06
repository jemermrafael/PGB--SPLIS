<?php

return [

  'directory' => storage_path('app/backups'),

  'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

  'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),

  'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),

  'mysql_path' => env('BACKUP_MYSQL_PATH'),

  'mysql_ssl_verify' => filter_var(env('BACKUP_MYSQL_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN),

  'mysql_ssl_mode' => env('BACKUP_MYSQL_SSL_MODE'),

];
