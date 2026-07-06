<?php

return [

  'directory' => storage_path('app/backups'),

  'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

  'schedule_time' => env('BACKUP_SCHEDULE_TIME', '02:00'),

  'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),

];
