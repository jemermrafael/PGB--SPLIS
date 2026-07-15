@extends('layouts.app')

@section('title', 'Order of Business — '.config('app.name'))

@section('content')
<div>
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Order of Business</h1>
            <p class="splis-page-subtitle">Legislative Sessions and OB documents — {{ $sessions->total() }} session{{ $sessions->total() === 1 ? '' : 's' }} on file.</p>
        </div>
        @can('create', App\Models\LegislativeSession::class)
            <a href="{{ route('ob.sessions.create') }}" class="splis-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New session
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="splis-alert-success mb-6">{{ session('status') }}</div>
    @endif

    <div class="splis-card overflow-hidden">
        @if ($sessions->isEmpty())
            <div class="splis-card-body text-center text-slate-600 dark:text-slate-400">
                <p class="mb-4">No Legislative Sessions yet.</p>
                @can('create', App\Models\LegislativeSession::class)
                    <a href="{{ route('ob.sessions.create') }}" class="splis-btn-primary inline-flex">Create first session</a>
                @endcan
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="splis-table">
                    <thead>
                        <tr>
                            <th>Session date</th>
                            <th>Session</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th>OB document</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td class="whitespace-nowrap font-medium">
                                    {{ $session->session_date->format('M d, Y') }}
                                    @if ($session->formattedSessionTime())
                                        <span class="block text-xs font-normal text-slate-500">{{ $session->formattedSessionTime() }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $session->session_number ?: '—' }}</span>
                                    <span class="block text-xs text-slate-500">{{ $session->sessionKindLabel() }}</span>
                                </td>
                                <td>{{ $session->venue ?: '—' }}</td>
                                <td>
                                    <span class="splis-badge">{{ $session->statusLabel() }}</span>
                                </td>
                                <td>
                                    @if ($session->obDocument)
                                        <span class="text-sm">{{ $session->obDocument->statusLabel() }}</span>
                                        <span class="block text-xs text-slate-500">{{ $session->obDocument->blocks_count }} blocks</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @include('order-of-business.partials.session-action-links', ['session' => $session])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
