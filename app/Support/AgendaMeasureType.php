<?php

namespace App\Support;

class AgendaMeasureType
{
    public const RESOLUTION = 'resolution';

    public const ORDINANCE = 'ordinance';

    public const APPROPRIATION_ORDINANCE = 'appropriation_ordinance';

    public static function label(?string $type): string
    {
        return config('agenda.measure_types.'.$type, 'Reso. / Ord. / AO');
    }

    public static function outputTypeLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return config('agenda.measure_types.'.$type);
    }

    public static function legacyPdfButtonLabel(?string $resoLabel): string
    {
        if ($resoLabel) {
            return "Reso. / Ord. / AO PDF — {$resoLabel} (GDrive)";
        }

        return 'Reso. / Ord. / AO PDF (GDrive)';
    }

    public static function splisOutputButtonLabel(?string $type): string
    {
        $label = self::outputTypeLabel($type) ?? 'Resolution';

        return "View {$label} in SPLIS";
    }

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return array_keys(config('agenda.measure_types', []));
    }
}
