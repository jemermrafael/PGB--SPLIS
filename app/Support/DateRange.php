<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DateRange
{
    /**
     * Parse a free-text date or range such as "4/1/2026-4/7/2026" or multiline
     * "4/1/2026-\n4/7/2026". Returns [start, end] as Y-m-d strings.
     * A single date yields [date, null].
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function parse(mixed $value): array
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return [null, null];
        }

        if (is_numeric($value)) {
            $date = Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();

            return [$date, null];
        }

        if (preg_match_all('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $value, $matches) && $matches[0] !== []) {
            $dates = [];
            foreach ($matches[0] as $match) {
                try {
                    $dates[] = Carbon::parse((string) $match)->toDateString();
                } catch (\Throwable) {
                    // Skip unparseable fragments.
                }
            }

            $dates = array_values(array_unique($dates));

            if ($dates === []) {
                return [null, null];
            }

            if (count($dates) === 1) {
                return [$dates[0], null];
            }

            sort($dates);

            return [$dates[0], $dates[array_key_last($dates)]];
        }

        try {
            return [Carbon::parse($value)->toDateString(), null];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    public static function format(?CarbonInterface $start, ?CarbonInterface $end): ?string
    {
        if ($start === null && $end === null) {
            return null;
        }

        if ($start === null) {
            return $end->format('F j, Y');
        }

        if ($end === null || $start->toDateString() === $end->toDateString()) {
            return $start->format('F j, Y');
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return $start->format('F j').'–'.$end->format('j, Y');
        }

        if ($start->year === $end->year) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j, Y').' – '.$end->format('F j, Y');
    }
}
