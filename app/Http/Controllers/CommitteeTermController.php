<?php

namespace App\Http\Controllers;

use App\Models\CommitteeTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommitteeTermController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CommitteeTerm::class);

        $terms = CommitteeTerm::query()
            ->ordered()
            ->paginate(25);

        return view('committee-terms.index', [
            'terms' => $terms,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CommitteeTerm::class);

        return view('committee-terms.form', [
            'term' => new CommitteeTerm([
                'year_from' => (int) now()->format('Y'),
                'is_current' => CommitteeTerm::query()->current()->doesntExist(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CommitteeTerm::class);

        $data = $this->validated($request);
        $this->applyCurrentTerm($data);
        CommitteeTerm::create($data);

        return redirect()
            ->route('committee-terms.index')
            ->with('status', 'Committee term created.');
    }

    public function edit(CommitteeTerm $committeeTerm): View
    {
        $this->authorize('update', $committeeTerm);

        return view('committee-terms.form', [
            'term' => $committeeTerm,
        ]);
    }

    public function update(Request $request, CommitteeTerm $committeeTerm): RedirectResponse
    {
        $this->authorize('update', $committeeTerm);

        $data = $this->validated($request, $committeeTerm);
        $this->applyCurrentTerm($data, $committeeTerm);
        $committeeTerm->update($data);

        return redirect()
            ->route('committee-terms.index')
            ->with('status', 'Committee term updated.');
    }

    public function destroy(CommitteeTerm $committeeTerm): RedirectResponse
    {
        $this->authorize('delete', $committeeTerm);

        if ($committeeTerm->memberships()->exists()) {
            return redirect()
                ->route('committee-terms.index')
                ->with('status', 'Cannot delete a term that has committee roster history.');
        }

        $committeeTerm->delete();

        return redirect()
            ->route('committee-terms.index')
            ->with('status', 'Committee term deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?CommitteeTerm $term = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:year_from'],
            'is_current' => ['sometimes', 'boolean'],
        ]) + [
            'is_current' => $request->boolean('is_current'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyCurrentTerm(array &$data, ?CommitteeTerm $except = null): void
    {
        if (empty($data['is_current'])) {
            return;
        }

        CommitteeTerm::query()
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['is_current' => false]);
    }
}
