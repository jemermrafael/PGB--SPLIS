<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class DriveMirrorQueueSettings
{
    public const DEFAULT_PER_MINUTE = 5;

    public function path(): string
    {
        return storage_path('app/drive-mirror-queue-settings.json');
    }

    public function isAutoEnabled(): bool
    {
        return (bool) ($this->get()['auto_enabled'] ?? false);
    }

    public function perMinute(): int
    {
        $value = (int) ($this->get()['per_minute'] ?? self::DEFAULT_PER_MINUTE);

        return max(1, min(5, $value));
    }

    /**
     * @param  array{auto_enabled?: bool, per_minute?: int}  $values
     */
    public function update(array $values): void
    {
        $current = $this->get();

        if (array_key_exists('auto_enabled', $values)) {
            $current['auto_enabled'] = (bool) $values['auto_enabled'];
        }

        if (isset($values['per_minute'])) {
            $current['per_minute'] = max(1, min(5, (int) $values['per_minute']));
        }

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{auto_enabled?: bool, per_minute?: int}
     */
    public function get(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }
}
