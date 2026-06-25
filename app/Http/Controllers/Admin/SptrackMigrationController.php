<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resolution;
use App\Models\SptrackImportQueue;
use App\Services\SptrackAnalyzer;
use App\Services\SptrackApplier;
use App\Services\SptrackReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SptrackMigrationController extends Controller
{
    public function index(SptrackReader $reader): View
    {
        $batchId = SptrackImportQueue::query()->latest('id')->value('batch_id');

        $counts = [
            'pending' => SptrackImportQueue::pending()->count(),
            'high' => SptrackImportQueue::pending()->where('confidence', SptrackImportQueue::CONFIDENCE_HIGH)->count(),
            'review' => SptrackImportQueue::pending()->whereIn('proposed_action', [
                SptrackImportQueue::ACTION_REVIEW,
            ])->count(),
            'create' => SptrackImportQueue::pending()->where('proposed_action', SptrackImportQueue::ACTION_CREATE)->count(),
            'approved' => SptrackImportQueue::where('queue_status', SptrackImportQueue::STATUS_APPROVED)->count(),
            'applied' => SptrackImportQueue::where('queue_status', SptrackImportQueue::STATUS_APPLIED)->count(),
        ];

        return view('admin.sptrack.index', [
            'batchId' => $batchId,
            'counts' => $counts,
            'source' => $reader->canUseDatabase() ? 'MySQL sptrack' : 'CSV fallback',
        ]);
    }

    public function queue(Request $request): View
    {
        $tab = $request->string('tab', 'high')->toString();

        $query = SptrackImportQueue::query()
            ->with(['suggestedResolution', 'userResolution'])
            ->latest('legacy_file_id');

        $query = match ($tab) {
            'high' => $query->pending()->where('confidence', SptrackImportQueue::CONFIDENCE_HIGH),
            'review' => $query->pending()->whereIn('proposed_action', [
                SptrackImportQueue::ACTION_REVIEW,
            ]),
            'create' => $query->pending()->where('proposed_action', SptrackImportQueue::ACTION_CREATE),
            'skip' => $query->pending()->where('proposed_action', SptrackImportQueue::ACTION_SKIP),
            'approved' => $query->where('queue_status', SptrackImportQueue::STATUS_APPROVED),
            'applied' => $query->where('queue_status', SptrackImportQueue::STATUS_APPLIED),
            default => $query->pending(),
        };

        if ($request->filled('series')) {
            $query->where('sp_series', (int) $request->input('series'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('sp_title', 'like', $term)
                    ->orWhere('sp_res_no', 'like', $term)
                    ->orWhere('municipality', 'like', $term);
            });
        }

        return view('admin.sptrack.queue', [
            'tab' => $tab,
            'items' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function analyze(Request $request, SptrackAnalyzer $analyzer): RedirectResponse
    {
        $stats = $analyzer->analyze(
            csvPath: $request->input('csv'),
            fresh: ! $request->boolean('keep_queue'),
            source: $request->input('source', 'database'),
        );

        return redirect()
            ->route('admin.sptrack.index')
            ->with('status', "Analysis complete. Batch {$stats['batch_id']}: {$stats['high']} high-confidence, {$stats['review']} need review, {$stats['create']} to create.");
    }

    public function approveHigh(): RedirectResponse
    {
        $updated = SptrackImportQueue::query()
            ->pending()
            ->where('confidence', SptrackImportQueue::CONFIDENCE_HIGH)
            ->where('proposed_action', SptrackImportQueue::ACTION_ENRICH)
            ->update([
                'queue_status' => SptrackImportQueue::STATUS_APPROVED,
                'user_action' => SptrackImportQueue::ACTION_ENRICH,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        return back()->with('status', "Approved {$updated} high-confidence match(es).");
    }

    public function approveCreate(): RedirectResponse
    {
        $updated = SptrackImportQueue::query()
            ->pending()
            ->where('proposed_action', SptrackImportQueue::ACTION_CREATE)
            ->update([
                'queue_status' => SptrackImportQueue::STATUS_APPROVED,
                'user_action' => SptrackImportQueue::ACTION_CREATE,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        return back()->with('status', "Approved {$updated} new record(s) for creation.");
    }

    public function update(Request $request, SptrackImportQueue $queue): RedirectResponse
    {
        $data = $request->validate([
            'user_action' => ['required', 'in:enrich,create,skip'],
            'user_resolution_id' => ['nullable', 'exists:resolutions,id'],
            'approve' => ['nullable', 'boolean'],
        ]);

        $queue->user_action = $data['user_action'];
        $queue->user_resolution_id = $data['user_resolution_id'] ?? null;
        $queue->reviewed_by = auth()->id();
        $queue->reviewed_at = now();

        if ($request->boolean('approve')) {
            $queue->queue_status = SptrackImportQueue::STATUS_APPROVED;
        }

        $queue->save();

        return back()->with('status', 'Queue row updated.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:sptrack_import_queue,id'],
            'action' => ['required', 'in:approve,reject,skip'],
        ]);

        $status = match ($data['action']) {
            'approve' => SptrackImportQueue::STATUS_APPROVED,
            'reject' => SptrackImportQueue::STATUS_REJECTED,
            'skip' => SptrackImportQueue::STATUS_SKIPPED,
        };

        SptrackImportQueue::query()
            ->whereIn('id', $data['ids'])
            ->where('queue_status', SptrackImportQueue::STATUS_PENDING)
            ->get()
            ->each(function (SptrackImportQueue $item) use ($status) {
                $item->update([
                    'queue_status' => $status,
                    'user_action' => $item->user_action ?? $item->proposed_action,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
            });

        return back()->with('status', ucfirst($data['action']).'d '.count($data['ids']).' row(s).');
    }

    public function apply(SptrackApplier $applier): RedirectResponse
    {
        $stats = $applier->apply(user: auth()->user());

        return redirect()
            ->route('admin.sptrack.index')
            ->with('status', "Applied queue: {$stats['enriched']} enriched, {$stats['created']} created, {$stats['skipped']} skipped, {$stats['failed']} failed.");
    }

    public function searchResolutions(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if ($term === '') {
            return response()->json([]);
        }

        $results = Resolution::query()
            ->where(function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where('resolution_no', 'like', $like)
                    ->orWhere('resolution_title', 'like', $like);
            })
            ->when($request->filled('series'), fn ($q) => $q->where('series', (int) $request->input('series')))
            ->orderByDesc('series')
            ->limit(15)
            ->get(['id', 'resolution_no', 'series', 'resolution_title', 'date_approved']);

        return response()->json($results);
    }
}
