<?php

namespace App\Http\Controllers;

use App\Enums\OrdinanceBoardMemberRole;
use App\Enums\OrdinancePublicationStatus;
use App\Models\ActivityLog;
use App\Models\Ordinance;
use App\Services\BoardMemberRosterService;
use App\Services\OrdinanceBoardMemberService;
use App\Support\TrashActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrdinanceController extends Controller
{
    public function __construct(
        protected BoardMemberRosterService $boardMemberRosterService,
        protected OrdinanceBoardMemberService $ordinanceBoardMemberService,
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

        $ordinance->load(['boardMembers', 'publishedFromAgenda']);

        return view('ordinances.show', [
            'ordinance' => $ordinance,
            'previousOrdinance' => $ordinance->trashed() ? null : $ordinance->previousInList(),
            'nextOrdinance' => $ordinance->trashed() ? null : $ordinance->nextInList(),
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
        $this->syncBoardMembers($ordinance, $request);

        ActivityLog::record('ordinance.created', $ordinance, [
            'ordinance_no' => $ordinance->ordinance_no,
            'series_year' => $ordinance->series_year,
        ]);

        return redirect()
            ->route('ordinances.index')
            ->with('status', 'Ordinance created.');
    }

    public function edit(Ordinance $ordinance): View
    {
        $this->authorize('update', $ordinance);

        $ordinance->load('boardMembers');

        return view('ordinances.form', $this->formData($ordinance));
    }

    public function update(Request $request, Ordinance $ordinance): RedirectResponse
    {
        $this->authorize('update', $ordinance);

        $ordinance->update($this->validatedOrdinance($request, $ordinance));
        $this->syncBoardMembers($ordinance, $request);

        return redirect()
            ->route('ordinances.show', $ordinance)
            ->with('status', 'Ordinance updated.');
    }

    public function destroy(Ordinance $ordinance): RedirectResponse
    {
        $this->authorize('delete', $ordinance);

        TrashActivity::record('ordinance.trashed', $ordinance);
        $ordinance->delete();

        return redirect()
            ->route(auth()->user()?->isSuperadmin() ? 'admin.trash.index' : 'ordinances.index', auth()->user()?->isSuperadmin() ? ['type' => 'ordinances'] : [])
            ->with('status', 'Ordinance moved to trash.');
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

        return [
            'ordinance' => $ordinance,
            'boardMembers' => $boardMembers,
            'rosterTerm' => $rosterTerm,
            'selectedAuthorIds' => $this->selectedMemberIds($ordinance, OrdinanceBoardMemberRole::Author),
            'selectedSponsorIds' => $this->selectedMemberIds($ordinance, OrdinanceBoardMemberRole::Sponsor),
            'selectedAuthoredSponsoredIds' => $this->selectedMemberIds($ordinance, OrdinanceBoardMemberRole::AuthoredSponsored),
        ];
    }

    /**
     * @return list<int>
     */
    protected function selectedMemberIds(Ordinance $ordinance, OrdinanceBoardMemberRole $role): array
    {
        $field = $role->formFieldName();
        $old = old($field);

        if (is_array($old)) {
            return array_values(array_map('intval', array_filter($old)));
        }

        if (! $ordinance->exists) {
            return [];
        }

        return $ordinance->membersForRole($role)->pluck('id')->all();
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

    protected function syncBoardMembers(Ordinance $ordinance, Request $request): void
    {
        $memberRules = [
            'author_member_ids' => ['nullable', 'array'],
            'author_member_ids.*' => ['integer', 'exists:board_members,id'],
            'sponsor_member_ids' => ['nullable', 'array'],
            'sponsor_member_ids.*' => ['integer', 'exists:board_members,id'],
            'authored_sponsored_member_ids' => ['nullable', 'array'],
            'authored_sponsored_member_ids.*' => ['integer', 'exists:board_members,id'],
        ];

        $validated = $request->validate($memberRules);

        $this->ordinanceBoardMemberService->sync(
            $ordinance,
            array_values(array_map('intval', $validated['author_member_ids'] ?? [])),
            array_values(array_map('intval', $validated['sponsor_member_ids'] ?? [])),
            array_values(array_map('intval', $validated['authored_sponsored_member_ids'] ?? [])),
        );
    }
}
