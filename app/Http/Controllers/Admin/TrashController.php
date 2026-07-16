<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\LegislativeSession;
use App\Models\Ordinance;
use App\Models\ReferenceMaterial;
use App\Models\Resolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TrashController extends Controller
{
    /**
     * @return array<string, array{label: string, model: class-string<Model>, trashed_action: string, restored_action: string, deleted_action: string, show_route: string|null}>
     */
    public static function types(): array
    {
        return [
            'resolutions' => [
                'label' => 'Resolutions',
                'model' => Resolution::class,
                'trashed_action' => 'resolution.trashed',
                'restored_action' => 'resolution.restored',
                'deleted_action' => 'resolution.deleted',
                'show_route' => 'resolutions.show',
            ],
            'ordinances' => [
                'label' => 'Ordinances',
                'model' => Ordinance::class,
                'trashed_action' => 'ordinance.trashed',
                'restored_action' => 'ordinance.restored',
                'deleted_action' => 'ordinance.deleted',
                'show_route' => 'ordinances.show',
            ],
            'appropriation-ordinances' => [
                'label' => 'Appropriation Ordinances',
                'model' => AppropriationOrdinance::class,
                'trashed_action' => 'appropriation_ordinance.trashed',
                'restored_action' => 'appropriation_ordinance.restored',
                'deleted_action' => 'appropriation_ordinance.deleted',
                'show_route' => 'appropriation-ordinances.show',
            ],
            'committees' => [
                'label' => 'Committees',
                'model' => Committee::class,
                'trashed_action' => 'committee.trashed',
                'restored_action' => 'committee.restored',
                'deleted_action' => 'committee.deleted',
                'show_route' => 'committees.show',
            ],
            'board-members' => [
                'label' => 'Board Members',
                'model' => BoardMember::class,
                'trashed_action' => 'board_member.trashed',
                'restored_action' => 'board_member.restored',
                'deleted_action' => 'board_member.deleted',
                'show_route' => 'board-members.show',
            ],
            'agenda' => [
                'label' => 'Agenda',
                'model' => AgendaItem::class,
                'trashed_action' => 'agenda.trashed',
                'restored_action' => 'agenda.restored',
                'deleted_action' => 'agenda.deleted',
                'show_route' => 'agenda.show',
            ],
            'references' => [
                'label' => 'References',
                'model' => ReferenceMaterial::class,
                'trashed_action' => 'reference_material.deleted',
                'restored_action' => 'reference_material.untrashed',
                'deleted_action' => 'reference_material.force_deleted',
                'show_route' => 'references.show',
            ],
            'sessions' => [
                'label' => 'Sessions',
                'model' => LegislativeSession::class,
                'trashed_action' => 'legislative_session.trashed',
                'restored_action' => 'legislative_session.restored',
                'deleted_action' => 'legislative_session.deleted',
                'show_route' => 'ob.sessions.show',
            ],
        ];
    }

    public static function totalCount(): int
    {
        return (int) Cache::remember('splis.trash.total', 60, function () {
            $total = 0;
            foreach (array_keys(self::types()) as $type) {
                $total += (new self)->trashedQuery($type)->count();
            }

            return $total;
        });
    }

    public static function forgetCountCache(): void
    {
        Cache::forget('splis.trash.total');
    }

    public function index(Request $request): View
    {
        $types = self::types();
        $type = (string) $request->query('type', 'resolutions');

        if (! isset($types[$type])) {
            $type = 'resolutions';
        }

        $counts = [];
        foreach ($types as $key => $meta) {
            $counts[$key] = $this->trashedQuery($key)->count();
        }

        $items = $this->trashedQuery($type)
            ->orderByDesc('deleted_at')
            ->paginate(25)
            ->withQueryString();

        $deleters = $this->resolveDeleters($type, $items->getCollection());

        $rows = $items->getCollection()->map(fn (Model $model) => $this->present($type, $model, $deleters));

        return view('admin.trash.index', [
            'types' => $types,
            'type' => $type,
            'counts' => $counts,
            'items' => $items,
            'rows' => $rows,
            'retentionDays' => (int) config('trash.retention_days', 30),
        ]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        $model = $this->findTrashed($type, $id);

        try {
            $model->restore();
        } catch (QueryException $e) {
            return back()->with('error', $this->collisionMessage($type, $e));
        }

        ActivityLog::record(self::types()[$type]['restored_action'], $model);
        self::forgetCountCache();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', $this->labelFor($type).' restored.');
    }

    public function forceDestroy(string $type, int $id): RedirectResponse
    {
        $model = $this->findTrashed($type, $id);
        ActivityLog::record(self::types()[$type]['deleted_action'], $model);
        $model->forceDelete();
        self::forgetCountCache();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', $this->labelFor($type).' permanently deleted.');
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $type = $data['type'];
        abort_unless(isset(self::types()[$type]), 404);

        $restored = 0;
        $failed = 0;

        foreach ($data['ids'] as $id) {
            $model = $this->trashedQuery($type)->whereKey($id)->first();
            if (! $model) {
                continue;
            }

            try {
                $model->restore();
                ActivityLog::record(self::types()[$type]['restored_action'], $model);
                $restored++;
            } catch (QueryException) {
                $failed++;
            }
        }

        self::forgetCountCache();

        $message = "{$restored} item(s) restored.";
        if ($failed > 0) {
            $message .= " {$failed} could not be restored (unique key conflict with an active record).";
        }

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with($failed > 0 && $restored === 0 ? 'error' : 'status', $message);
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $type = $data['type'];
        abort_unless(isset(self::types()[$type]), 404);

        $deleted = 0;
        foreach ($data['ids'] as $id) {
            $model = $this->trashedQuery($type)->whereKey($id)->first();
            if (! $model) {
                continue;
            }
            ActivityLog::record(self::types()[$type]['deleted_action'], $model);
            $model->forceDelete();
            $deleted++;
        }

        self::forgetCountCache();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', "{$deleted} item(s) permanently deleted.");
    }

    public function purgeOlder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $type = $data['type'];
        abort_unless(isset(self::types()[$type]), 404);

        $days = (int) ($data['days'] ?? config('trash.retention_days', 30));
        $cutoff = now()->subDays($days);

        $deleted = 0;
        $ids = $this->trashedQuery($type)
            ->where('deleted_at', '<', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            $model = $this->trashedQuery($type)->whereKey($id)->first();
            if (! $model) {
                continue;
            }
            ActivityLog::record(self::types()[$type]['deleted_action'], $model, [
                'purged_older_than' => true,
            ]);
            $model->forceDelete();
            $deleted++;
        }

        self::forgetCountCache();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', "Purged {$deleted} item(s) older than {$days} days.");
    }

    protected function findTrashed(string $type, int $id): Model
    {
        return $this->trashedQuery($type)->whereKey($id)->firstOrFail();
    }

    /**
     * @return Builder<Model>
     */
    protected function trashedQuery(string $type): Builder
    {
        $types = self::types();
        abort_unless(isset($types[$type]), 404);

        /** @var class-string<Model> $class */
        $class = $types[$type]['model'];

        return $class::query()->onlyTrashed();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Model>  $models
     * @return array<int, string>
     */
    protected function resolveDeleters(string $type, $models): array
    {
        if ($models->isEmpty()) {
            return [];
        }

        $action = self::types()[$type]['trashed_action'];
        $class = self::types()[$type]['model'];
        $ids = $models->modelKeys();

        $logs = ActivityLog::query()
            ->where('subject_type', $class)
            ->whereIn('subject_id', $ids)
            ->where('action', $action)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (ActivityLog $log) => $log->subject_id);

        $map = [];
        foreach ($logs as $log) {
            $map[(int) $log->subject_id] = $log->user?->name ?: '—';
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $deleters
     * @return array{id: int, primary: string, secondary: string, deleted_at: string, deleted_by: string, open_url: string|null}
     */
    protected function present(string $type, Model $model, array $deleters): array
    {
        $meta = self::types()[$type];
        $openUrl = $meta['show_route'] ? route($meta['show_route'], $model) : null;

        $base = [
            'id' => $model->id,
            'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
            'deleted_by' => $deleters[$model->id] ?? '—',
            'open_url' => $openUrl,
        ];

        return match ($type) {
            'resolutions' => $base + [
                'primary' => $model->series.'-'.$model->resolution_no,
                'secondary' => (string) ($model->resolution_title ?: '—'),
            ],
            'ordinances' => $base + [
                'primary' => 'Ord. '.$model->ordinance_no.' ('.$model->series_year.')',
                'secondary' => (string) ($model->subject ?: '—'),
            ],
            'appropriation-ordinances' => $base + [
                'primary' => 'AO '.$model->ordinance_no.' ('.$model->series_year.')',
                'secondary' => (string) ($model->subject ?: '—'),
            ],
            'committees' => $base + [
                'primary' => (string) ($model->name ?: '—'),
                'secondary' => $model->is_active ? 'Active' : 'Inactive',
            ],
            'board-members' => $base + [
                'primary' => trim(($model->honorific ? $model->honorific.' ' : '').$model->name),
                'secondary' => (string) ($model->district ?: '—'),
            ],
            'agenda' => $base + [
                'primary' => $model->displayLabel(),
                'secondary' => (string) ($model->title ?: '—'),
            ],
            'references' => $base + [
                'primary' => (string) ($model->title ?: '—'),
                'secondary' => (string) ($model->reference_no ?: $model->document_type ?: '—'),
            ],
            'sessions' => $base + [
                'primary' => $model->displayTitle(),
                'secondary' => (string) ($model->venue ?: $model->status ?: '—'),
            ],
            default => $base + [
                'primary' => '#'.$model->id,
                'secondary' => '—',
            ],
        };
    }

    protected function labelFor(string $type): string
    {
        return self::types()[$type]['label'] ?? 'Item';
    }

    protected function collisionMessage(string $type, QueryException $e): string
    {
        $label = $this->labelFor($type);

        if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains($e->getMessage(), 'Duplicate')) {
            return "Could not restore {$label}: an active record already uses the same unique key (name/number). Rename or remove the conflict, then try again.";
        }

        return "Could not restore {$label}.";
    }
}
