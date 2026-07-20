<?php

namespace App\Services;

use App\Models\LegislativeSession;
use App\Models\LegislativeSessionCommitteeReportFile;
use App\Support\MediaType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionCommitteeReportFileService
{
    public function storageDirectory(int $sessionId): string
    {
        return 'order-of-business/'.$sessionId.'/committee-reports';
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

    public function exists(LegislativeSessionCommitteeReportFile $file): bool
    {
        return $this->absolutePath($file->stored_path) !== null;
    }

    public function publicUrl(LegislativeSessionCommitteeReportFile $file): ?string
    {
        if (! $this->exists($file)) {
            return null;
        }

        return route('ob.sessions.committee-report-file', [
            'legislativeSession' => $file->legislative_session_id,
            'file' => $file,
        ]);
    }

    public function viewerMode(LegislativeSessionCommitteeReportFile $file): ?string
    {
        $path = $this->absolutePath($file->stored_path);

        if ($path === null) {
            return null;
        }

        $media = MediaType::fromPath($path);

        return $media !== null && MediaType::isImageMime($media['mime']) ? 'image' : 'pdf';
    }

    public function store(UploadedFile $uploadedFile, LegislativeSession $session, ?int $userId = null): LegislativeSessionCommitteeReportFile
    {
        $media = MediaType::fromUploadedMime(
            (string) $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalExtension(),
        );

        $originalName = trim((string) $uploadedFile->getClientOriginalName());
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = Str::slug($baseName) !== '' ? Str::slug($baseName) : 'committee-report';
        $storedName = $safeBase.'-'.Str::lower(Str::random(8)).'.'.$media['extension'];
        $relative = $this->storageDirectory((int) $session->id).'/'.$storedName;

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, (string) file_get_contents($uploadedFile->getRealPath()));

        $nextSort = (int) $session->committeeReportFiles()->max('sort_order') + 1;

        return $session->committeeReportFiles()->create([
            'original_filename' => $originalName !== '' ? $originalName : $storedName,
            'stored_path' => $relative,
            'mime_type' => $media['mime'],
            'file_size' => $uploadedFile->getSize(),
            'sort_order' => $nextSort,
            'created_by' => $userId,
        ]);
    }

    public function delete(LegislativeSessionCommitteeReportFile $file): void
    {
        $path = $file->stored_path;

        if (filled($path) && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        $file->delete();
    }

    public function stream(LegislativeSessionCommitteeReportFile $file): StreamedResponse
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
}
