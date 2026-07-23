<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardMemberTerm extends Model
{
    protected $fillable = [
        'board_member_id',
        'committee_term_id',
        'district',
        'ex_officio_title',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
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
