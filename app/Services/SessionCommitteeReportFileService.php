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

        return $this->storeBytes(
            (string) file_get_contents($uploadedFile->getRealPath()),
            $session,
            $originalName !== '' ? $originalName : null,
            $media['extension'],
            $media['mime'],
            $userId,
            $uploadedFile->getSize() ?: null,
        );
    }

    /**
     * @param  ?int  $fileSize
     */
    public function storeBytes(
        string $contents,
        LegislativeSession $session,
        ?string $originalFilename,
        string $extension = 'pdf',
        ?string $mimeType = null,
        ?int $userId = null,
        ?int $fileSize = null,
        ?int $boardMemberCommitteeReportId = null,
    ): LegislativeSessionCommitteeReportFile {
        $extension = ltrim(strtolower($extension), '.');
        $originalName = trim((string) $originalFilename);
        $baseName = pathinfo($originalName !== '' ? $originalName : 'committee-report', PATHINFO_FILENAME);
        $safeBase = Str::slug($baseName) !== '' ? Str::slug($baseName) : 'committee-report';
        $storedName = $safeBase.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $relative = $this->storageDirectory((int) $session->id).'/'.$storedName;

        Storage::disk('local')->makeDirectory(dirname($relative));
        Storage::disk('local')->put($relative, $contents);

        $nextSort = (int) $session->committeeReportFiles()->max('sort_order') + 1;

        if ($originalName !== '' && ! str_contains(strtolower($originalName), '.'.$extension)) {
            $originalName .= '.'.$extension;
        }

        return $session->committeeReportFiles()->create([
            'board_member_committee_report_id' => $boardMemberCommitteeReportId,
            'original_filename' => $originalName !== '' ? $originalName : $storedName,
            'stored_path' => $relative,
            'mime_type' => $mimeType ?: match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'doc' => 'application/msword',
                default => 'application/pdf',
            },
            'file_size' => $fileSize ?? strlen($contents),
            'sort_order' => $nextSort,
            'created_by' => $userId,
        ]);
    }

    public function hasFileNamed(LegislativeSession $session, string $originalFilename): bool
    {
        $needle = strtolower(trim($originalFilename));

        if ($needle === '') {
            return false;
        }

        return $session->committeeReportFiles()
            ->get()
            ->contains(fn (LegislativeSessionCommitteeReportFile $file) => strtolower($file->original_filename) === $needle);
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
