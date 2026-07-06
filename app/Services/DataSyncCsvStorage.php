<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class DataSyncCsvStorage
{
    public function store(UploadedFile $file): string
    {
        $dir = storage_path('app/data-sync/'.uniqid('', true));
        File::ensureDirectoryExists($dir);

        $name = $this->safeFilename($file->getClientOriginalName());
        $file->move($dir, $name);

        return $dir.DIRECTORY_SEPARATOR.$name;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }

        $dir = dirname($path);
        if (is_dir($dir) && str_contains(str_replace('\\', '/', $dir), '/data-sync/')) {
            @rmdir($dir);
        }
    }

    protected function safeFilename(?string $name): string
    {
        $name = trim((string) $name);
        $name = $name !== '' ? basename($name) : 'upload.csv';

        if (! str_ends_with(strtolower($name), '.csv')) {
            $name .= '.csv';
        }

        return preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?: 'upload.csv';
    }
}
