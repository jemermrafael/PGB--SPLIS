<?php

namespace App\Http\Controllers;

use App\Models\AppropriationOrdinance;
use App\Models\BoardMember;
use App\Models\Ordinance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppropriationOrdinanceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AppropriationOrdinance::class);

        $query = AppropriationOrdinance::query()->ordered();

        if ($request->filled('q')) {
            $query->search($request->string('q'));
        }

        if ($request->filled('series')) {
            $query->where('series_year', (int) $request->input('series'));
        }

        $seriesYears = AppropriationOrdinance::query()
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year');

        return view('appropriation-ordinances.index', [
            'seriesYears' => $seriesYears,
            'records' => $query->paginate(config('appropriation_ordinances.per_page', 15))->withQueryString(),
        ]);
    }

    public function show(AppropriationOrdinance $appropriationOrdinance): View
    {
        $this->authorize('view', $appropriationOrdinance);

        return view('appropriation-ordinances.show', [
            'appropriationOrdinance' => $appropriationOrdinance,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AppropriationOrdinance::class);

        return view('appropriation-ordinances.form', [
            'appropriationOrdinance' => new AppropriationOrdinance([
                'series_year' => (int) config('appropriation_ordinances.default_series_year', (int) now()->format('Y')),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AppropriationOrdinance::class);

        $appropriationOrdinance = AppropriationOrdinance::create(array_merge(
            $this->validated($request),
            ['created_by' => $request->user()->id],
        ));

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with('status', 'Appropriation ordinance created.');
    }

    public function edit(AppropriationOrdinance $appropriationOrdinance): View
    {
        $this->authorize('update', $appropriationOrdinance);

        return view('appropriation-ordinances.form', [
            'appropriationOrdinance' => $appropriationOrdinance,
        ]);
    }

    public function update(Request $request, AppropriationOrdinance $appropriationOrdinance): RedirectResponse
    {
        $this->authorize('update', $appropriationOrdinance);

        $appropriationOrdinance->update($this->validated($request, $appropriationOrdinance));

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with('status', 'Appropriation ordinance updated.');
    }

    public function destroy(AppropriationOrdinance $appropriationOrdinance): RedirectResponse
    {
        $this->authorize('delete', $appropriationOrdinance);

        $appropriationOrdinance->delete();

        return redirect()
            ->route('appropriation-ordinances.index')
            ->with('status', 'Appropriation ordinance deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?AppropriationOrdinance $record = null): array
    {
        $seriesYear = (int) $request->input('series_year');

        return $request->validate([
            'date_received' => ['nullable', 'date'],
            'subject' => ['required', 'string'],
            'ordinance_no' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('appropriation_ordinances', 'ordinance_no')
                    ->where(fn ($query) => $query->where('series_year', $seriesYear))
                    ->ignore($record?->id),
            ],
            'series_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'date_passed' => ['nullable', 'date'],
            'date_approved' => ['nullable', 'date'],
            'pdf_url' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
