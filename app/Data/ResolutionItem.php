<?php

namespace App\Data;

class ResolutionItem
{
    public function __construct(
        public string $source,
        public int|string $id,
        public string $resolutionNo,
        public ?string $resolutionTitle,
        public int $series,
        public ?string $sponsoredBy = null,
        public ?string $dateApproved = null,
        public ?string $categoryLabel = null,
        public ?string $committee = null,
        public ?string $keyword = null,
        public ?string $departmentLabel = null,
        public ?string $municipalityLabel = null,
        public string $documentType = 'resolution',
        public ?string $documentTypeLabel = null,
        public ?string $documentTypeBadgeClass = null,
        public bool $hasPdf = false,
        public ?string $pdfUrl = null,
        public ?string $pdfStatus = null,
        public ?string $status = null,
    ) {}
}
