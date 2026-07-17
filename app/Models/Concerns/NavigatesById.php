<?php

namespace App\Models\Concerns;

/**
 * Previous / next navigation matching the resolutions archive pattern
 * (list ordered newest-first by id: previous = newer, next = older).
 */
trait NavigatesById
{
    public function previousInList(): ?static
    {
        return static::query()
            ->where($this->getKeyName(), '>', $this->getKey())
            ->orderBy($this->getKeyName())
            ->first();
    }

    public function nextInList(): ?static
    {
        return static::query()
            ->where($this->getKeyName(), '<', $this->getKey())
            ->orderByDesc($this->getKeyName())
            ->first();
    }
}
