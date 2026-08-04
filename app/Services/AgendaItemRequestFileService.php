<?php

namespace App\Services;

use App\Models\AgendaItem;
use App\Models\AgendaItemRequestFile;
use App\Support\AgendaPdfSlot;
use App\Support\MediaType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaItemRequestFileService
{
    /**
     * Canonical directory for newly mirrored/uploaded packet files.
     */
    public function storageDirectory(int $agendaId): string
    {
        return 'agenda/'.$agendaId.'/request-packet';
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

    public function exists(AgendaItemRequestFile $file): bool
    {
        return $this->absolutePath($file->stored_path) !== null;
    }

    public function publicUrl(AgendaItemRequestFile $file): ?string
    {
        if (! $this->exists($file)) {
            return null;
        }

        return route('agenda.request-files.show', [
            'agenda' => $file->agenda_item_id,
            'file' => $file,
        ]);
    }

    public function viewerMode(AgendaItemRequestFile $file): ?string
    {
        $path = $this->absolutePath($file->stored_path);

        if ($path === null) {
            return null;
        }

        $media = MediaType::fromPath($path);

        return $media !== null && MediaType::isImageMime($media['mime']) ? 'image' : 'pdf';
    }

    public function store(UploadedFile $uploadedFile, AgendaItem $agenda, ?string $relativeFolder = null, ?int $userId = null): AgendaItemRequestFile
    {
        $media = MediaType::fromUploadedMime(
            (string) $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalExtension(),
        );

        $originalName = trim((string) $uploadedFile->getClientOriginalName());

        return $this->storeBytes(
            (string) file_get_contents($uploadedFile->getRealPath()),
            $agenda,
            $originalName !== '' ? $originalName : null,
            $this->normalizeFolder($relativeFolder),
            $media['extension'],
            $media['mime'],
            $userId,
            $uploadedFile->getSize() ?: null,
        );
    }

    public function storeBytes(
        string $contents,
        AgendaItem $agenda,
        ?string $originalFilename,
        ?string $relativeFolder = null,
        string $extension = 'pdf',
        ?string $mimeType = null,
        ?int $userId = null,
        ?int $fileSize = null,
    ): AgendaItemRequestFile {
        $extension = ltrim(strtolower($extension), '.');
        $folder = $this->normalizeFolder($relativeFolder);
        $originalName = trim((string) $originalFilename);
        $baseName = pathinfo($originalName !== '' ? $originalName : 'request-file', PATHINFO_FILENAME);
        $safeBase = Str::slug($baseName) !== '' ? Str::slug($baseName) : 'request-file';
        $storedName = $safeBase.'-'.Str::lower(Str::random(8)).'.'.$extension;

        $relative = $this->storageDirectory((int) $agenda->id);
        if ($folder !== null) {
            $relative .= '/'.$this->safeFolderPath($folder);
        }
        $relative .= '/'.$storedName;

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        if ($originalName !== '' && ! str_contains(strtolower($originalName), '.'.$extension)) {
            $originalName .= '.'.$extension;
        }

        $file = $agenda->requestFiles()->create([
            'relative_folder' => $folder,
            'original_filename' => $originalName !== '' ? $originalName : $storedName,
            'stored_path' => $relative,
            'mime_type' => $mimeType ?: $this->defaultMime($extension),
            'file_size' => $fileSize ?? strlen($contents),
            'sort_order' => (int) $agenda->requestFiles()->max('sort_order') + 1,
            'created_by' => $userId,
        ]);

        $this->syncPrimaryRequestPdfFromRootPacket($agenda);

        return $file;
    }

    /**
     * Register an existing file already on the local disk without copying.
     */
    public function registerExistingPath(
        AgendaItem $agenda,
        string $storedPath,
        string $originalFilename,
        ?string $relativeFolder = null,
        ?int $userId = null,
    ): ?AgendaItemRequestFile {
        $storedPath = str_replace('\\', '/', ltrim($storedPath, '/'));

        if ($this->absolutePath($storedPath) === null) {
            return null;
        }

        $existing = $agenda->requestFiles()
            ->where('stored_path', $storedPath)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($this->hasFileInFolder($agenda, $originalFilename, $relativeFolder)) {
            return null;
        }

        $abs = $this->absolutePath($storedPath);
        $media = $abs !== null ? MediaType::fromPath($abs) : null;

        $file = $agenda->requestFiles()->create([
            'relative_folder' => $this->normalizeFolder($relativeFolder),
            'original_filename' => $originalFilename,
            'stored_path' => $storedPath,
            'mime_type' => $media['mime'] ?? null,
            'file_size' => $abs !== null ? (File::size($abs) ?: null) : null,
            'sort_order' => (int) $agenda->requestFiles()->max('sort_order') + 1,
            'created_by' => $userId,
        ]);

        $this->syncPrimaryRequestPdfFromRootPacket($agenda);

        return $file;
    }

    /**
     * Scan agenda/{id} on disk for packet-style folders/files and register them.
     *
     * @return array{registered: int, skipped: int}
     */
    public function importFromDisk(AgendaItem $agenda, ?int $userId = null): array
    {
        $registered = 0;
        $skipped = 0;
        $root = 'agenda/'.$agenda->id;

        if (! Storage::disk('local')->exists($root)) {
            return compact('registered', 'skipped');
        }

        foreach (Storage::disk('local')->allFiles($root) as $relative) {
            $relative = str_replace('\\', '/', $relative);
            $underRoot = Str::after($relative, $root.'/');

            if ($underRoot === '' || $underRoot === $relative) {
                $skipped++;

                continue;
            }

            if ($this->isReservedAgendaPath($underRoot)) {
                $skipped++;

                continue;
            }

            $parts = explode('/', $underRoot);
            $filename = array_pop($parts);
            $folder = $parts !== [] ? implode('/', $parts) : null;

            // Files under request-packet/ keep folder relative to that base.
            if ($folder !== null && str_starts_with($folder, 'request-packet')) {
                $folder = trim(Str::after($folder, 'request-packet'), '/');
                $folder = $folder !== '' ? $folder : null;
            }

            if ($filename === '' || $this->isUnsupportedFilename($filename)) {
                $skipped++;

                continue;
            }

            $created = $this->registerExistingPath($agenda, $relative, $filename, $folder, $userId);

            if ($created !== null && $created->wasRecentlyCreated) {
                $registered++;
            } else {
                $skipped++;
            }
        }

        $this->syncPrimaryRequestPdfFromRootPacket($agenda);

        return compact('registered', 'skipped');
    }

    /**
     * Root-level packet PDFs (no folder) are the agenda's primary Request PDF.
     * Updates request_pdf_path quietly — does not create an agenda version.
     */
    public function syncPrimaryRequestPdfFromRootPacket(AgendaItem $agenda): void
    {
        $agenda->unsetRelation('requestFiles');

        $primary = $agenda->requestFiles()
            ->where(function ($query): void {
                $query->whereNull('relative_folder')
                    ->orWhere('relative_folder', '');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(function (AgendaItemRequestFile $file): bool {
                if (! $this->exists($file)) {
                    return false;
                }

                $ext = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
                $mime = strtolower((string) $file->mime_type);

                return $ext === 'pdf' || $mime === 'application/pdf' || str_ends_with(strtolower($file->stored_path), '.pdf');
            });

        if ($primary === null) {
            return;
        }

        if ((string) $agenda->request_pdf_path === (string) $primary->stored_path) {
            return;
        }

        $agenda->forceFill([
            'request_pdf_path' => $primary->stored_path,
        ])->saveQuietly();
    }

    public function hasFileInFolder(AgendaItem $agenda, string $originalFilename, ?string $relativeFolder = null): bool
    {
        $needle = strtolower(trim($originalFilename));
        $folder = $this->normalizeFolder($relativeFolder);

        if ($needle === '') {
            return false;
        }

        return $agenda->requestFiles()
            ->get()
            ->contains(function (AgendaItemRequestFile $file) use ($needle, $folder): bool {
                return strtolower($file->original_filename) === $needle
                    && $this->normalizeFolder($file->relative_folder) === $folder;
            });
    }

    /**
     * @return Collection<string, Collection<int, AgendaItemRequestFile>>
     */
    public function groupedByFolder(AgendaItem $agenda): Collection
    {
        return $agenda->requestFiles
            ->filter(fn (AgendaItemRequestFile $file) => $file->existsLocally())
            ->sortBy([
                fn (AgendaItemRequestFile $file) => mb_strtolower($file->relative_folder ?? ''),
                fn (AgendaItemRequestFile $file) => $file->sort_order,
                fn (AgendaItemRequestFile $file) => mb_strtolower($file->original_filename),
            ])
            ->groupBy(fn (AgendaItemRequestFile $file) => $file->folderLabel());
    }

    public function delete(AgendaItemRequestFile $file): void
    {
        $agenda = $file->agendaItem;
        $path = $file->stored_path;

        // Only delete files we own under request-packet/ — leave manually placed trees intact on disk.
        if (
            filled($path)
            && str_contains(str_replace('\\', '/', $path), '/request-packet/')
            && Storage::disk('local')->exists($path)
        ) {
            Storage::disk('local')->delete($path);
        }

        $file->delete();

        if ($agenda !== null) {
            if ((string) $agenda->request_pdf_path === (string) $path) {
                $agenda->forceFill(['request_pdf_path' => null])->saveQuietly();
            }
            $this->syncPrimaryRequestPdfFromRootPacket($agenda->fresh() ?? $agenda);
        }
    }

    public function stream(AgendaItemRequestFile $file): StreamedResponse
    {
        $path = $this->absolutePath($file->stored_path);
        abort_if($path === null, 404, 'File not found.');

        $media = MediaType::fromPath($path) ?? ['mime' => 'application/pdf'];
        $filename = $file->original_filename ?: basename($path);

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => $media['mime'],
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function normalizeFolder(?string $folder): ?string
    {
        $folder = trim(str_replace('\\', '/', (string) $folder), '/');

        if ($folder === '' || strcasecmp($folder, 'root') === 0) {
            return null;
        }

        $parts = array_values(array_filter(
            explode('/', $folder),
            fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..',
        ));

        return $parts === [] ? null : implode('/', $parts);
    }

    protected function safeFolderPath(string $folder): string
    {
        $parts = explode('/', $folder);

        return implode('/', array_map(
            fn (string $part): string => Str::slug($part) !== '' ? $part : 'folder',
            $parts,
        ));
    }

    protected function defaultMime(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            default => 'application/pdf',
        };
    }

    protected function isReservedAgendaPath(string $underRoot): bool
    {
        $normalized = strtolower($underRoot);
        $filename = strtolower(basename($underRoot));

        foreach (AgendaPdfSlot::all() as $slot) {
            $base = strtolower(AgendaPdfSlot::config($slot)['filename']);
            if (preg_match('/^'.preg_quote($base, '/').'(\.[a-z0-9]+)?$/', $filename)) {
                return true;
            }
            // Versioned request uploads: agenda/{id}/request/{ulid}.ext
            if (str_starts_with($normalized, $base.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function isUnsupportedFilename(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return ! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'], true);
    }
}
