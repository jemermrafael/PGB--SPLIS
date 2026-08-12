@extends('layouts.app')

@section('title', 'Committee Reports — '.config('app.name'))

@section('content')
<div class="max-w-5xl">
    <div class="splis-page-header">
        <x-page-heading
            title="Committee Reports"
            subtitle="Upload PDF reports, tag your Committee Agendas, and attach them to Session Committee Reports."
            icon="file-text"
            page="board_member_committee_reports"
        />
        @can('create', App\Models\BoardMemberCommitteeReport::class)
            <a href="{{ route('board-member.committee-reports.create') }}" class="splis-btn-primary inline-flex items-center gap-2 whitespace-nowrap">
                <x-icon name="plus" class="h-4 w-4" />
                Submit Report
            </a>
        @endcan
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table">
            <thead>
                <tr>
                    <th class="whitespace-nowrap">Submitted</th>
                    <th>File</th>
                    <th>Agenda tags</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reportGroups as $group)
                    <tr class="bg-slate-50 dark:bg-slate-800/60">
                        <td colspan="4" class="py-2.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ $group['label'] }}
                        </td>
                    </tr>
                    @foreach ($group['reports'] as $report)
                        <tr>
                            <td class="whitespace-nowrap text-sm">
                                {{ $report->submitted_at?->format('M j, Y g:i A') }}
                                @if ($report->isLockedForBoardMemberMutation())
                                    <span class="mt-1 block text-xs text-slate-500">Locked — session/OB is over</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $report->original_filename }}</td>
                            <td class="text-sm">
                                @if ($report->agendaItems->isEmpty())
                                    —
                                @else
                                    <span class="flex flex-wrap gap-x-2 gap-y-1">
                                        @foreach ($report->agendaItems as $agenda)
                                            <a href="{{ route('agenda.show', $agenda) }}" class="splis-link whitespace-nowrap">{{ $agenda->displayLabel() }}</a>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex flex-nowrap items-center justify-end gap-2">
                                    @include('partials.pdf-modal-trigger', [
                                        'url' => route('board-member.committee-reports.pdf', $report),
                                        'viewer' => 'embed',
                                        'title' => ($report->original_filename ?: $report->title ?: 'Committee Report').' — '.$report->submitted_at?->format('M j, Y'),
                                        'label' => 'View',
                                        'class' => 'splis-btn-secondary inline-flex items-center gap-2 whitespace-nowrap text-sm',
                                    ])
                                    @can('update', $report)
                                        <a href="{{ route('board-member.committee-reports.edit', $report) }}" class="splis-btn-secondary inline-flex items-center gap-2 whitespace-nowrap text-sm">
                                            <x-icon name="edit" class="h-4 w-4" />
                                            Edit
                                        </a>
                                    @endcan
                                    @can('delete', $report)
                                        <form
                                            method="POST"
                                            action="{{ route('board-member.committee-reports.destroy', $report) }}"
                                            class="inline"
                                            data-confirm-submit
                                            data-confirm-title="Delete committee report?"
                                            data-confirm-message="Delete this uploaded committee report? Tagged agenda PDFs and related session folder copies from this submission will be removed."
                                            data-confirm-label="Delete"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="splis-btn-danger inline-flex items-center gap-2 whitespace-nowrap text-sm">
                                                <x-icon name="trash" class="h-4 w-4" />
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-slate-500">No committee reports submitted yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
