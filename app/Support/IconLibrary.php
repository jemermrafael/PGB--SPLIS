<?php

namespace App\Support;

use App\Models\IconLibraryItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IconLibrary
{
    public static function store(UploadedFile $file, ?string $name = null, ?int $userId = null): IconLibraryItem
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'svg'], true)) {
            $extension = $file->getMimeType() === 'image/svg+xml' ? 'svg' : 'png';
        }

        $original = $file->getClientOriginalName() ?: "icon.{$extension}";
        $label = trim((string) $name);
        if ($label === '') {
            $label = pathinfo($original, PATHINFO_FILENAME) ?: 'Icon';
        }

        $directory = 'icon-library';
        Storage::disk('local')->makeDirectory($directory);

        $basename = Str::slug($label) ?: 'icon';
        $filename = $basename.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $path = "{$directory}/{$filename}";

        $file->storeAs($directory, $filename, 'local');

        return IconLibraryItem::query()->create([
            'name' => mb_substr($label, 0, 120),
            'original_filename' => mb_substr($original, 0, 255),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType() ?: ($extension === 'svg' ? 'image/svg+xml' : 'image/png'),
            'created_by' => $userId,
        ]);
    }

    public static function delete(IconLibraryItem $item): void
    {
        if ($item->existsLocally()) {
            Storage::disk('local')->delete($item->stored_path);
        }

        $item->delete();
    }
}
