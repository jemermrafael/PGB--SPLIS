<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\IncomingDocument;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Services\IncomingDocumentLinker;
use App\Services\IncomingDocumentPublisher;
use App\Services\PdfAttachmentService;
use App\Support\DocumentType;
use App\Support\IncomingFieldOptions;
use App\Support\ResolutionNumberParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomingDocumentController extends Controller
{
    public function __construct(
        protected PdfAttachmentService $pdfService,
    ) {}

    public function index(): View
    {
        return view('incoming.index', [
            'categories' => Category::orderBy('description')->get(),
            'departments' => Department::orderBy('description')->get(),
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', IncomingDocument::class);

        return view('incoming.form', $this->formData(new IncomingDocument));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', IncomingDocument::class);

        $data = $this->validated($request);
        $data['source'] = IncomingDocument::SOURCE_MANUAL;
        $data['link_status'] = IncomingDocument::LINK_UNLINKED;
        $data['created_by'] = $request->user()->id;

        $incoming = IncomingDocument::create($data);

        return redirect()
            ->route('incoming.show', $incoming)
            ->with('status', 'Incoming document created.');
    }

    public function show(IncomingDocument $incoming): View
    {
        $incoming->load(['resolution', 'creator']);

        return view('incoming.show', [
            'incoming' => $incoming,
            'previousIncoming' => $incoming->previousInList(),
            'nextIncoming' => $incoming->nextInList(),
        ]);
    }

    public function edit(IncomingDocument $incoming): View
    {
        $this->authorize('update', $incoming);

        return view('incoming.form', $this->formData($incoming));
    }

    public function update(Request $request, IncomingDocument $incoming): RedirectResponse
    {
        $this->authorize('update', $incoming);

        $incoming->update($this->validated($request));

        return redirect()
            ->route('incoming.show', $incoming)
            ->with('status', 'Incoming document updated.');
    }

    public function link(Request $request, IncomingDocument $incoming, IncomingDocumentLinker $linker): RedirectResponse
    {
        $this->authorize('link', $incoming);

        $data = $request->validate([
            'resolution_id' => ['required', 'integer', 'exists:resolutions,id'],
        ]);

        $resolution = Resolution::query()->findOrFail($data['resolution_id']);

        try {
            $linker->link($incoming, $resolution);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['resolution_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('incoming.show', $incoming)
            ->with('status', 'Linked to resolution '.$resolution->resolution_no.'.');
    }

    public function publish(IncomingDocument $incoming, IncomingDocumentPublisher $publisher): View
    {
        $this->authorize('publish', $incoming);

        return view('incoming.publish', array_merge(
            $this->resolutionFormData(),
            [
                'incoming' => $incoming,
                'resolution' => $publisher->prefill($incoming),
            ]
        ));
    }

    public function publishStore(
        Request $request,
        IncomingDocument $incoming,
        IncomingDocumentPublisher $publisher,
        IncomingDocumentLinker $linker,
    ): RedirectResponse {
        $this->authorize('publish', $incoming);
        $this->authorize('create', Resolution::class);

        $data = $this->validatedResolution($request);
        $data = array_merge($publisher->workflowAttributes($incoming), $data);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'draft';
        $data['document_type'] = DocumentType::infer($data['resolution_no'], $data['resolution_title']);

        $resolution = Resolution::create($data);

        if ($request->hasFile('pdf')) {
            $resolution->update([
                'pdf_path' => $this->pdfService->store($request->file('pdf'), $resolution->series, $resolution->resolution_no),
            ]);
        }

        $linker->link($incoming, $resolution);

        ActivityLog::record('resolution.published_from_incoming', $resolution, [
            'incoming_id' => $incoming->id,
            'resolution_no' => $resolution->resolution_no,
        ]);

        return redirect()
            ->route('resolutions.show', $resolution)
            ->with('status', 'Resolution published and linked to incoming '.$incoming->displayLabel().'.');
    }

    public function searchResolutions(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $series = $request->filled('series') ? (int) $request->input('series') : null;
        if ($series === null && preg_match('/^(\d{4})-/', $term, $m)) {
            $series = (int) $m[1];
        }

        $results = Resolution::query()
            ->whereNull('incoming_document_id')
            ->where(function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where('resolution_no', 'like', $like)
                    ->orWhere('resolution_title', 'like', $like);

                if (preg_match('/^(\d{4})-(\d+)$/', $term, $m)) {
                    $year = (int) $m[1];
                    $sequence = (int) $m[2];
                    $q->orWhere('resolution_no', ResolutionNumberParser::buildOfficialNumber($year, $sequence))
                        ->orWhere('resolution_no', 'like', '%'.$year.'%-%'.sprintf('%04d', $sequence))
                        ->orWhere('resolution_no', 'like', '%'.$year.'%-%'.$sequence);
                }

                $sequence = ResolutionNumberParser::extractSequence($term);
                if ($sequence !== null) {
                    $q->orWhere('resolution_no', 'like', '%'.sprintf('%04d', $sequence))
                        ->orWhere('resolution_no', 'like', '%-'.$sequence);
                }
            })
            ->when($series, fn ($q) => $q->where('series', $series))
            ->orderByDesc('series')
            ->limit(15)
            ->get(['id', 'resolution_no', 'series', 'resolution_title', 'date_approved']);

        return response()->json($results);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'mun_resolution_no' => ['nullable', 'string', 'max:100'],
            'date_received' => ['nullable', 'date'],
            'mun_series' => ['nullable', 'string', 'max:20'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:100'],
            'referral' => ['nullable', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:150'],
            'workflow_status' => ['nullable', 'string', 'max:50'],
            'sp_res_no' => ['nullable', 'string', 'max:50'],
            'sp_series' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sp_title' => ['nullable', 'string', 'max:5000'],
            'sp_date_approved' => ['nullable', 'date'],
            'keyword' => ['nullable', 'string', 'max:150'],
            'concerned_agency' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'mun_pdf_url' => ['nullable', 'string', 'max:500'],
            'sp_pdf_url' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolutionFormData(): array
    {
        return [
            'categories' => Category::orderBy('description')->get(),
            'departments' => Department::orderBy('description')->get(),
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedResolution(Request $request): array
    {
        $data = $request->validate([
            'resolution_no' => ['required', 'string', 'max:50'],
            'resolution_title' => ['required', 'string'],
            'series' => ['required', 'integer', 'min:1900', 'max:2100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'date_approved' => ['nullable', 'date'],
            'sponsored_by' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
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

    /**
     * @return array<string, mixed>
     */
    protected function formData(IncomingDocument $incoming): array
    {
        return [
            'incoming' => $incoming,
            'actionTakenOptions' => IncomingFieldOptions::actionTaken(),
            'referralOptions' => IncomingFieldOptions::referrals(),
            'concernedAgencyOptions' => IncomingFieldOptions::concernedAgencies(),
        ];
    }
}
