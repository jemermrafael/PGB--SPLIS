<?php

namespace App\Support;

final class OrdinancePdfType
{
    public const MAIN = 'main';

    public const BULLETIN = 'bulletin';

    public const CERTIFICATION = 'certification';

    public const NEWSPAPER = 'newspaper';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::MAIN,
            self::BULLETIN,
            self::CERTIFICATION,
            self::NEWSPAPER,
        ];
    }

    public static function isValid(?string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * @return array{url: string, path: string, upload: string, suffix: string, label: string}
     */
    public static function config(string $type): array
    {
        return match ($type) {
            self::MAIN => [
                'url' => 'pdf_url',
                'path' => 'pdf_path',
                'upload' => 'pdf',
                'suffix' => '',
                'label' => 'Ordinance PDF',
            ],
            self::BULLETIN => [
                'url' => 'mov_bulletin_url',
                'path' => 'mov_bulletin_pdf_path',
                'upload' => 'mov_bulletin_pdf',
                'suffix' => '-bulletin',
                'label' => 'Bulletin PDF',
            ],
            self::CERTIFICATION => [
                'url' => 'mov_certification_url',
                'path' => 'mov_certification_pdf_path',
                'upload' => 'mov_certification_pdf',
                'suffix' => '-certification',
                'label' => 'Certification PDF',
            ],
            self::NEWSPAPER => [
                'url' => 'mov_newspaper_url',
                'path' => 'mov_newspaper_pdf_path',
                'upload' => 'mov_newspaper_pdf',
                'suffix' => '-newspaper',
                'label' => 'Newspaper PDF',
            ],
            default => throw new \InvalidArgumentException('Unknown Ordinance PDF type: '.$type),
        };
    }
}
