@php
    use App\Services\AppropriationOrdinanceVersionService;

    $versionService = app(AppropriationOrdinanceVersionService::class);
    $fieldLabels = AppropriationOrdinanceVersionService::fieldLabels();
    $sortedVersions = $appropriationOrdinance->versions->sortByDesc('version_no')->values();
    $ascendingVersions = $appropriationOrdinance->versions->sortBy('version_no')->values();
    $previousByNo = $ascendingVersions->keyBy('version_no');

    $compareVersions = $ascendingVersions->map(fn ($version) => [
        'version_no' => $version->version_no,
        'label' => sprintf(
            'v%s — %s · %s',
            $version->version_no,
            $version->changeReasonLabel(),
            $version->created_at?->format('M j, Y g:i A') ?? 'Unknown date',
        ),
        'snapshot' => $version->snapshot ?? [],
    ])->values();

    $formattedByVersion = $ascendingVersions->mapWithKeys(function ($version) use ($versionService, $fieldLabels) {
        $values = [];

        foreach (array_keys($fieldLabels) as $field) {
            $values[$field] = $versionService->formatSnapshotDisplayValue($field, $version->snapshotValue($field));
        }

        return [$version->version_no => $values];
    });
@endphp

<div class="splis-card mt-6 overflow-hidden">
    <div class="splis-card-header splis-card-header--emphasis flex flex-wrap items-center justify-between gap-3 !border-b-0">
        <div>
            <h2 class="splis-card-title">Version History</h2>
            <p class="splis-card-subtitle">Current version: v{{ $appropriationOrdinance->current_version_no }}</p>
        </div>
        @if ($ascendingVersions->count() >= 2)
            <button type="button" id="appropriation-ordinance-version-compare-open" class="splis-btn-secondary text-sm">
                Compare versions
            </button>
        @endif
    </div>

    @if ($errors->has('version'))
        <div class="splis-alert-error mx-5 mb-4">{{ $errors->first('version') }}</div>
    @endif

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th class="min-w-[12rem]">What's changed</th>
                    <th class="hidden md:table-cell">Reason</th>
                    <th class="hidden lg:table-cell">Edited by</th>
                    <th>Date / time</th>
                    @if (auth()->user()?->isSuperadmin())
                        <th class="w-20"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($sortedVersions as $version)
                    @php
                        $previous = $previousByNo->get($version->version_no - 1);
                        $changes = $versionService->changedFields($previous, $version);
                        $pdfUrl = $version->snapshotPdfUrl($appropriationOrdinance);
                    @endphp
                    <tr @class(['bg-brand-50/40 dark:bg-brand-950/20' => $version->version_no === $appropriationOrdinance->current_version_no])>
                        <td class="whitespace-nowrap font-semibold">
                            v{{ $version->version_no }}
                            @if ($version->version_no === $appropriationOrdinance->current_version_no)
                                <span class="splis-badge splis-badge--muted ml-1">Current</span>
                            @endif
                        </td>
                        <td class="max-w-lg">
                            @if ($version->version_no === 1 || $changes === [])
                                <p class="line-clamp-2">{{ $version->snapshotTitle() ?? '—' }}</p>
                                @if ($version->version_no === 1)
                                    <p class="mt-1 text-xs text-slate-500">Initial snapshot</p>
                                @endif
                            @else
                                <ul class="space-y-1 text-sm">
                                    @foreach (array_slice($changes, 0, 6) as $change)
                                        <li>
                                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $change['label'] }}</span>
                                            <span class="text-slate-500">:</span>
                                            <span class="text-slate-500">{{ $change['from'] ?? '—' }}</span>
                                            <span class="text-slate-400">→</span>
                                            <span class="text-slate-800 dark:text-slate-100">{{ $change['to'] ?? '—' }}</span>
                                        </li>
                                    @endforeach
                                    @if (count($changes) > 6)
                                        <li class="text-xs text-slate-500">+{{ count($changes) - 6 }} more field(s)</li>
                                    @endif
                                </ul>
                            @endif
                            @if ($pdfUrl)
                                <div class="mt-2">
                                    @include('partials.pdf-modal-trigger', [
                                        'url' => $pdfUrl,
                                        'title' => 'Appropriation Ordinance PDF — version '.$version->version_no,
                                        'label' => 'PDF',
                                        'class' => 'splis-link inline-flex items-center gap-1 text-xs',
                                    ])
                                </div>
                            @endif
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $version->changeReasonLabel() }}</td>
                        <td class="hidden lg:table-cell">{{ $version->creator?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-sm text-slate-500">{{ $version->created_at?->format('M j, Y g:i A') }}</td>
                        @can('delete', $version)
                            <td class="whitespace-nowrap text-right">
                                @if ($sortedVersions->count() > 1)
                                    <form
                                        method="POST"
                                        action="{{ route('appropriation-ordinances.versions.destroy', [$appropriationOrdinance, $version]) }}"
                                        data-confirm-submit
                                        data-confirm-title="Delete appropriation ordinance version?"
                                        data-confirm-message="Delete version v{{ $version->version_no }}?{{ $version->version_no === $appropriationOrdinance->current_version_no ? ' The appropriation ordinance will revert to the previous version.' : '' }}"
                                        data-confirm-label="Delete"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="splis-btn-ghost text-sm text-red-600 hover:text-red-700">Delete</button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->isSuperadmin() ? 6 : 5 }}" class="py-8 text-center text-sm text-slate-500">No versions recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($ascendingVersions->count() >= 2)
    <div
        id="appropriation-ordinance-version-compare"
        data-versions='@json($compareVersions)'
        data-field-labels='@json($fieldLabels)'
        data-formatted='@json($formattedByVersion)'
        hidden
    ></div>

    <div id="appropriation-ordinance-version-compare-modal" class="splis-modal" hidden>
        <div class="splis-modal-backdrop" data-modal-close tabindex="-1" aria-hidden="true"></div>
        <div class="splis-modal-panel" role="dialog" aria-modal="true" aria-labelledby="appropriation-ordinance-version-compare-title">
            <div class="splis-modal-header">
                <h3 id="appropriation-ordinance-version-compare-title" class="splis-modal-title">Compare versions</h3>
                <button type="button" class="splis-modal-close" data-modal-close aria-label="Close">×</button>
            </div>
            <div class="splis-modal-body">
                <div id="appropriation-ordinance-version-compare-selectors" class="splis-version-compare-selectors">
                    <label class="splis-version-compare-select">
                        <span class="splis-label">Left version</span>
                        <select id="appropriation-ordinance-version-compare-left" class="splis-select"></select>
                    </label>
                    <label class="splis-version-compare-select">
                        <span class="splis-label">Right version</span>
                        <select id="appropriation-ordinance-version-compare-right" class="splis-select"></select>
                    </label>
                </div>
                <div id="appropriation-ordinance-version-compare-results" class="splis-version-compare-results"></div>
            </div>
        </div>
    </div>
@endif
