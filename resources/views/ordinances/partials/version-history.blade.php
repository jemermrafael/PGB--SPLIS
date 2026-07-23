@php
    use App\Support\OrdinancePdfType;

    $sortedVersions = $ordinance->versions->sortByDesc('version_no')->values();
@endphp

<div class="splis-card mt-6">
    <div class="splis-card-header">
        <div>
            <h2 class="splis-card-title">Version History</h2>
            <p class="splis-card-subtitle">Title and PDF changes — current version: v{{ $ordinance->current_version_no }}</p>
        </div>
    </div>
    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th class="min-w-[12rem]">Title</th>
                    <th class="min-w-[10rem]">PDFs</th>
                    <th class="hidden md:table-cell">Reason</th>
                    <th class="hidden lg:table-cell">Recorded by</th>
                    <th>Date</th>
                    @if (auth()->user()?->isSuperadmin())
                        <th class="w-20"></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($sortedVersions as $version)
                    <tr @class(['bg-brand-50/40 dark:bg-brand-950/20' => $version->version_no === $ordinance->current_version_no])>
                        <td class="whitespace-nowrap font-semibold">
                            v{{ $version->version_no }}
                            @if ($version->version_no === $ordinance->current_version_no)
                                <span class="splis-badge splis-badge--muted ml-1">Current</span>
                            @endif
                        </td>
                        <td class="max-w-md">
                            <p class="line-clamp-2">{{ $version->snapshotTitle() ?? '—' }}</p>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                @php $hasPdfLink = false; @endphp
                                @foreach (OrdinancePdfType::all() as $type)
                                    @php
                                        $pdfUrl = $version->snapshotPdfUrl($type, $ordinance);
                                        $label = OrdinancePdfType::config($type)['label'];
                                    @endphp
                                    @if ($pdfUrl)
                                        @php $hasPdfLink = true; @endphp
                                        @include('partials.pdf-modal-trigger', [
                                            'url' => $pdfUrl,
                                            'title' => $label.' — version '.$version->version_no,
                                            'label' => $label,
                                            'class' => 'splis-link inline-flex items-center gap-1 whitespace-nowrap text-xs',
                                        ])
                                    @endif
                                @endforeach
                                @unless ($hasPdfLink)
                                    <span class="text-xs text-slate-400">—</span>
                                @endunless
                            </div>
                        </td>
                        <td class="hidden md:table-cell whitespace-nowrap">{{ $version->changeReasonLabel() }}</td>
                        <td class="hidden lg:table-cell">{{ $version->creator?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-sm text-slate-500">{{ $version->created_at?->format('M j, Y g:i A') }}</td>
                        @can('delete', $version)
                            <td class="whitespace-nowrap text-right">
                                @if ($sortedVersions->count() > 1)
                                    <form
                                        method="POST"
                                        action="{{ route('ordinances.versions.destroy', [$ordinance, $version]) }}"
                                        data-confirm-submit
                                        data-confirm-title="Delete ordinance version?"
                                        data-confirm-message="Delete version v{{ $version->version_no }}?{{ $version->version_no === $ordinance->current_version_no ? ' The ordinance title and PDFs will revert to the previous version.' : '' }}"
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
