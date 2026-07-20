<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Support\AgendaPdfSlot;
use App\Support\MediaType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaPdfService
{
    public function storageRelativePath(int $agendaId, string $slot, string $extension = 'pdf'): string
    {
        $filename = AgendaPdfSlot::config($slot)['filename'];

        return 'agenda/'.$agendaId.'/'.$filename.'.'.ltrim($extension, '.');
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

    public function existsFor(AgendaItem $agenda, string $slot): bool
    {
        $column = AgendaPdfSlot::config($slot)['path'];

        return $this->absolutePath($agenda->{$column}) !== null;
    }

    public function publicUrl(AgendaItem $agenda, string $slot): ?string
    {
        if ($this->existsFor($agenda, $slot)) {
            return route('agenda.file', ['agenda' => $agenda, 'slot' => $slot]);
        }

        $urlColumn = AgendaPdfSlot::config($slot)['url'];
        $url = trim((string) ($agenda->{$urlColumn} ?? ''));

        return $url !== '' ? $url : null;
    }

    public function viewerMode(AgendaItem $agenda, string $slot): ?string
    {
        $pathColumn = AgendaPdfSlot::config($slot)['path'];
        $path = filled($agenda->{$pathColumn}) ? $this->absolutePath($agenda->{$pathColumn}) : null;

        if ($path !== null) {
            $media = MediaType::fromPath($path);

            return $media !== null && MediaType::isImageMime($media['mime']) ? 'image' : 'pdf';
        }

        $urlColumn = AgendaPdfSlot::config($slot)['url'];
        $url = trim((string) ($agenda->{$urlColumn} ?? ''));

        return $url !== '' ? 'embed' : null;
    }

    public function store(UploadedFile $file, AgendaItem $agenda, string $slot): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        return $this->storeBytes(
            (string) file_get_contents($file->getRealPath()),
            $agenda,
            $slot,
            $media['extension'],
        );
    }

    public function storeBytes(string $contents, AgendaItem $agenda, string $slot, string $extension = 'pdf'): string
    {
        $relative = $this->storageRelativePath((int) $agenda->id, $slot, $extension);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $this->deleteSiblingVariants((int) $agenda->id, $slot, $extension);

        return $relative;
    }

    public function stream(AgendaItem $agenda, string $slot): StreamedResponse
    {
        $column = AgendaPdfSlot::config($slot)['path'];
        $path = $this->absolutePath($agenda->{$column});

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

    /**
     * @return list<string>
     */
    public function missingMirrorSlots(AgendaItem $agenda): array
    {
        $missing = [];

        foreach (AgendaPdfSlot::all() as $slot) {
            $config = AgendaPdfSlot::config($slot);
            $hasUrl = filled($agenda->{$config['url']});
            $hasLocal = $this->existsFor($agenda, $slot);

            if ($hasUrl && ! $hasLocal) {
                $missing[] = $slot;
            }
        }

        return $missing;
    }

    protected function deleteSiblingVariants(int $agendaId, string $slot, string $keepExtension): void
    {
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
            if ($extension === $keepExtension) {
                continue;
            }

            $relative = $this->storageRelativePath($agendaId, $slot, $extension);

            if (Storage::disk('local')->exists($relative)) {
                Storage::disk('local')->delete($relative);
            }
        }
    }
}
