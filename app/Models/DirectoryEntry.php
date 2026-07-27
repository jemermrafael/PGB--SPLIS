<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectoryEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'directory_category_id',
        'name',
        'contact_number',
        'email',
        'emails',
        'focal_persons',
        'designation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'emails' => 'array',
            'focal_persons' => 'array',
            'directory_category_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DirectoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DirectoryCategory::class, 'directory_category_id');
    }

    /**
     * @param  Builder<DirectoryEntry>  $query
     * @return Builder<DirectoryEntry>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('designation', 'like', $like)
                ->orWhere('contact_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('emails', 'like', $like)
                ->orWhere('focal_persons', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
        });
    }

    /**
     * @return list<string>
     */
    public function emailList(): array
    {
        $emails = collect($this->emails ?? [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn (string $email) => $email !== '')
            ->values()
            ->all();

        if ($emails === [] && filled($this->email)) {
            return [trim((string) $this->email)];
        }

        return $emails;
    }

    public function primaryEmail(): ?string
    {
        $emails = $this->emailList();

        return $emails[0] ?? null;
    }

    /**
     * @return list<array{name: string, emails: list<string>}>
     */
    public function focalPersonsList(): array
    {
        return collect($this->focal_persons ?? [])
            ->map(function ($person): ?array {
                $name = trim((string) ($person['name'] ?? ''));
                $emails = collect($person['emails'] ?? [])
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn (string $email) => $email !== '')
                    ->values()
                    ->all();

                if ($name === '' && $emails === []) {
                    return null;
                }

                return [
                    'name' => $name,
                    'emails' => $emails,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function isProvincialBoardCategory(): bool
    {
        $name = $this->relationLoaded('category')
            ? ($this->category?->name ?? '')
            : ($this->category()->value('name') ?? '');

        return strcasecmp(trim((string) $name), 'Provincial Board') === 0;
    }
}
