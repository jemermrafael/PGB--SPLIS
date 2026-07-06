<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BackupSettings
{
    public function path(): string
    {
        return storage_path('app/backup-settings.json');
    }

    public function scheduleTime(): string
    {
        return $this->get()['schedule_time'] ?? config('backup.schedule_time', '02:00');
    }

    public function retentionDays(): int
    {
        return (int) ($this->get()['retention_days'] ?? config('backup.retention_days', 14));
    }

    /**
     * @param  array{schedule_time?: string, retention_days?: int}  $values
     */
    public function update(array $values): void
    {
        $current = $this->get();

        if (isset($values['schedule_time'])) {
            $current['schedule_time'] = $values['schedule_time'];
        }

        if (isset($values['retention_days'])) {
            $current['retention_days'] = $values['retention_days'];
        }

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{schedule_time?: string, retention_days?: int}
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
