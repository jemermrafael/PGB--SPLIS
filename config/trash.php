<?php

return [
    /*
    | Soft-deleted records older than this many days can be bulk-purged
    | from Admin → Trash (“Purge older than…”).
    */
    'retention_days' => (int) env('TRASH_RETENTION_DAYS', 30),
];
