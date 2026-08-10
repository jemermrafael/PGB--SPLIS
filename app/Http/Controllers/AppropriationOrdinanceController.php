<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppropriationOrdinance;
use App\Models\AppropriationOrdinanceVersion;
use App\Services\AppropriationOrdinancePdfService;
use App\Services\AppropriationOrdinanceVersionService;
use App\Support\TrashActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppropriationOrdinanceController extends Controller
{
    public function __construct(
        protected AppropriationOrdinancePdfService $pdfService,
        protected AppropriationOrdinanceVersionService $versionService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', AppropriationOrdinance::class);

        $seriesYears = AppropriationOrdinance::query()
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year');

        return view('appropriation-ordinances.index', [
            'seriesYears' => $seriesYears,
        ]);
    }

    public function show(AppropriationOrdinance $appropriationOrdinance): View
    {
        $this->authorize('view', $appropriationOrdinance);

        $appropriationOrdinance->load(['versions.creator']);

        return view('appropriation-ordinances.show', [
            'appropriationOrdinance' => $appropriationOrdinance,
            'previousAppropriationOrdinance' => $appropriationOrdinance->trashed() ? null : $appropriationOrdinance->previousInList(),
            'nextAppropriationOrdinance' => $appropriationOrdinance->trashed() ? null : $appropriationOrdinance->nextInList(),
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
        $this->storeUploadedPdf($request, $appropriationOrdinance);
        $this->versionService->recordInitialVersion($appropriationOrdinance, $request->user()->id);

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with('status', 'Appropriation Ordinance created.');
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

        $before = collect(AppropriationOrdinanceVersionService::VERSIONED_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $appropriationOrdinance->getAttribute($field)])
            ->all();

        if ($request->hasFile('pdf')) {
            $this->versionService->preservePdfInCurrentVersion($appropriationOrdinance, $request->user()->id);
        }

        $appropriationOrdinance->update($this->validated($request, $appropriationOrdinance));
        $this->storeUploadedPdf($request, $appropriationOrdinance);
        $appropriationOrdinance->refresh();
        $this->versionService->recordVersionIfChanged(
            $appropriationOrdinance,
            $before,
            $request->user()->id,
        );

        ActivityLog::record('appropriation_ordinance.updated', $appropriationOrdinance, [
            'ordinance_no' => $appropriationOrdinance->ordinance_no,
            'series_year' => $appropriationOrdinance->series_year,
        ]);

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with('status', 'Appropriation Ordinance updated.');
    }

    public function destroyVersion(
        AppropriationOrdinance $appropriationOrdinance,
        AppropriationOrdinanceVersion $version,
    ): RedirectResponse {
        abort_unless($version->appropriation_ordinance_id === $appropriationOrdinance->id, 404);
        $this->authorize('delete', $version);

        try {
            $this->versionService->deleteVersion($version);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('appropriation-ordinances.show', $appropriationOrdinance)
            ->with('status', 'Version v'.$version->version_no.' deleted.');
    }

    public function destroy(AppropriationOrdinance $appropriationOrdinance): RedirectResponse
    {
        $this->authorize('delete', $appropriationOrdinance);

        TrashActivity::record('appropriation_ordinance.trashed', $appropriationOrdinance);
        $appropriationOrdinance->delete();

        return redirect()
            ->route(auth()->user()?->isSuperadmin() ? 'admin.trash.index' : 'appropriation-ordinances.index', auth()->user()?->isSuperadmin() ? ['type' => 'appropriation-ordinances'] : [])
            ->with('status', 'Appropriation Ordinance moved to trash.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?AppropriationOrdinance $record = null): array
    {
        $seriesYear = (int) $request->input('series_year');

        $validated = $request->validate([
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
            'pdf' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp', 'max:307200'],
        ]);

        unset($validated['pdf']);

        return $validated;
    }

    protected function storeUploadedPdf(Request $request, AppropriationOrdinance $record): void
    {
        if (! $request->hasFile('pdf')) {
            return;
        }

        $path = $this->pdfService->storeVersioned($request->file('pdf'), $record);

        $record->update(['pdf_path' => $path]);
    }
}
