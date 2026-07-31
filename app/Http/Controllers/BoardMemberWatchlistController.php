<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\BoardMemberWatchlistItem;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Services\BoardMemberWatchlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardMemberWatchlistController extends Controller
{
    public function __construct(
        protected BoardMemberWatchlistService $watchlist,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        return view('board-member.watchlist.index', [
            'items' => $this->watchlist->listForUser($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);

        $validated = $request->validate([
            'watchable_type' => ['required', 'string', 'in:agenda,resolution,ordinance'],
            'watchable_id' => ['required', 'integer', 'min:1'],
        ]);

        $watchable = match ($validated['watchable_type']) {
            'agenda' => AgendaItem::query()->findOrFail($validated['watchable_id']),
            'resolution' => Resolution::query()->findOrFail($validated['watchable_id']),
            'ordinance' => Ordinance::query()->findOrFail($validated['watchable_id']),
        };

        $isWatching = $this->watchlist->toggle($user, $watchable);

        return back()->with('status', $isWatching
            ? 'Added to your watchlist.'
            : 'Removed from your watchlist.');
    }

    public function destroy(Request $request, BoardMemberWatchlistItem $watchlistItem): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isBoardMember(), 403);
        abort_unless($watchlistItem->user_id === $user->id, 403);

        $watchlistItem->delete();

        return back()->with('status', 'Removed from your watchlist.');
    }
}
