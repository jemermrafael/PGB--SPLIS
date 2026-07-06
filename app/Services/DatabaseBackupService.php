<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupService
{
    public function __construct(
        protected BackupSettings $settings,
    ) {}

    public function directory(): string
    {
        $dir = config('backup.directory');

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function retentionDays(): int
    {
        return max(1, $this->settings->retentionDays());
    }

    /**
     * @return array{filename: string, path: string, size: int, created_at: string}
     */
    public function create(): array
    {
        $config = $this->connectionConfig();
        $database = $config['database'];

        $filename = sprintf('splis-%s.sql.gz', now()->format('Y-m-d-His'));
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        $dump = Process::timeout(600)->run(array_merge(
            [$this->resolveMysqldumpBinary()],
            $this->mysqlConnectionArguments($config),
            [
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                $database,
            ],
        ));

        if (! $dump->successful()) {
            throw new \RuntimeException(trim($dump->errorOutput() ?: $dump->output() ?: 'mysqldump failed.'));
        }

        $compressed = gzencode($dump->output(), 9);

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress database dump.');
        }

        if (file_put_contents($path, $compressed) === false) {
            throw new \RuntimeException("Failed to write backup file: {$path}");
        }

        $this->pruneOldBackups();

        return $this->describeFile($path);
    }

    public function restore(string $filename): void
    {
        $this->restorePath($this->resolvePath($filename));
    }

    public function restoreUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \RuntimeException('Invalid upload.');
        }

        $name = strtolower($file->getClientOriginalName() ?? '');
        if (! str_ends_with($name, '.sql.gz') && ! str_ends_with($name, '.gz')) {
            throw new \RuntimeException('Upload a .sql.gz backup file.');
        }

        $tempPath = $this->directory().DIRECTORY_SEPARATOR.'restore-upload-'.uniqid('', true).'.sql.gz';
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $this->restorePath($tempPath, validateFilename: false);
        } finally {
            @unlink($tempPath);
        }
    }

    public function pruneOldBackups(): int
    {
        $cutoff = now()->subDays($this->retentionDays())->getTimestamp();
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

    protected function restorePath(string $path, bool $validateFilename = true): void
    {
        if ($validateFilename && ! $this->isValidFilename(basename($path))) {
            throw new \InvalidArgumentException('Invalid backup filename.');
        }

        if (! is_file($path)) {
            throw new \RuntimeException('Backup file not found.');
        }

        $config = $this->connectionConfig();
        $mysql = $this->resolveMysqlBinary();
        $args = $this->mysqlConnectionArguments($config);
        $database = $config['database'];

        if (PHP_OS_FAMILY !== 'Windows' && is_executable('/bin/bash')) {
            $shellArgs = implode(' ', array_map('escapeshellarg', $args));
            $command = sprintf(
                'gunzip -c %s | %s %s %s',
                escapeshellarg($path),
                escapeshellarg($mysql),
                $shellArgs,
                escapeshellarg($database),
            );

            $result = Process::timeout(3600)->run(['bash', '-c', $command]);
        } else {
            $sql = $this->readGzipFile($path);
            $result = Process::timeout(3600)->input($sql)->run(array_merge(
                [$mysql],
                $args,
                [$database],
            ));
        }

        if (! $result->successful()) {
            throw new \RuntimeException(trim($result->errorOutput() ?: $result->output() ?: 'mysql restore failed.'));
        }
    }

    /**
     * @return array{host: string, port: string|int, database: string, username: string, password: string}
     */
    protected function connectionConfig(): array
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

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => $config['port'] ?? 3306,
            'database' => $database,
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
        ];
    }

    /**
     * @param  array{host: string, port: string|int, database: string, username: string, password: string}  $config
     * @return list<string>
     */
    protected function mysqlConnectionArguments(array $config): array
    {
        $args = [
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--password='.$config['password'],
        ];

        $sslMode = config('backup.mysql_ssl_mode');
        if (is_string($sslMode) && $sslMode !== '') {
            $args[] = '--ssl-mode='.$sslMode;
        }

        if (! config('backup.mysql_ssl_verify', false)) {
            $args[] = '--ssl-verify-server-cert=0';
        }

        return $args;
    }

    protected function resolveMysqldumpBinary(): string
    {
        return $this->resolveClientBinary('mysqldump', ['mariadb-dump']);
    }

    protected function resolveMysqlBinary(): string
    {
        return $this->resolveClientBinary('mysql', ['mariadb']);
    }

    /**
     * @param  list<string>  $alternatives
     */
    protected function resolveClientBinary(string $primary, array $alternatives = []): string
    {
        $configured = config('backup.mysqldump_path');
        if ($primary === 'mysql' && is_string(config('backup.mysql_path')) && config('backup.mysql_path') !== '') {
            $configured = config('backup.mysql_path');
        }

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ([$primary, ...$alternatives] as $binary) {
            $result = Process::run(PHP_OS_FAMILY === 'Windows'
                ? ['where', $binary]
                : ['which', $binary]);

            if ($result->successful() && trim($result->output()) !== '') {
                $line = trim(strtok(trim($result->output()), PHP_EOL));

                return $line !== '' ? $line : $binary;
            }
        }

        throw new \RuntimeException("{$primary} was not found. Install MySQL/MariaDB client tools.");
    }

    protected function readGzipFile(string $path): string
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to read gzip backup.');
        }

        $sql = '';
        while (! gzeof($handle)) {
            $chunk = gzread($handle, 1024 * 1024);
            if ($chunk === false) {
                gzclose($handle);
                throw new \RuntimeException('Failed to decompress backup.');
            }
            $sql .= $chunk;
        }

        gzclose($handle);

        return $sql;
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
