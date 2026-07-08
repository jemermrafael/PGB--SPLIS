<div class="splis-card mt-6">
    <div class="splis-card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="splis-card-title">Version history</h2>
            <p class="splis-card-subtitle">Current version: v{{ $agenda->current_version_no }}</p>
        </div>
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
                            @if ($version->snapshotValue('request_pdf_url'))
                                <a href="{{ $version->snapshotValue('request_pdf_url') }}" target="_blank" rel="noopener" class="splis-link text-xs">Request PDF</a>
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
                                        onsubmit="return confirm('Delete version v{{ $version->version_no }}?{{ $version->version_no === $agenda->current_version_no ? ' The agenda will revert to the previous version.' : '' }}');"
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
