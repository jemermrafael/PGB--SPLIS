<?php

return [
    'xlsx_path' => env('ORDINANCES_XLSX_PATH', base_path('oldsp/Ordinance.xlsx')),
    'xlsx_sheet' => env('ORDINANCES_XLSX_SHEET', 'Ordinance'),
    'default_series_year' => (int) env('ORDINANCES_DEFAULT_SERIES_YEAR', 2026),
    'per_page' => 15,
    'classifications' => [
        'Citizen',
        'Government',
        'Business',
    ],
];
