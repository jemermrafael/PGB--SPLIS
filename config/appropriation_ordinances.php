<?php

return [
    'per_page' => 15,
    'default_series_year' => (int) env('APPROPRIATION_ORDINANCES_DEFAULT_SERIES_YEAR', 2026),
    'csv_path' => env('APPROPRIATION_ORDINANCES_CSV_PATH', base_path('oldsp/ApproOrd.csv')),
];
