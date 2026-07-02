<?php

namespace App\Http\Controllers;

use App\Enums\OrdinancePublicationStatus;
use App\Models\Ordinance;
use App\Services\BoardMemberRosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrdinanceController extends Controller
{
    public function __construct(
        protected BoardMemberRosterService $boardMemberRosterService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Ordinance::class);

        $seriesYears = Ordinance::query()
            ->select('series_year')
            ->distinct()
            ->orderByDesc('series_year')
            ->pluck('series_year');

        return view('ordinances.index', [
            'seriesYears' => $seriesYears,
            'classifications' => config('ordinances.classifications', []),
            'publicationStatuses' => \App\Enums\OrdinancePublicationStatus::cases(),
        ]);
    }

    public function show(Ordinance $ordinance): View
    {
        $this->authorize('view', $ordinance);

        $ordinance->load('authoredSponsoredMembers');

        return view('ordinances.show', [
            'ordinance' => $ordinance,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Ordinance::class);

        return view('ordinances.form', $this->formData(new Ordinance([
            'series_year' => (int) config('ordinances.default_series_year', (int) now()->format('Y')),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ordinance::class);

        $ordinance = Ordinance::create($this->validatedOrdinance($request));
        $this->syncAuthoredSponsoredMembers($ordinance, $this->validatedMemberIds($request));

        return redirect()
            ->route('ordinances.index')
            ->with('status', 'Ordinance created.');
    }

    public function edit(Ordinance $ordinance): View
    {
        $this->authorize('update', $ordinance);

        $ordinance->load('authoredSponsoredMembers');

        return view('ordinances.form', $this->formData($ordinance));
    }

    public function update(Request $request, Ordinance $ordinance): RedirectResponse
    {
        $this->authorize('update', $ordinance);

        $ordinance->update($this->validatedOrdinance($request, $ordinance));
        $this->syncAuthoredSponsoredMembers($ordinance, $this->validatedMemberIds($request));

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('status', 'Ordinance updated.');
    }

    public function destroy(Ordinance $ordinance): RedirectResponse
    {
        $this->authorize('delete', $ordinance);

        $ordinance->delete();

        return redirect()
            ->route('ordinances.index')
            ->with('status', 'Ordinance deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(Ordinance $ordinance): array
    {
        $seriesYear = (int) old('series_year', $ordinance->series_year ?: config('ordinances.default_series_year', (int) now()->format('Y')));
        $rosterTerm = $this->boardMemberRosterService->termForSeriesYear($seriesYear);
        $boardMembers = $this->boardMemberRosterService
            ->activeMembersForTermQuery($rosterTerm)
            ->get();

        $selectedMembers = $ordinance->relationLoaded('authoredSponsoredMembers')
            ? $ordinance->authoredSponsoredMembers
            : collect();

        return [
            'ordinance' => $ordinance,
            'boardMembers' => $boardMembers,
            'rosterTerm' => $rosterTerm,
            'selectedMemberId1' => old('authored_sponsored_member_id_1', $selectedMembers->get(0)?->id),
            'selectedMemberId2' => old('authored_sponsored_member_id_2', $selectedMembers->get(1)?->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedOrdinance(Request $request, ?Ordinance $ordinance = null): array
    {
        $classifications = config('ordinances.classifications', []);
        $seriesYear = (int) $request->input('series_year');

        return $request->validate([
            'ordinance_no' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('ordinances', 'ordinance_no')
                    ->where(fn ($query) => $query->where('series_year', $seriesYear))
                    ->ignore($ordinance?->id),
            ],
            'series_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'subject' => ['nullable', 'string'],
            'publication_status' => ['nullable', 'string', Rule::in(array_column(OrdinancePublicationStatus::cases(), 'value'))],
            'pdf_url' => ['nullable', 'string', 'max:500'],
            'date_enacted' => ['nullable', 'date'],
            'date_approved' => ['nullable', 'date'],
            'date_posted' => ['nullable', 'date'],
            'date_published_newspaper' => ['nullable', 'date'],
            'effectivity_date' => ['nullable', 'date'],
            'mov_bulletin' => ['nullable', 'string'],
            'mov_bulletin_url' => ['nullable', 'string', 'max:500'],
            'mov_certification' => ['nullable', 'string', 'max:200'],
            'mov_certification_url' => ['nullable', 'string', 'max:500'],
            'mov_newspaper' => ['nullable', 'string', 'max:200'],
            'mov_newspaper_url' => ['nullable', 'string', 'max:500'],
            'implementing_bodies' => ['nullable', 'string'],
            'classification' => ['nullable', 'string', Rule::in($classifications)],
            'mandate_ppa' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return list<int>
     */
    protected function validatedMemberIds(Request $request): array
    {
        $validated = $request->validate([
            'authored_sponsored_member_id_1' => ['nullable', 'integer', 'exists:board_members,id'],
            'authored_sponsored_member_id_2' => [
                'nullable',
                'integer',
                'exists:board_members,id',
                'different:authored_sponsored_member_id_1',
            ],
        ]);

        return array_values(array_filter([
            $validated['authored_sponsored_member_id_1'] ?? null,
            $validated['authored_sponsored_member_id_2'] ?? null,
        ]));
    }

    /**
     * @param  list<int>  $memberIds
     */
    protected function syncAuthoredSponsoredMembers(Ordinance $ordinance, array $memberIds): void
    {
        $sync = [];

        foreach ($memberIds as $index => $memberId) {
            $sync[$memberId] = ['sort_order' => $index];
        }

        $ordinance->authoredSponsoredMembers()->sync($sync);
    }
}
