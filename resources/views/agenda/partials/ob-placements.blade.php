@if ($placements->isNotEmpty())
    @php
        $allObEnded = $placements->every(function ($placement) {
            $session = $placement->legislativeSession;

            if (! $session) {
                return true;
            }

            return $session->isPastSessionDate()
                || $placement->obDocument?->isFinal();
        });
    @endphp
    <div>
        <p class="splis-detail-label">
            {{ $allObEnded ? 'Order of Business history' : 'Order of Business placements' }}
        </p>
        <ul class="mt-2 space-y-3">
            @foreach ($placements as $placement)
                @if ($placement->legislativeSession)
                    @php
                        $obEnded = $placement->legislativeSession->isPastSessionDate()
                            || $placement->obDocument?->isFinal();
                    @endphp
                    <li class="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
                        <div class="min-w-0">
                            <a href="{{ route('ob.sessions.show', $placement->legislativeSession) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-200">
                                {{ $placement->legislativeSession->displayTitle() }}
                            </a>
                            <p class="mt-1 text-xs text-slate-500">{{ $placement->sectionLabel() }}</p>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                                @if ($placement->session_agenda_no)
                                    <span>Agenda no. {{ $placement->session_agenda_no }}</span>
                                @endif
                                @if ($placement->agendaItemVersion)
                                    <span>Version v{{ $placement->agendaItemVersion->version_no }}</span>
                                @endif
                                @if ($placement->obDocument)
                                    <span>{{ $placement->obDocument->isFinal() ? 'Final' : 'Draft' }}</span>
                                @endif
                                <span>{{ $placement->created_at?->format('M j, Y') }}</span>
                            </div>
                        </div>
                        @if (! $obEnded)
                            @can('removeFromOrderOfBusiness', $agenda)
                                <form
                                    method="POST"
                                    action="{{ route('agenda.remove-from-order-of-business', $agenda) }}"
                                    class="mt-3"
                                    data-confirm-submit
                                    data-confirm-title="Remove from Order of Business?"
                                    data-confirm-message="Remove this agenda item from {{ $placement->legislativeSession->displayTitle() }}?"
                                    data-confirm-label="Remove"
                                >
                                    @csrf
                                    <input type="hidden" name="legislative_session_id" value="{{ $placement->legislative_session_id }}">
                                    <button type="submit" class="splis-btn-danger w-full justify-center text-sm">Remove from OB</button>
                                </form>
                            @endcan
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
