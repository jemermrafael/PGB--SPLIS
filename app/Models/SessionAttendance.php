<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_OB = 'ob';

    public const STATUS_EXCUSED = 'excused';

    protected $fillable = [
        'legislative_session_id',
        'board_member_id',
        'is_present',
        'remarks',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LegislativeSession::class, 'legislative_session_id');
    }

    public function boardMember(): BelongsTo
    {
        return $this->belongsTo(BoardMember::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return self::STATUS_PRESENT|self::STATUS_ABSENT|self::STATUS_OB|self::STATUS_EXCUSED
     */
    public function status(): string
    {
        $remarks = strtoupper(trim((string) $this->remarks));

        if (in_array($remarks, ['OB', 'O.B.'], true)) {
            return self::STATUS_OB;
        }

        if (in_array($remarks, ['EXCUSED', '*'], true)) {
            return self::STATUS_EXCUSED;
        }

        return $this->is_present ? self::STATUS_PRESENT : self::STATUS_ABSENT;
    }

    public function printMark(): string
    {
        return match ($this->status()) {
            self::STATUS_PRESENT => '/',
            self::STATUS_ABSENT => 'X',
            self::STATUS_OB => 'OB',
            self::STATUS_EXCUSED => '*',
            default => '',
        };
    }

    public static function printMarkFor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PRESENT => '/',
            self::STATUS_ABSENT => 'X',
            self::STATUS_OB => 'OB',
            self::STATUS_EXCUSED => '*',
            default => '',
        };
    }

    /**
     * Free-text note shown in the Remarks column (separate from status codes in remarks).
     */
    public function displayNotes(): string
    {
        return trim((string) ($this->notes ?? ''));
    }

    /**
     * @return array{is_present: bool, remarks: string|null}
     */
    public static function attributesForStatus(string $status): array
    {
        return match ($status) {
            self::STATUS_PRESENT => ['is_present' => true, 'remarks' => null],
            self::STATUS_OB => ['is_present' => false, 'remarks' => 'OB'],
            self::STATUS_EXCUSED => ['is_present' => false, 'remarks' => 'EXCUSED'],
            default => ['is_present' => false, 'remarks' => null],
        };
    }
}
