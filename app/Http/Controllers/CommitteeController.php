<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommitteeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Committee::class);

        $committees = Committee::query()
            ->ordered()
            ->paginate(50);

        return view('committees.index', [
            'committees' => $committees,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Committee::class);

        return view('committees.form', [
            'committee' => new Committee([
                'is_active' => true,
                'sort_order' => (int) Committee::query()->max('sort_order') + 1,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Committee::class);

        $data = $this->validated($request);
        Committee::create($data);

        return redirect()
            ->route('committees.index')
            ->with('status', 'Committee created.');
    }

    public function edit(Committee $committee): View
    {
        $this->authorize('update', $committee);

        return view('committees.form', [
            'committee' => $committee,
        ]);
    }

    public function update(Request $request, Committee $committee): RedirectResponse
    {
        $this->authorize('update', $committee);

        $committee->update($this->validated($request, $committee));

        return redirect()
            ->route('committees.index')
            ->with('status', 'Committee updated.');
    }

    public function destroy(Committee $committee): RedirectResponse
    {
        $this->authorize('delete', $committee);

        $committee->delete();

        return redirect()
            ->route('committees.index')
            ->with('status', 'Committee deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Committee $committee = null): array
    {
        return $request->validate([
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'name' => [
                'required',
                'string',
                'max:200',
                Rule::unique('committees', 'name')->ignore($committee?->id),
            ],
            'chair' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'vice_chair' => ['nullable', 'string', 'max:200'],
            'members' => ['nullable', 'string', 'max:5000'],
            'secretary' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
