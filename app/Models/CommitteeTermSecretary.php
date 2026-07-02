<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeTermSecretary extends Model
{
    protected $fillable = [
        'committee_id',
        'committee_term_id',
        'name',
    ];

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return BelongsTo<CommitteeTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(CommitteeTerm::class, 'committee_term_id');
    }
}
