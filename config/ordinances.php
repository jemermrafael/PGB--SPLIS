<?php

return [
    'csv_export_path' => env('ORDINANCES_CSV_EXPORT_PATH', base_path('oldsp')),
    'csv_path' => env('ORDINANCES_CSV_PATH', base_path('oldsp/Ordinances-001.csv')),
    'csv_prefix' => env('ORDINANCES_CSV_PREFIX', 'Ordinances-'),
    'xlsx_path' => env('ORDINANCES_XLSX_PATH', base_path('oldsp/Ordinance.xlsx')),
    'xlsx_sheet' => env('ORDINANCES_XLSX_SHEET', 'Ordinance'),
    'default_series_year' => (int) env('ORDINANCES_DEFAULT_SERIES_YEAR', 2026),
    'per_page' => 15,
    'classifications' => [
        'Citizen',
        'Government',
        'Business',
    ],
    'version_reasons' => [
        'encoded' => 'Initial encoding',
        'title' => 'Title update',
        'pdf' => 'PDF update',
        'general' => 'General update',
    ],
];
