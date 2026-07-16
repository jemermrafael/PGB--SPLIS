<?php

namespace App\Services;

use App\Models\Resolution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfAttachmentService
{
    public function legacyRoot(): string
    {
        return rtrim(config('resolutions.legacy_pdf_root'), DIRECTORY_SEPARATOR);
    }

    public function storageRoot(): string
    {
        return rtrim(config('resolutions.storage_pdf_root'), DIRECTORY_SEPARATOR);
    }

    public function filename(string $resolutionNo): string
    {
        return trim($resolutionNo).'.pdf';
    }

    public function storageRelativePath(int $series, string $resolutionNo): string
    {
        return 'resolutions/'.$series.'/'.$this->filename($resolutionNo);
    }

    public function relativePath(int $series, string $resolutionNo): string
    {
        return $series.DIRECTORY_SEPARATOR.$this->filename($resolutionNo);
    }

    public function absolutePath(string $relativePath): ?string
    {
        $path = storage_path('app'.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));

        return File::isFile($path) ? $path : null;
    }

    public function storagePath(int $series, string $resolutionNo): string
    {
        return $this->storageRoot().DIRECTORY_SEPARATOR.$this->relativePath($series, $resolutionNo);
    }

    public function legacyPath(int $series, string $resolutionNo): string
    {
        return $this->legacyRoot().DIRECTORY_SEPARATOR.$this->relativePath($series, $resolutionNo);
    }

    public function exists(int $series, string $resolutionNo, ?string $pdfPath = null): bool
    {
        return $this->resolvePath($series, $resolutionNo, $pdfPath) !== null;
    }

    public function existsFor(Resolution $resolution): bool
    {
        return $this->resolvePath($resolution->series, $resolution->resolution_no, $resolution->pdf_path) !== null;
    }

    public function hasLinkedPdf(Resolution $resolution): bool
    {
        return filled($resolution->pdf_path) || filled($resolution->sp_pdf_url);
    }

    public function linkStatus(Resolution $resolution): string
    {
        if (! $this->hasLinkedPdf($resolution)) {
            return 'none';
        }

        if (filled($resolution->sp_pdf_url) && ! filled($resolution->pdf_path)) {
            return 'external';
        }

        if (filled($resolution->pdf_path) && $this->existsFor($resolution)) {
            return 'local';
        }

        if (filled($resolution->pdf_path)) {
            return 'missing';
        }

        return 'none';
    }

    public function publicUrl(Resolution $resolution): ?string
    {
        if (filled($resolution->pdf_path)) {
            return route('resolutions.pdf', [
                'series' => $resolution->series,
                'resolutionNo' => $resolution->resolution_no,
            ]);
        }

        $spUrl = trim((string) ($resolution->sp_pdf_url ?? ''));

        return $spUrl !== '' ? $spUrl : null;
    }

    public function findLegacyFile(int $series, string $resolutionNo): ?string
    {
        $legacy = $this->legacyPath($series, $resolutionNo);
        if (File::isFile($legacy)) {
            return $legacy;
        }

        $legacyDir = $this->legacyRoot().DIRECTORY_SEPARATOR.$series;
        if (File::isDirectory($legacyDir)) {
            $target = strtolower($this->filename($resolutionNo));
            foreach (File::files($legacyDir) as $file) {
                if (strtolower($file->getFilename()) === $target) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    public function resolvePath(int $series, string $resolutionNo, ?string $pdfPath = null): ?string
    {
        if ($pdfPath) {
            $fromDb = $this->absolutePath($pdfPath);
            if ($fromDb) {
                return $fromDb;
            }
        }

        $storage = $this->storagePath($series, $resolutionNo);
        if (File::isFile($storage)) {
            return $storage;
        }

        return $this->findLegacyFile($series, $resolutionNo);
    }

    public function resolveFor(Resolution $resolution): ?string
    {
        return $this->resolvePath($resolution->series, $resolution->resolution_no, $resolution->pdf_path);
    }

    public function store(UploadedFile $file, int $series, string $resolutionNo): string
    {
        $relative = $this->storageRelativePath($series, $resolutionNo);
        $dir = storage_path('app/resolutions/'.$series);
        File::ensureDirectoryExists($dir);

        $filename = $this->filename($resolutionNo);
        $file->move($dir, $filename);

        return $relative;
    }

    public function stream(int $series, string $resolutionNo, ?string $pdfPath = null): StreamedResponse
    {
        $path = $this->resolvePath($series, $resolutionNo, $pdfPath);

        abort_if($path === null, 404, 'PDF not found.');

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }
}
