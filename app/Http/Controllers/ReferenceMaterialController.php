<?php

namespace App\Http\Controllers;

use App\Models\ReferenceMaterial;
use App\Models\ReferenceMaterialVersion;
use App\Services\PdfTextExtractor;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReferenceMaterialController extends Controller
{
    public function __construct(
        protected PdfTextExtractor $pdfTextExtractor,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReferenceMaterial::class);

        $query = ReferenceMaterial::query()->with(['supersedes', 'latestVersion']);

        if ($q = trim((string) $request->input('q', ''))) {
            $query->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')
                    ->orWhere('reference_no', 'like', '%'.$q.'%')
                    ->orWhere('issuing_office', 'like', '%'.$q.'%')
                    ->orWhere('summary', 'like', '%'.$q.'%')
                    ->orWhere('keywords', 'like', '%'.$q.'%')
                    ->orWhereHas('versions', fn ($versionQuery) => $versionQuery->where('extracted_text', 'like', '%'.$q.'%'));
            });
        }

        if ($type = trim((string) $request->input('document_type', ''))) {
            $query->where('document_type', $type);
        }

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($office = trim((string) $request->input('issuing_office', ''))) {
            $query->where('issuing_office', $office);
        }

        if ($year = (int) $request->input('year', 0)) {
            $query->whereYear('date_issued', $year);
        }

        $materials = $query
            ->orderByDesc('date_issued')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('references.index', [
            'materials' => $materials,
            'documentTypes' => config('reference_materials.document_types', []),
            'statuses' => config('reference_materials.statuses', []),
            'offices' => ReferenceMaterial::query()
                ->whereNotNull('issuing_office')
                ->where('issuing_office', '!=', '')
                ->distinct()
                ->orderBy('issuing_office')
                ->pluck('issuing_office'),
            'years' => ReferenceMaterial::query()
                ->whereNotNull('date_issued')
                ->selectRaw('YEAR(date_issued) as year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year'),
            'filters' => [
                'q' => $request->input('q', ''),
                'document_type' => $request->input('document_type', ''),
                'status' => $request->input('status', ''),
                'issuing_office' => $request->input('issuing_office', ''),
                'year' => $request->input('year', ''),
            ],
        ]);
    }

    public function show(ReferenceMaterial $reference): View
    {
        $this->authorize('view', $reference);

        $reference->load(['supersedes', 'supersededBy', 'versions.creator']);

        return view('references.show', [
            'reference' => $reference,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ReferenceMaterial::class);

        return view('references.form', [
            'reference' => new ReferenceMaterial(['status' => 'active']),
            'documentTypes' => config('reference_materials.document_types', []),
            'statuses' => config('reference_materials.statuses', []),
            'supersedesOptions' => ReferenceMaterial::query()
                ->orderByDesc('date_issued')
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'title', 'reference_no']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ReferenceMaterial::class);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;
        $data['archived_at'] = ($data['status'] ?? 'active') === 'archived' ? now() : null;

        if ($request->hasFile('file')) {
            $data = array_merge($data, $this->storeFile($request));
        }

        $reference = ReferenceMaterial::query()->create($data);
        if (! empty($data['file_path'])) {
            $this->createVersion($reference, $data, $request->user()?->id);
        }

        ActivityLogger::log('reference_material.created', $reference, [
            'title' => $reference->title,
            'status' => $reference->status,
        ]);

        return redirect()
            ->route('references.show', $reference)
            ->with('status', 'Reference material created.');
    }

    public function edit(ReferenceMaterial $reference): View
    {
        $this->authorize('update', $reference);

        return view('references.form', [
            'reference' => $reference,
            'documentTypes' => config('reference_materials.document_types', []),
            'statuses' => config('reference_materials.statuses', []),
            'supersedesOptions' => ReferenceMaterial::query()
                ->where('id', '!=', $reference->id)
                ->orderByDesc('date_issued')
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'title', 'reference_no']),
        ]);
    }

    public function update(Request $request, ReferenceMaterial $reference): RedirectResponse
    {
        $this->authorize('update', $reference);

        $data = $this->validated($request, $reference);
        $data['updated_by'] = $request->user()?->id;
        $data['archived_at'] = ($data['status'] ?? $reference->status) === 'archived'
            ? ($reference->archived_at ?? now())
            : null;

        if ($request->hasFile('file')) {
            $stored = $this->storeFile($request);
            if ($reference->file_path && Storage::disk('local')->exists($reference->file_path)) {
                Storage::disk('local')->delete($reference->file_path);
            }
            $data = array_merge($data, $stored);
        }

        $reference->update($data);
        if (! empty($data['file_path'])) {
            $this->createVersion($reference, $data, $request->user()?->id);
        }

        ActivityLogger::log('reference_material.updated', $reference, [
            'title' => $reference->title,
            'status' => $reference->status,
        ]);

        return redirect()
            ->route('references.show', $reference)
            ->with('status', 'Reference material updated.');
    }

    public function archive(ReferenceMaterial $reference): RedirectResponse
    {
        $this->authorize('archive', $reference);

        $reference->update([
            'status' => 'archived',
            'archived_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        ActivityLogger::log('reference_material.archived', $reference, [
            'title' => $reference->title,
        ]);

        return redirect()
            ->route('references.show', $reference)
            ->with('status', 'Reference material archived.');
    }

    public function restore(ReferenceMaterial $reference): RedirectResponse
    {
        $this->authorize('restore', $reference);

        $reference->update([
            'status' => 'active',
            'archived_at' => null,
            'updated_by' => auth()->id(),
        ]);

        ActivityLogger::log('reference_material.restored', $reference, [
            'title' => $reference->title,
        ]);

        return redirect()
            ->route('references.show', $reference)
            ->with('status', 'Reference material restored.');
    }

    public function destroy(ReferenceMaterial $reference): RedirectResponse
    {
        $this->authorize('delete', $reference);

        $reference->delete();

        ActivityLogger::log('reference_material.deleted', $reference, [
            'title' => $reference->title,
        ]);

        return redirect()
            ->route('references.index')
            ->with('status', 'Reference material deleted.');
    }

    public function download(ReferenceMaterial $reference)
    {
        $this->authorize('view', $reference);
        abort_unless($reference->file_path && Storage::disk('local')->exists($reference->file_path), 404);

        return Storage::disk('local')->download(
            $reference->file_path,
            $reference->original_filename ?: basename($reference->file_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?ReferenceMaterial $reference = null): array
    {
        $documentTypes = array_keys(config('reference_materials.document_types', []));
        $statuses = array_keys(config('reference_materials.statuses', []));

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in($documentTypes)],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'issuing_office' => ['nullable', 'string', 'max:200'],
            'date_issued' => ['nullable', 'date'],
            'effective_date' => ['nullable', 'date'],
            'summary' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'version_no' => ['nullable', 'string', 'max:30'],
            'supersedes_reference_material_id' => [
                'nullable',
                'integer',
                'exists:reference_materials,id',
                Rule::notIn([$reference?->id]),
            ],
            'status' => ['required', 'string', Rule::in($statuses)],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ]);
    }

    /**
     * @return array{file_path: string, original_filename: string, mime_type: string, file_size: int}
     */
    protected function storeFile(Request $request): array
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $name = now()->format('YmdHis').'-'.Str::random(8).($ext ? '.'.$ext : '');
        $path = $file->storeAs('reference-materials/'.now()->format('Y/m'), $name, 'local');

        return [
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getClientMimeType(),
            'file_size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param  array{file_path: string, original_filename: string, mime_type: string, file_size: int}  $stored
     */
    protected function createVersion(ReferenceMaterial $reference, array $stored, ?int $userId): void
    {
        $version = $reference->version_no ?: $this->nextVersionNo($reference);
        $reference->updateQuietly(['version_no' => $version]);

        $absolutePath = Storage::disk('local')->path($stored['file_path']);
        $extractedText = str_ends_with(strtolower($stored['mime_type']), 'pdf')
            || str_ends_with(strtolower($stored['original_filename']), '.pdf')
            ? $this->pdfTextExtractor->extractFromPath($absolutePath)
            : '';

        ReferenceMaterialVersion::query()->updateOrCreate(
            [
                'reference_material_id' => $reference->id,
                'version_no' => $version,
            ],
            [
                'file_path' => $stored['file_path'],
                'original_filename' => $stored['original_filename'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                'extracted_text' => $extractedText,
                'created_by' => $userId,
            ],
        );
    }

    protected function nextVersionNo(ReferenceMaterial $reference): string
    {
        $latest = $reference->versions()->first();
        $raw = (string) ($latest?->version_no ?? '');

        if ($raw === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            return '1.0';
        }

        $number = (float) $raw + 1.0;

        return number_format($number, 1, '.', '');
    }
}

