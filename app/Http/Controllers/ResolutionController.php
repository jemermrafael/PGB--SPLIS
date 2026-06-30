<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Services\PdfAttachmentService;
use App\Services\ResolutionRepository;
use App\Support\DocumentType;
use App\Support\IncomingFieldOptions;
use App\Support\ResolutionFieldOptions;
use App\Support\ResolutionLookupResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResolutionController extends Controller
{
    public function __construct(
        protected ResolutionRepository $repository,
        protected PdfAttachmentService $pdfService,
    ) {}

    public function index(): View
    {
        return view('resolutions.index', [
            'categories' => Category::forSelect(),
            'departments' => Department::orderBy('description')->get(),
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year'),
        ]);
    }

    public function showLegacy(int $id): View|RedirectResponse
    {
        $migrated = $this->repository->findByLegacyId($id);
        if ($migrated) {
            return redirect()->route('resolutions.show', $migrated);
        }

        $resolution = $this->repository->findLegacy($id);
        abort_if(! $resolution, 404);

        $labels = $this->repository->legacyLookupLabels($resolution);
        $hasPdf = $this->pdfService->exists((int) $resolution->Series, trim((string) $resolution->Resolution_No));

        return view('resolutions.show-legacy', compact('resolution', 'labels', 'hasPdf'));
    }

    public function show(Resolution $resolution): View
    {
        $resolution->load(['department', 'category', 'category2', 'category3', 'category4', 'municipality', 'creator', 'incomingDocument']);
        $hasPdf = $this->pdfService->existsFor($resolution);

        return view('resolutions.show', [
            'resolution' => $resolution,
            'hasPdf' => $hasPdf,
            'previousResolution' => $resolution->previousInList(),
            'nextResolution' => $resolution->nextInList(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Resolution::class);

        return view('resolutions.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Resolution::class);

        $data = $this->validated($request);
        $data = ResolutionLookupResolver::apply($data);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'draft';
        $data['document_type'] = DocumentType::infer($data['resolution_no'], $data['resolution_title']);

        $resolution = Resolution::create($data);

        if ($request->hasFile('pdf')) {
            $resolution->update([
                'pdf_path' => $this->pdfService->store($request->file('pdf'), $resolution->series, $resolution->resolution_no),
            ]);
        }

        ActivityLog::record('resolution.created', $resolution, ['resolution_no' => $resolution->resolution_no]);

        return redirect()->route('resolutions.show', $resolution)->with('status', 'Resolution created.');
    }

    public function edit(Resolution $resolution): View
    {
        $this->authorize('update', $resolution);

        return view('resolutions.form', $this->formData($resolution));
    }

    public function update(Request $request, Resolution $resolution): RedirectResponse
    {
        $this->authorize('update', $resolution);

        $data = $this->validated($request);
        $data = ResolutionLookupResolver::apply($data);
        $data['document_type'] = DocumentType::infer($data['resolution_no'], $data['resolution_title']);
        $resolution->update($data);

        if ($request->hasFile('pdf')) {
            $resolution->update([
                'pdf_path' => $this->pdfService->store($request->file('pdf'), $resolution->series, $resolution->resolution_no),
            ]);
        }

        ActivityLog::record('resolution.updated', $resolution);

        return redirect()->route('resolutions.show', $resolution)->with('status', 'Resolution updated.');
    }

    public function destroy(Resolution $resolution): RedirectResponse
    {
        $this->authorize('delete', $resolution);

        ActivityLog::record('resolution.deleted', $resolution);
        $resolution->delete();

        return redirect()->route('resolutions.index')->with('status', 'Resolution deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'resolution_no' => ['required', 'string', 'max:50'],
            'resolution_title' => ['required', 'string'],
            'series' => ['required', 'integer', 'min:1900', 'max:2100'],
            'department' => ['nullable', 'string', 'max:200'],
            'date_approved' => ['nullable', 'date'],
            'sponsored_by' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:200'],
            'category2_id' => ['nullable', 'exists:category2s,id'],
            'category3_id' => ['nullable', 'exists:category3s,id'],
            'category4_id' => ['nullable', 'exists:category4s,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'committee' => ['nullable', 'string', 'max:100'],
            'app_ord_no' => ['nullable', 'string', 'max:20'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'municipality_id' => ['nullable', 'exists:municipalities,id'],
            'status' => ['nullable', 'in:draft,approved,archived'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:204800'],
        ]);

        $data['province'] = $request->boolean('province');

        return $data;
    }

    protected function formData(?Resolution $resolution = null): array
    {
        $resolution = ($resolution ?? new Resolution)->loadMissing(['category', 'department']);

        return [
            'resolution' => $resolution,
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year'),
            'categoryOptions' => ResolutionFieldOptions::categories(),
            'departmentOptions' => ResolutionFieldOptions::departments(),
            'sponsoredByOptions' => ResolutionFieldOptions::sponsoredBy(),
            'committeeOptions' => IncomingFieldOptions::committees(),
            'keywordsUrl' => route('incoming.keywords'),
        ];
    }
}
