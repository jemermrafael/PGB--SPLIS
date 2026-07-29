@php
    use App\Services\ResolutionVersionService;

    $versionService = app(ResolutionVersionService::class);
    $sortedVersions = $resolution->versions->sortByDesc('version_no')->values();
    $previousByNo = $resolution->versions->sortBy('version_no')->values()->keyBy('version_no');
@endphp

<div class="splis-card mt-6">
    <div class="splis-card-header !border-b-0">
        <div>
            <h2 class="splis-card-title">Version History</h2>
            <p class="splis-card-subtitle">Current version: v{{ $resolution->current_version_no }}</p>
        </div>
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
                        $pdfUrl = $version->snapshotPdfUrl($resolution);
                    @endphp
                    <tr @class(['bg-brand-50/40 dark:bg-brand-950/20' => $version->version_no === $resolution->current_version_no])>
                        <td class="whitespace-nowrap font-semibold">
                            v{{ $version->version_no }}
                            @if ($version->version_no === $resolution->current_version_no)
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
                                        'title' => 'Resolution PDF — version '.$version->version_no,
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
                                        action="{{ route('resolutions.versions.destroy', [$resolution, $version]) }}"
                                        data-confirm-submit
                                        data-confirm-title="Delete resolution version?"
                                        data-confirm-message="Delete version v{{ $version->version_no }}?{{ $version->version_no === $resolution->current_version_no ? ' The resolution will revert to the previous version.' : '' }}"
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
