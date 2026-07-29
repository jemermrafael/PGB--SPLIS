<?php

        return [

    'legacy_pdf_root' => env('RESOLUTION_LEGACY_PDF_PATH') ?: base_path('oldsp/PDF'),

    'storage_pdf_root' => storage_path('app/resolutions'),

    'per_page' => 15,

    'per_page_options' => [15, 25, 50, 100],

    'csv_export_path' => env('RESOLUTION_CSV_EXPORT_PATH') ?: base_path('oldsp/Databases/SP/resocsv/spreso1'),

    'version_reasons' => [
        'encoded' => 'Initial encoding',
        'published_from_agenda' => 'Published from agenda',
        'published_from_incoming' => 'Published from incoming',
        'imported' => 'Imported',
        'title' => 'Title update',
        'pdf' => 'PDF update',
        'general' => 'General update',
    ],

];
