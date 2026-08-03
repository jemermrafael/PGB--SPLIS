<?php

namespace App\Http\Controllers;

use App\Models\DirectoryCategory;
use App\Models\DirectoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryEntryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DirectoryEntry::class);

        $entries = DirectoryEntry::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('directory.index', [
            'entries' => $entries,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DirectoryEntry::class);

        $nextSortOrder = ((int) DirectoryEntry::query()->max('sort_order')) + 1;

        return view('directory.form', [
            'entry' => new DirectoryEntry([
                'sort_order' => $nextSortOrder,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DirectoryEntry::class);

        DirectoryEntry::query()->create($this->validated($request));

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

        $directoryEntry->update($this->validated($request, $directoryEntry));

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
    protected function validated(Request $request, ?DirectoryEntry $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'directory_category_id' => ['nullable', 'integer', 'exists:directory_categories,id'],
            'contact_number' => ['nullable', 'string', 'max:100'],
            'emails' => ['nullable', 'array', 'max:20'],
            'emails.*' => ['nullable', 'email', 'max:255'],
            'focal_persons' => ['nullable', 'array', 'max:20'],
            'focal_persons.*.name' => ['nullable', 'string', 'max:200'],
            'focal_persons.*.emails' => ['nullable', 'array', 'max:10'],
            'focal_persons.*.emails.*' => ['nullable', 'email', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);

        $emails = collect($data['emails'] ?? [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn (string $email) => $email !== '')
            ->unique()
            ->values()
            ->all();

        $payload = [
            'name' => $data['name'],
            'contact_number' => $data['contact_number'] ?? null,
            'emails' => $emails !== [] ? $emails : null,
            'email' => $emails[0] ?? null,
            'designation' => $data['designation'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        // Category / focal persons are no longer on the form; keep existing values on edit
        // unless explicitly posted (e.g. older clients / tests).
        if ($request->exists('directory_category_id') || $request->exists('focal_persons')) {
            $categoryId = $data['directory_category_id'] ?? null;
            $isProvincialBoard = $categoryId
                && DirectoryCategory::query()
                    ->whereKey($categoryId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(DirectoryCategory::PROVINCIAL_BOARD)])
                    ->exists();

            $payload['directory_category_id'] = $categoryId;
            $payload['focal_persons'] = $isProvincialBoard
                ? $this->normalizedFocalPersons($data['focal_persons'] ?? [])
                : null;
        } elseif ($existing === null) {
            $payload['directory_category_id'] = null;
            $payload['focal_persons'] = null;
        }

        return $payload;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{name: string, emails: list<string>}>|null
     */
    protected function normalizedFocalPersons(mixed $raw): ?array
    {
        $persons = collect(is_array($raw) ? $raw : [])
            ->map(function ($person): ?array {
                if (! is_array($person)) {
                    return null;
                }

                $name = trim((string) ($person['name'] ?? ''));
                $emails = collect($person['emails'] ?? [])
                    ->map(fn ($email) => trim((string) $email))
                    ->filter(fn (string $email) => $email !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($name === '' && $emails === []) {
                    return null;
                }

                return [
                    'name' => $name,
                    'emails' => $emails,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $persons !== [] ? $persons : null;
    }
}
