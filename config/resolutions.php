<?php

return [

    'legacy_pdf_root' => env('RESOLUTION_LEGACY_PDF_PATH') ?: base_path('oldsp/PDF'),

    'storage_pdf_root' => storage_path('app/resolutions'),

    'per_page' => 15,

    'csv_export_path' => env('RESOLUTION_CSV_EXPORT_PATH') ?: base_path('oldsp/Databases/SP/resocsv/spreso1'),

];
