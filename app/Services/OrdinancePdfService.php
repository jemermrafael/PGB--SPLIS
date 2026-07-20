<?php

namespace App\Services;

use App\Models\Ordinance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdinancePdfService
{
    public function storageRelativePath(int $seriesYear, int $ordinanceNo): string
    {
        return 'ordinances/'.$seriesYear.'/'.$this->filename($ordinanceNo);
    }

    public function filename(int $ordinanceNo): string
    {
        return str_pad((string) $ordinanceNo, 2, '0', STR_PAD_LEFT).'.pdf';
    }

    /**
     * Resolve an absolute path under storage/app/private (local disk).
     * Falls back to legacy storage/app/ordinances for files mirrored before the private move.
     */
    public function absolutePath(?string $relativePath): ?string
    {
        if (! filled($relativePath)) {
            return null;
        }

        $relative = str_replace('\\', '/', ltrim($relativePath, '/'));

        if (Storage::disk('local')->exists($relative)) {
            return Storage::disk('local')->path($relative);
        }

        $legacy = storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        return File::isFile($legacy) ? $legacy : null;
    }

    public function existsFor(Ordinance $ordinance): bool
    {
        return $this->absolutePath($ordinance->pdf_path) !== null;
    }

    public function hasLinkedPdf(Ordinance $ordinance): bool
    {
        return filled($ordinance->pdf_path) || filled($ordinance->pdf_url);
    }

    /**
     * Prefer local file stream URL; fall back to external pdf_url.
     */
    public function publicUrl(Ordinance $ordinance): ?string
    {
        if ($this->existsFor($ordinance)) {
            return route('ordinances.pdf', $ordinance);
        }

        $url = trim((string) ($ordinance->pdf_url ?? ''));

        return $url !== '' ? $url : null;
    }

    public function store(UploadedFile $file, int $seriesYear, int $ordinanceNo): string
    {
        $relative = $this->storageRelativePath($seriesYear, $ordinanceNo);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->putFileAs(
            dirname($relative),
            $file,
            basename($relative),
        );

        $this->deleteLegacyCopy($relative);

        return $relative;
    }

    /**
     * Write raw PDF bytes to private storage (storage/app/private/…).
     */
    public function storeBytes(string $contents, int $seriesYear, int $ordinanceNo): string
    {
        $relative = $this->storageRelativePath($seriesYear, $ordinanceNo);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $this->deleteLegacyCopy($relative);

        return $relative;
    }

    public function stream(Ordinance $ordinance): StreamedResponse
    {
        $path = $this->absolutePath($ordinance->pdf_path);

        abort_if($path === null, 404, 'PDF not found.');

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename((int) $ordinance->ordinance_no).'"',
        ]);
    }

    /**
     * Remove a leftover file from the old storage/app/ordinances location.
     */
    protected function deleteLegacyCopy(string $relative): void
    {
        $legacy = storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if (File::isFile($legacy)) {
            File::delete($legacy);
        }
    }
}
