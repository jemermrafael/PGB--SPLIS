<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IconLibraryItem;
use App\Support\ActivityLogger;
use App\Support\CommitteeIcon;
use App\Support\IconLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IconLibraryController extends Controller
{
    public function index(): View
    {
        return view('admin.icons.index', [
            'presetPaths' => CommitteeIcon::paths(),
            'items' => IconLibraryItem::query()
                ->with('creator:id,name')
                ->withCount('committees')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'icons' => ['required', 'array', 'min:1'],
            'icons.*' => ['file', 'mimes:png,svg', 'mimetypes:image/png,image/svg+xml,text/plain', 'max:512'],
        ]);

        $uploaded = [];
        foreach ($request->file('icons', []) as $file) {
            if ($file === null) {
                continue;
            }

            $item = IconLibrary::store($file, null, $request->user()?->id);
            $uploaded[] = $item->name;

            ActivityLogger::log('icon_library.uploaded', $item, [
                'name' => $item->name,
                'path' => $item->stored_path,
            ]);
        }

        $count = count($uploaded);
        if ($count === 0) {
            return back()->with('error', 'No icons were uploaded.');
        }

        $status = $count === 1
            ? "Icon uploaded: {$uploaded[0]}"
            : "{$count} icons uploaded.";

        return back()->with('status', $status);
    }

    public function destroy(IconLibraryItem $iconLibraryItem): RedirectResponse
    {
        $name = $iconLibraryItem->name;

        ActivityLogger::log('icon_library.deleted', $iconLibraryItem, [
            'name' => $name,
            'path' => $iconLibraryItem->stored_path,
        ]);

        IconLibrary::delete($iconLibraryItem);

        return back()->with('status', "Icon removed: {$name}");
    }
}
