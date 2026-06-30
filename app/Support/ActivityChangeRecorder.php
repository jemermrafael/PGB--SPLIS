<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ActivityChangeRecorder
{
    /**
     * @param  list<string>  $keys
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after, array $keys = []): array
    {
        $changes = [];
        $keys = $keys !== []
            ? $keys
            : array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;

            if (self::valuesEqual($from, $to)) {
                continue;
            }

            $changes[$key] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function presentFields(Model $model, array $keys): array
    {
        $fields = [];

        foreach ($keys as $key) {
            $value = $model->getAttribute($key);
            if ($value === null || $value === '') {
                continue;
            }
            $fields[$key] = $value;
        }

        return $fields;
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('M d, Y');
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('M d, Y');
            } catch (\Throwable) {
                // fall through
            }
        }

        $string = trim((string) $value);

        return mb_strlen($string) > 100 ? mb_substr($string, 0, 97).'…' : $string;
    }

    public static function incomingFieldLabel(string $key): string
    {
        return self::incomingFieldLabels()[$key] ?? str_replace('_', ' ', ucfirst($key));
    }

    /**
     * @return array<string, string>
     */
    public static function incomingFieldLabels(): array
    {
        return [
            'mun_resolution_no' => 'Municipal Resolution No.',
            'date_received' => 'Date Received',
            'mun_series' => 'Municipal Series',
            'municipality' => 'Municipality',
            'title' => 'Title',
            'action_taken' => 'Action Taken',
            'referral' => 'Referral',
            'agenda' => 'Agenda',
            'workflow_status' => 'Status',
            'sp_res_no' => 'SP Resolution No.',
            'sp_series' => 'SP Series',
            'sp_title' => 'SP Title',
            'sp_date_approved' => 'SP Date Approved',
            'keyword' => 'Keyword',
            'concerned_agency' => 'Concerned Agency',
            'remarks' => 'Remarks',
            'mun_pdf_url' => 'Municipal PDF',
            'sp_pdf_url' => 'SP PDF',
            'source' => 'Source',
            'link_status' => 'Link Status',
            'resolution_id' => 'Resolution ID',
        ];
    }

    /**
     * @return list<string>
     */
    public static function incomingLoggableKeys(): array
    {
        return array_keys(self::incomingFieldLabels());
    }

    protected static function valuesEqual(mixed $from, mixed $to): bool
    {
        if ($from === $to) {
            return true;
        }

        if (($from === null || $from === '') && ($to === null || $to === '')) {
            return true;
        }

        return (string) $from === (string) $to;
    }
}
