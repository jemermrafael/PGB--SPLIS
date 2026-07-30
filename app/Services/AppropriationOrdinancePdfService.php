<?php

namespace App\Services;

use App\Models\AppropriationOrdinance;
use App\Support\MediaType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppropriationOrdinancePdfService
{
    public function storageRelativePath(int $seriesYear, int $ordinanceNo, string $extension = 'pdf'): string
    {
        return 'appropriation-ordinances/'.$seriesYear.'/'.$this->filename($ordinanceNo, $extension);
    }

    public function filename(int $ordinanceNo, string $extension = 'pdf'): string
    {
        return str_pad((string) $ordinanceNo, 2, '0', STR_PAD_LEFT).'.'.ltrim($extension, '.');
    }

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

    public function existsFor(AppropriationOrdinance $record): bool
    {
        return $this->absolutePath($record->pdf_path) !== null;
    }

    public function hasLinkedPdf(AppropriationOrdinance $record): bool
    {
        return filled($record->pdf_path) || filled($record->pdf_url);
    }

    public function publicUrl(AppropriationOrdinance $record): ?string
    {
        if ($this->existsFor($record)) {
            return route('appropriation-ordinances.pdf', $record);
        }

        $url = trim((string) ($record->pdf_url ?? ''));

        return $url !== '' ? $url : null;
    }

    public function viewerMode(AppropriationOrdinance $record): ?string
    {
        $path = filled($record->pdf_path) ? $this->absolutePath($record->pdf_path) : null;

        if ($path !== null) {
            $media = MediaType::fromPath($path);

            return $media !== null && MediaType::isImageMime($media['mime']) ? 'image' : 'pdf';
        }

        $url = trim((string) ($record->pdf_url ?? ''));

        return $url !== '' ? 'embed' : null;
    }

    public function store(UploadedFile $file, int $seriesYear, int $ordinanceNo): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        return $this->storeBytes(
            (string) file_get_contents($file->getRealPath()),
            $seriesYear,
            $ordinanceNo,
            $media['extension'],
        );
    }

    /**
     * Store under a unique path so previous version files remain on disk.
     */
    public function storeVersioned(UploadedFile $file, AppropriationOrdinance $record): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        $relative = sprintf(
            'appropriation-ordinances/%d/versions/%s.%s',
            $record->id,
            strtolower((string) Str::ulid()),
            $media['extension'],
        );

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put(
            $relative,
            (string) file_get_contents($file->getRealPath()),
        );

        return $relative;
    }

    public function storeBytes(string $contents, int $seriesYear, int $ordinanceNo, string $extension = 'pdf'): string
    {
        $relative = $this->storageRelativePath($seriesYear, $ordinanceNo, $extension);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $this->deleteSiblingVariants($seriesYear, $ordinanceNo, $extension);

        return $relative;
    }

    public function stream(AppropriationOrdinance $record): StreamedResponse
    {
        $path = $this->absolutePath($record->pdf_path);

        abort_if($path === null, 404, 'File not found.');

        $media = MediaType::fromPath($path);
        abort_if($media === null, 404, 'Unsupported file type.');

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function needsMirror(AppropriationOrdinance $record): bool
    {
        return filled($record->pdf_url) && ! $this->existsFor($record);
    }

    protected function deleteSiblingVariants(int $seriesYear, int $ordinanceNo, string $keepExtension): void
    {
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
            if ($extension === $keepExtension) {
                continue;
            }

            $relative = $this->storageRelativePath($seriesYear, $ordinanceNo, $extension);

            if (Storage::disk('local')->exists($relative)) {
                Storage::disk('local')->delete($relative);
            }
        }
    }
}
