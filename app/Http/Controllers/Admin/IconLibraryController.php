<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IconLibraryItem;
use App\Support\CommitteeIcon;
use App\Support\IconLibrary;
use App\Support\PageIcon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IconLibraryController extends Controller
{
    public function index(): View
    {
        $this->authorizeIconLibrary();

        return view('admin.icons.index', [
            'presetPaths' => CommitteeIcon::paths(),
            'items' => IconLibraryItem::query()
                ->with('creator:id,name')
                ->withCount('committees')
                ->latest()
                ->get(),
            'pageCatalog' => PageIcon::catalog(),
            'pageOverrides' => PageIcon::overridesByPage(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeIconLibrary();

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
        $this->authorizeIconLibrary();

        $name = $iconLibraryItem->name;

        IconLibrary::delete($iconLibraryItem);
        PageIcon::flushCache();

        return back()->with('status', "Icon removed: {$name}");
    }

    public function updatePageIcons(Request $request): RedirectResponse
    {
        $this->authorizeIconLibrary();

        $pageKeys = array_keys(PageIcon::catalog());

        $validated = $request->validate([
            'pages' => ['nullable', 'array'],
            'pages.*' => ['nullable', 'integer', 'exists:icon_library_items,id'],
        ]);

        $pages = $validated['pages'] ?? [];

        foreach ($pageKeys as $pageKey) {
            if (! array_key_exists($pageKey, $pages)) {
                continue;
            }

            $libraryId = $pages[$pageKey];
            PageIcon::assign(
                $pageKey,
                filled($libraryId) ? (int) $libraryId : null,
            );
        }

        return back()->with('status', 'Page title icons saved.');
    }

    protected function authorizeIconLibrary(): void
    {
        abort_unless($this->userCanManageIconLibrary(), 403);
    }

    protected function userCanManageIconLibrary(): bool
    {
        return request()->user()?->canManageIconLibrary() === true;
    }
}
