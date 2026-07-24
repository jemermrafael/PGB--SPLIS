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
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'icon' => ['required', 'file', 'mimes:png,svg', 'mimetypes:image/png,image/svg+xml,text/plain', 'max:512'],
        ]);

        $item = IconLibrary::store(
            $request->file('icon'),
            $validated['name'] ?? null,
            $request->user()?->id,
        );

        ActivityLogger::log('icon_library.uploaded', $item, [
            'name' => $item->name,
            'path' => $item->stored_path,
        ]);

        return back()->with('status', "Icon uploaded: {$item->name}");
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
