<?php

namespace App\Http\Controllers;

use App\Models\DirectoryCategory;
use App\Models\DirectoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DirectoryCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', DirectoryEntry::class);

        return view('directory.categories', [
            'categories' => DirectoryCategory::query()
                ->withCount('entries')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DirectoryEntry::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:directory_categories,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        DirectoryCategory::query()->create([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Category created.');
    }

    public function update(Request $request, DirectoryCategory $directoryCategory): RedirectResponse
    {
        $this->authorize('create', DirectoryEntry::class);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('directory_categories', 'name')->ignore($directoryCategory->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $directoryCategory->update([
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Category updated.');
    }

    public function destroy(DirectoryCategory $directoryCategory): RedirectResponse
    {
        $this->authorize('create', DirectoryEntry::class);

        $directoryCategory->delete();

        return back()->with('status', 'Category removed.');
    }
}
