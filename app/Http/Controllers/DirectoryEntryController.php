<?php

namespace App\Http\Controllers;

use App\Models\DirectoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryEntryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DirectoryEntry::class);

        $entries = DirectoryEntry::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(50);

        return view('directory.index', [
            'entries' => $entries,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', DirectoryEntry::class);

        return view('directory.form', [
            'entry' => new DirectoryEntry(['sort_order' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DirectoryEntry::class);

        $data = $this->validated($request);
        DirectoryEntry::query()->create($data);

        return redirect()
            ->route('directory.index')
            ->with('status', 'Directory entry created.');
    }

    public function edit(DirectoryEntry $directoryEntry): View
    {
        $this->authorize('update', $directoryEntry);

        return view('directory.form', [
            'entry' => $directoryEntry,
        ]);
    }

    public function update(Request $request, DirectoryEntry $directoryEntry): RedirectResponse
    {
        $this->authorize('update', $directoryEntry);

        $directoryEntry->update($this->validated($request));

        return redirect()
            ->route('directory.index')
            ->with('status', 'Directory entry updated.');
    }

    public function destroy(DirectoryEntry $directoryEntry): RedirectResponse
    {
        $this->authorize('delete', $directoryEntry);

        $directoryEntry->delete();

        return redirect()
            ->route('directory.index')
            ->with('status', 'Directory entry removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]) + [
            'sort_order' => (int) ($request->input('sort_order') ?? 0),
        ];
    }
}
