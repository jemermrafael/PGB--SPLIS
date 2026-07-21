<?php

namespace App\Services;

use App\Models\LegislativeSession;
use App\Support\MediaType;
use App\Support\SessionPdfSlot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionPdfService
{
    public function storageRelativePath(int $sessionId, string $slot, string $extension = 'pdf'): string
    {
        $filename = SessionPdfSlot::config($slot)['filename'];

        return 'order-of-business/'.$sessionId.'/'.$filename.'.'.ltrim($extension, '.');
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

    public function existsFor(LegislativeSession $session, string $slot): bool
    {
        if (! SessionPdfSlot::isMirrorable($slot)) {
            return false;
        }

        $column = SessionPdfSlot::config($slot)['path'];

        return $column !== null && $this->absolutePath($session->{$column}) !== null;
    }

    public function publicUrl(LegislativeSession $session, string $slot): ?string
    {
        $config = SessionPdfSlot::config($slot);

        if ($config['kind'] === 'folder') {
            $url = trim((string) ($session->{$config['field']} ?? ''));

            return $url !== '' ? $url : null;
        }

        if ($this->existsFor($session, $slot)) {
            return route('ob.sessions.pdf', ['legislativeSession' => $session, 'slot' => $slot]);
        }

        $url = trim((string) ($session->{$config['field']} ?? ''));

        return $url !== '' ? $url : null;
    }

    public function viewerMode(LegislativeSession $session, string $slot): ?string
    {
        $config = SessionPdfSlot::config($slot);

        if ($config['kind'] === 'folder') {
            return 'link';
        }

        $pathColumn = $config['path'];
        $path = $pathColumn !== null && filled($session->{$pathColumn})
            ? $this->absolutePath($session->{$pathColumn})
            : null;

        if ($path !== null) {
            $media = MediaType::fromPath($path);

            if ($media === null) {
                return null;
            }

            if (MediaType::isImageMime($media['mime'])) {
                return 'image';
            }

            if (MediaType::isOfficeMime($media['mime'])) {
                return 'download';
            }

            return 'pdf';
        }

        $url = trim((string) ($session->{$config['field']} ?? ''));

        return $url !== '' ? 'embed' : null;
    }

    public function store(UploadedFile $file, LegislativeSession $session, string $slot): string
    {
        $media = MediaType::fromUploadedMime(
            (string) $file->getMimeType(),
            $file->getClientOriginalExtension(),
        );

        if (
            MediaType::isOfficeMime($media['mime'])
            && ! SessionPdfSlot::acceptsOfficeDocuments($slot)
        ) {
            throw new \InvalidArgumentException('Word documents are only allowed for Draft Journal and Draft Minutes.');
        }

        return $this->storeBytes(
            (string) file_get_contents($file->getRealPath()),
            $session,
            $slot,
            $media['extension'],
        );
    }

    public function storeBytes(string $contents, LegislativeSession $session, string $slot, string $extension = 'pdf'): string
    {
        $relative = $this->storageRelativePath((int) $session->id, $slot, $extension);

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $this->deleteSiblingVariants((int) $session->id, $slot, $extension);

        return $relative;
    }

    public function stream(LegislativeSession $session, string $slot): StreamedResponse
    {
        $column = SessionPdfSlot::config($slot)['path'];
        abort_if($column === null, 404, 'File not found.');

        $path = $this->absolutePath($session->{$column});
        abort_if($path === null, 404, 'File not found.');

        $media = MediaType::fromPath($path);
        abort_if($media === null, 404, 'Unsupported file type.');

        $disposition = MediaType::isOfficeMime($media['mime']) ? 'attachment' : 'inline';

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => $disposition.'; filename="'.basename($path).'"',
        ]);
    }

    /**
     * @return list<string>
     */
    public function missingMirrorSlots(LegislativeSession $session): array
    {
        $missing = [];

        foreach (SessionPdfSlot::mirrorable() as $slot) {
            $config = SessionPdfSlot::config($slot);
            $hasUrl = filled($session->{$config['field']});
            $hasLocal = $this->existsFor($session, $slot);

            if ($hasUrl && ! $hasLocal) {
                $missing[] = $slot;
            }
        }

        return $missing;
    }

    /**
     * Delete the locally stored file for a slot and clear its path column.
     */
    public function deleteLocal(LegislativeSession $session, string $slot): bool
    {
        if (! SessionPdfSlot::isMirrorable($slot)) {
            return false;
        }

        $column = SessionPdfSlot::config($slot)['path'];

        if ($column === null) {
            return false;
        }

        $stored = $session->{$column};

        if (filled($stored) && Storage::disk('local')->exists($stored)) {
            Storage::disk('local')->delete($stored);
        }

        // Remove any leftover sibling variants (e.g. previous extension).
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'] as $extension) {
            $relative = $this->storageRelativePath((int) $session->id, $slot, $extension);

            if (Storage::disk('local')->exists($relative)) {
                Storage::disk('local')->delete($relative);
            }
        }

        if (! filled($stored)) {
            return false;
        }

        $session->update([$column => null]);

        return true;
    }

    protected function deleteSiblingVariants(int $sessionId, string $slot, string $keepExtension): void
    {
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'] as $extension) {
            if ($extension === $keepExtension) {
                continue;
            }

            $relative = $this->storageRelativePath($sessionId, $slot, $extension);

            if (Storage::disk('local')->exists($relative)) {
                Storage::disk('local')->delete($relative);
            }
        }
    }
}
