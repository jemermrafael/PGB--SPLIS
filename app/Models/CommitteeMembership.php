<?php

namespace App\Models;

use App\Enums\CommitteeMembershipRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeMembership extends Model
{
    protected $fillable = [
        'committee_id',
        'board_member_id',
        'committee_term_id',
        'role',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'role' => CommitteeMembershipRole::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return BelongsTo<BoardMember, $this>
     */
    public function boardMember(): BelongsTo
    {
        return $this->belongsTo(BoardMember::class);
    }

    /**
     * @return BelongsTo<CommitteeTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(CommitteeTerm::class, 'committee_term_id');
    }
}
