<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupService
{
    public function directory(): string
    {
        $dir = config('backup.directory');

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @return array{filename: string, path: string, size: int, created_at: string}
     */
    public function create(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') !== 'mysql') {
            throw new \RuntimeException('Database backup only supports MySQL connections.');
        }

        $database = $config['database'] ?? '';
        if ($database === '') {
            throw new \RuntimeException('Database name is not configured.');
        }

        $filename = sprintf('splis-%s.sql.gz', now()->format('Y-m-d-His'));
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;
        $mysqldump = $this->resolveMysqldumpBinary();

        $dump = Process::timeout(600)->run([
            $mysqldump,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--password='.$config['password'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            $database,
        ]);

        if (! $dump->successful()) {
            throw new \RuntimeException(trim($dump->errorOutput() ?: $dump->output() ?: 'mysqldump failed.'));
        }

        $sql = $dump->output();
        $compressed = gzencode($sql, 9);

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress database dump.');
        }

        if (file_put_contents($path, $compressed) === false) {
            throw new \RuntimeException("Failed to write backup file: {$path}");
        }

        $this->pruneOldBackups();

        return $this->describeFile($path);
    }

    public function pruneOldBackups(): int
    {
        $retentionDays = max(1, (int) config('backup.retention_days', 14));
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $removed = 0;

        foreach ($this->listFiles() as $file) {
            if ($file['modified_at'] >= $cutoff) {
                continue;
            }

            if (@unlink($file['path'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return list<array{filename: string, path: string, size: int, size_label: string, modified_at: int, created_at: string}>
     */
    public function list(): array
    {
        return $this->listFiles();
    }

    public function resolvePath(string $filename): string
    {
        if (! $this->isValidFilename($filename)) {
            throw new \InvalidArgumentException('Invalid backup filename.');
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        if (! is_file($path)) {
            throw new \RuntimeException('Backup file not found.');
        }

        return $path;
    }

    public function downloadResponse(string $filename): StreamedResponse
    {
        $path = $this->resolvePath($filename);

        return response()->streamDownload(function () use ($path): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }

            while (! feof($handle)) {
                echo fread($handle, 1024 * 1024);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function isValidFilename(string $filename): bool
    {
        return (bool) preg_match('/^splis-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/', $filename);
    }

    protected function resolveMysqldumpBinary(): string
    {
        $configured = config('backup.mysqldump_path');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach (['mysqldump', 'mariadb-dump'] as $binary) {
            $result = Process::run(PHP_OS_FAMILY === 'Windows'
                ? ['where', $binary]
                : ['which', $binary]);

            if ($result->successful() && trim($result->output()) !== '') {
                $line = trim(strtok(trim($result->output()), PHP_EOL));

                return $line !== '' ? $line : $binary;
            }
        }

        throw new \RuntimeException('mysqldump was not found. Install MySQL/MariaDB client tools.');
    }

    /**
     * @return list<array{filename: string, path: string, size: int, size_label: string, modified_at: int, created_at: string}>
     */
    protected function listFiles(): array
    {
        $dir = $this->directory();
        $files = glob($dir.DIRECTORY_SEPARATOR.'splis-*.sql.gz') ?: [];

        $items = [];
        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $filename = basename($path);
            if (! $this->isValidFilename($filename)) {
                continue;
            }

            $items[] = $this->describeFile($path);
        }

        usort($items, fn (array $a, array $b) => $b['modified_at'] <=> $a['modified_at']);

        return $items;
    }

    /**
     * @return array{filename: string, path: string, size: int, size_label: string, modified_at: int, created_at: string}
     */
    protected function describeFile(string $path): array
    {
        $size = filesize($path) ?: 0;
        $modifiedAt = filemtime($path) ?: time();

        return [
            'filename' => basename($path),
            'path' => $path,
            'size' => $size,
            'size_label' => $this->formatBytes($size),
            'modified_at' => $modifiedAt,
            'created_at' => date('M j, Y g:i A', $modifiedAt),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
