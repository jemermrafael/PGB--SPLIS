<?php

namespace App\Services;

use App\Models\Ordinance;
use App\Support\MediaType;
use App\Support\OrdinancePdfType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdinancePdfService
{
    public function storageRelativePath(int $seriesYear, int $ordinanceNo, string $type = OrdinancePdfType::MAIN, string $extension = 'pdf'): string
    {
        $suffix = OrdinancePdfType::config($type)['suffix'];

        return 'ordinances/'.$seriesYear.'/'.$this->filename($ordinanceNo, $suffix, $extension);
    }

    public function filename(int $ordinanceNo, string $suffix = '', string $extension = 'pdf'): string
    {
        return str_pad((string) $ordinanceNo, 2, '0', STR_PAD_LEFT).$suffix.'.'.ltrim($extension, '.');
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

    public function pathFor(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): ?string
    {
        $column = OrdinancePdfType::config($type)['path'];
        $relative = $ordinance->{$column};

        return filled($relative) ? $this->absolutePath($relative) : null;
    }

    public function existsFor(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): bool
    {
        return $this->pathFor($ordinance, $type) !== null;
    }

    public function hasLinkedPdf(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): bool
    {
        $config = OrdinancePdfType::config($type);

        return filled($ordinance->{$config['path']}) || filled($ordinance->{$config['url']});
    }

    /**
     * Prefer local file stream URL; fall back to external URL.
     */
    public function publicUrl(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): ?string
    {
        if ($this->existsFor($ordinance, $type)) {
            return route('ordinances.pdf', [
                'ordinance' => $ordinance,
                'type' => $type === OrdinancePdfType::MAIN ? null : $type,
            ]);
        }

        $urlColumn = OrdinancePdfType::config($type)['url'];
        $url = trim((string) ($ordinance->{$urlColumn} ?? ''));

        return $url !== '' ? $url : null;
    }

    /**
     * Viewer mode for the shared modal: image, pdf, or drive embed fallback.
     */
    public function viewerMode(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): ?string
    {
        $path = $this->pathFor($ordinance, $type);

        if ($path !== null) {
            $media = MediaType::fromPath($path);

            return $media !== null && MediaType::isImageMime($media['mime']) ? 'image' : 'pdf';
        }

        $urlColumn = OrdinancePdfType::config($type)['url'];
        $url = trim((string) ($ordinance->{$urlColumn} ?? ''));

        return $url !== '' ? 'embed' : null;
    }

    public function store(UploadedFile $file, int $seriesYear, int $ordinanceNo, string $type = OrdinancePdfType::MAIN): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        return $this->storeBytes(
            (string) file_get_contents($file->getRealPath()),
            $seriesYear,
            $ordinanceNo,
            $type,
            $media['extension'],
        );
    }

    /**
     * Store under a unique path so previous version files remain on disk.
     */
    public function storeVersioned(UploadedFile $file, Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        $relative = sprintf(
            'ordinances/%d/%s/%s.%s',
            $ordinance->id,
            $type,
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

    /**
     * Write file bytes to private storage (storage/app/private/…).
     */
    public function storeBytes(
        string $contents,
        int $seriesYear,
        int $ordinanceNo,
        string $type = OrdinancePdfType::MAIN,
        string $extension = 'pdf',
    ): string {
        $relative = $this->storageRelativePath($seriesYear, $ordinanceNo, $type, $extension);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $this->deleteLegacyCopy($relative);
        $this->deleteSiblingVariants($seriesYear, $ordinanceNo, $type, $extension);

        return $relative;
    }

    public function stream(Ordinance $ordinance, string $type = OrdinancePdfType::MAIN): StreamedResponse
    {
        $path = $this->pathFor($ordinance, $type);

        abort_if($path === null, 404, 'File not found.');

        return $this->streamAbsolute($path);
    }

    public function streamRelative(string $relativePath): StreamedResponse
    {
        $path = $this->absolutePath($relativePath);

        abort_if($path === null, 404, 'File not found.');

        return $this->streamAbsolute($path);
    }

    protected function streamAbsolute(string $path): StreamedResponse
    {
        $media = MediaType::fromPath($path);
        abort_if($media === null, 404, 'Unsupported file type.');

        $filename = basename($path);

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return list<string>
     */
    public function missingMirrorTypes(Ordinance $ordinance): array
    {
        $missing = [];

        foreach (OrdinancePdfType::all() as $type) {
            $config = OrdinancePdfType::config($type);
            $hasUrl = filled($ordinance->{$config['url']});
            $hasLocal = $this->existsFor($ordinance, $type);

            if ($hasUrl && ! $hasLocal) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    protected function deleteLegacyCopy(string $relative): void
    {
        $legacy = storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if (File::isFile($legacy)) {
            File::delete($legacy);
        }
    }

    /**
     * Remove prior extension variants when replacing e.g. bulletin.pdf with bulletin.jpg.
     */
    protected function deleteSiblingVariants(int $seriesYear, int $ordinanceNo, string $type, string $keepExtension): void
    {
        $suffix = OrdinancePdfType::config($type)['suffix'];
        $extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($extensions as $extension) {
            if ($extension === $keepExtension) {
                continue;
            }

            $relative = $this->storageRelativePath($seriesYear, $ordinanceNo, $type, $extension);

            if (Storage::disk('local')->exists($relative)) {
                Storage::disk('local')->delete($relative);
            }

            $this->deleteLegacyCopy($relative);
        }
    }
}
