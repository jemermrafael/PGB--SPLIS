<?php

namespace App\Support;

use App\Models\AgendaItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AgendaDeadline
{
    public const TONE_ACCOMPLISHED = 'accomplished';

    public const TONE_LAPSED = 'lapsed';

    public const TONE_NONE = 'none';

    public const TONE_OVERDUE = 'overdue';

    public const TONE_URGENT = 'urgent';

    public const TONE_OK = 'ok';

    public static function apply(AgendaItem $item): void
    {
        $item->due_date = self::computeDueDate($item);
        $item->days_left_label = self::daysLeftLabel($item);

        if ($item->prescribed_days === 0 && $item->status === AgendaItem::STATUS_PENDING) {
            $item->status = AgendaItem::STATUS_NO_DUE_DATE;
        }
    }

    /**
     * @param  array{date_received?: string|null, prescribed_days?: int|string|null, status?: string|null}  $input
     * @return array{due_date: ?string, days_left_label: ?string, tone: string, status_hint: string}
     */
    public static function preview(array $input): array
    {
        $item = new AgendaItem([
            'date_received' => ! empty($input['date_received']) ? $input['date_received'] : null,
            'prescribed_days' => isset($input['prescribed_days']) && $input['prescribed_days'] !== ''
                ? (int) $input['prescribed_days']
                : null,
            'status' => ! empty($input['status']) ? $input['status'] : AgendaItem::STATUS_PENDING,
        ]);

        $dueDate = self::computeDueDate($item);
        $item->due_date = $dueDate;
        $item->days_left_label = self::daysLeftLabel($item);

        $statusHint = $item->status;
        if ($item->prescribed_days === 0 && $item->status === AgendaItem::STATUS_PENDING) {
            $statusHint = AgendaItem::STATUS_NO_DUE_DATE;
        }

        return [
            'due_date' => $dueDate?->format('Y-m-d'),
            'days_left_label' => $item->days_left_label,
            'tone' => self::toneForItem($item),
            'status_hint' => $statusHint,
        ];
    }

    public static function computeDueDate(AgendaItem $item): ?Carbon
    {
        if (! $item->date_received || ! $item->prescribed_days || $item->prescribed_days < 1) {
            return null;
        }

        $date = $item->date_received instanceof CarbonInterface
            ? $item->date_received
            : Carbon::parse($item->date_received);

        return $date->copy()->addDays($item->prescribed_days);
    }

    public static function daysLeftLabel(AgendaItem $item): ?string
    {
        return match ($item->status) {
            AgendaItem::STATUS_DONE => 'Accomplished',
            AgendaItem::STATUS_LAPSED => 'Deemed Approved',
            AgendaItem::STATUS_NO_DUE_DATE => 'No Deadline',
            default => self::numericDaysLeft($item->due_date),
        };
    }

    public static function toneForItem(AgendaItem $item): string
    {
        return match ($item->status) {
            AgendaItem::STATUS_DONE => self::TONE_ACCOMPLISHED,
            AgendaItem::STATUS_LAPSED => self::TONE_LAPSED,
            AgendaItem::STATUS_NO_DUE_DATE => self::TONE_NONE,
            default => self::toneForNumericLabel($item->days_left_label),
        };
    }

    public static function toneForNumericLabel(?string $label): string
    {
        if ($label === null || $label === '' || ! is_numeric($label)) {
            return self::TONE_NONE;
        }

        $days = (int) $label;

        if ($days < 0) {
            return self::TONE_OVERDUE;
        }

        if ($days <= 7) {
            return self::TONE_URGENT;
        }

        return self::TONE_OK;
    }

    public static function expiringSoonDays(): int
    {
        return max(1, (int) config('agenda.expiring_soon_days', 14));
    }

    public static function dueSoonDays(): int
    {
        return max(1, (int) config('agenda.due_soon_days', 7));
    }

    public static function isWithinExpiringSoonWindow(?CarbonInterface $dueDate, ?string $status): bool
    {
        if ($status !== AgendaItem::STATUS_PENDING || ! $dueDate) {
            return false;
        }

        $due = $dueDate instanceof CarbonInterface
            ? $dueDate->copy()->startOfDay()
            : Carbon::parse($dueDate)->startOfDay();

        $days = now()->startOfDay()->diffInDays($due, false);

        return $days >= 0 && $days <= self::expiringSoonDays();
    }

    public static function progressPercent(AgendaItem $item): ?int
    {
        if (! $item->prescribed_days || $item->prescribed_days < 1 || ! $item->due_date) {
            return null;
        }

        $label = $item->days_left_label;
        if ($label === null || ! is_numeric($label)) {
            return null;
        }

        $daysLeft = max(0, (int) $label);
        $elapsed = $item->prescribed_days - $daysLeft;

        return (int) max(0, min(100, round(($elapsed / $item->prescribed_days) * 100)));
    }

    public static function inferSeries(?CarbonInterface $datePassed, ?CarbonInterface $dateSigned, ?CarbonInterface $dateReceived): ?int
    {
        foreach ([$datePassed, $dateSigned, $dateReceived] as $date) {
            if ($date) {
                return (int) $date->format('Y');
            }
        }

        return null;
    }

    protected static function numericDaysLeft(?CarbonInterface $dueDate): ?string
    {
        if (! $dueDate) {
            return null;
        }

        $days = now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

        return (string) $days;
    }
}
