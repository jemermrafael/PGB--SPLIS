@php
    use App\Services\AgendaVersionService;

    $versionService = app(AgendaVersionService::class);
    $fieldLabels = AgendaVersionService::fieldLabels();
    $sortedVersions = $agenda->versions->sortBy('version_no')->values();

    $compareVersions = $sortedVersions->map(fn ($version) => [
        'version_no' => $version->version_no,
        'label' => sprintf(
            'v%s — %s · %s',
            $version->version_no,
            $version->changeReasonLabel(),
            $version->created_at?->format('M j, Y g:i A') ?? 'Unknown date',
        ),
        'snapshot' => $version->snapshot ?? [],
    ])->values();

    $formattedByVersion = $sortedVersions->mapWithKeys(function ($version) use ($versionService, $fieldLabels) {
        $values = [];

        foreach (array_keys($fieldLabels) as $field) {
            $values[$field] = $versionService->formatSnapshotDisplayValue($field, $version->snapshotValue($field));
        }

        return [$version->version_no => $values];
    });
@endphp

<div class="splis-card mt-6">
    <div class="splis-card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="splis-card-title">Version history</h2>
            <p class="splis-card-subtitle">Current version: v{{ $agenda->current_version_no }}</p>
        </div>
        @if ($sortedVersions->count() >= 2)
            <button type="button" id="agenda-version-compare-open" class="splis-btn-secondary text-sm">
                Compare versions
            </button>
        @endif
    </div>
    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th class="min-w-[12rem]">Title</th>
                    <th class="hidden md:table-cell">Reason</th>
                    <th class="hidden sm:table-cell">Status</th>
                    <th class="hidden lg:table-cell">Recorded by</th>
                    <th>Date</th>
                    @if (auth()->user()?->isSuperadmin())
                        <th class="w-20"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($agenda->versions as $version)
                    <tr @class(['bg-brand-50/40 dark:bg-brand-950/20' => $version->version_no === $agenda->current_version_no])>
                        <td class="whitespace-nowrap font-semibold">
                            v{{ $version->version_no }}
                            @if ($version->version_no === $agenda->current_version_no)
                                <span class="splis-badge splis-badge--muted ml-1">Current</span>
                            @endif
                        </td>
                        <td class="max-w-md">
                            <p class="line-clamp-2">{{ $version->snapshotTitle() ?? '—' }}</p>
                            @if ($version->snapshotOutputLabel() || $version->snapshotOutputTypeLabel())
                                <p class="mt-1 text-xs text-slate-500">
                                    Provincial Output:
                                    @if ($version->snapshotOutputTypeLabel())
                                        {{ $version->snapshotOutputTypeLabel() }}
                                    @endif
                                    @if ($version->snapshotOutputLabel())
                                        @if ($version->snapshotOutputTypeLabel()) · @endif
                                        {{ $version->snapshotOutputLabel() }}
                                    @endif
                                </p>
                            @endif
                            @if ($version->snapshotValue('request_pdf_url'))
                                @include('partials.pdf-modal-trigger', [
                                    'url' => $version->snapshotValue('request_pdf_url'),
                                    'title' => 'Request PDF — version '.$version->version_no,
                                    'label' => 'Request PDF',
                                    'class' => 'splis-link inline-flex items-center gap-1 text-xs',
                                ])
                            @endif
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $version->changeReasonLabel() }}</td>
                        <td class="hidden sm:table-cell whitespace-nowrap">
                            {{ config('agenda.statuses.'.$version->snapshotValue('status'), $version->snapshotValue('status', '—')) }}
                        </td>
                        <td class="hidden lg:table-cell">{{ $version->creator?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-sm text-slate-500">{{ $version->created_at?->format('M j, Y g:i A') }}</td>
                        @can('delete', $version)
                            <td class="whitespace-nowrap text-right">
                                @if ($agenda->versions->count() > 1)
                                    <form
                                        method="POST"
                                        action="{{ route('agenda.versions.destroy', [$agenda, $version]) }}"
                                        data-confirm-submit
                                        data-confirm-title="Delete agenda version?"
                                        data-confirm-message="Delete version v{{ $version->version_no }}?{{ $version->version_no === $agenda->current_version_no ? ' The agenda will revert to the previous version.' : '' }}"
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
                        <td colspan="{{ auth()->user()?->isSuperadmin() ? 7 : 6 }}" class="py-8 text-center text-sm text-slate-500">No versions recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($sortedVersions->count() >= 2)
    <div
        id="agenda-version-compare"
        data-versions='@json($compareVersions)'
        data-field-labels='@json($fieldLabels)'
        data-formatted='@json($formattedByVersion)'
        hidden
    ></div>

    <div id="agenda-version-compare-modal" class="splis-modal" hidden>
        <div class="splis-modal-backdrop" data-modal-close tabindex="-1" aria-hidden="true"></div>
        <div class="splis-modal-panel" role="dialog" aria-modal="true" aria-labelledby="agenda-version-compare-title">
            <div class="splis-modal-header">
                <h3 id="agenda-version-compare-title" class="splis-modal-title">Compare versions</h3>
                <button type="button" class="splis-modal-close" data-modal-close aria-label="Close">×</button>
            </div>
            <div class="splis-modal-body">
                <div id="agenda-version-compare-selectors" class="splis-version-compare-selectors">
                    <label class="splis-version-compare-select">
                        <span class="splis-label">Left version</span>
                        <select id="agenda-version-compare-left" class="splis-select"></select>
                    </label>
                    <label class="splis-version-compare-select">
                        <span class="splis-label">Right version</span>
                        <select id="agenda-version-compare-right" class="splis-select"></select>
                    </label>
                </div>
                <div id="agenda-version-compare-results" class="splis-version-compare-results"></div>
            </div>
        </div>
    </div>
@endif
