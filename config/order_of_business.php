<?php

return [
    'session_kinds' => [
        'regular' => 'Regular Session',
        'special' => 'Special Session',
    ],

    'session_statuses' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'document_statuses' => [
        'draft' => 'Draft',
        'final' => 'Final',
    ],

    'agenda_sections' => [
        'committee_reports' => 'IV. Committee Reports',
        'unfinished' => 'A. Unfinished Business',
        'business_2nd' => '1. Measures for 2nd Reading',
        'business_3rd' => '2. Measures for 3rd Reading',
        'unassigned_urgent' => '1. Urgent Request/s',
        'unassigned_regular' => '2. Regular Unassigned Business',
    ],

    'session_pdf_links' => [
        'pdf_summary_committee_reports' => 'Summary of Comm. Reports',
        'pdf_committee_reports' => 'Committee Reports',
        'pdf_draft_journal' => 'Draft Journal',
        'pdf_draft_minutes' => 'Draft Minutes',
        'pdf_final_journal' => 'Final Journal',
        'pdf_final_minutes' => 'Final Minutes',
    ],

    'committee_report_summary' => [
        'recommendation_templates' => [
            [
                'label' => 'Declare validity',
                'html' => 'TO DECLARE THE VALIDITY UPON SUSPENSION OF RULES',
            ],
            [
                'label' => 'Declare operative (conditions)',
                'html' => 'TO DECLARE OPERATIVE IN ITS ENTIRETY SUBJECT TO CERTAIN CONDITIONS UPON SUSPENSION OF RULES',
            ],
            [
                'label' => 'Approve ordinance (2nd reading)',
                'html' => 'TO APPROVE THE PROPOSED ORDINANCE ON SECOND READING UPON SUSPENSION OF RULES',
            ],
            [
                'label' => 'Approve',
                'html' => 'TO APPROVE UPON SUSPENSION OF RULES',
            ],
        ],
    ],
];
