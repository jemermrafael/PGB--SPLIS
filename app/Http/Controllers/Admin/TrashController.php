<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrashController extends Controller
{
    /**
     * @return array<string, array{label: string, model: class-string<Model>}>
     */
    public static function types(): array
    {
        return [
            'resolutions' => [
                'label' => 'Resolutions',
                'model' => Resolution::class,
            ],
            'ordinances' => [
                'label' => 'Ordinances',
                'model' => Ordinance::class,
            ],
            'appropriation-ordinances' => [
                'label' => 'Appropriation Ordinances',
                'model' => AppropriationOrdinance::class,
            ],
            'committees' => [
                'label' => 'Committees',
                'model' => Committee::class,
            ],
            'board-members' => [
                'label' => 'Board Members',
                'model' => BoardMember::class,
            ],
            'agenda' => [
                'label' => 'Agenda',
                'model' => AgendaItem::class,
            ],
            'references' => [
                'label' => 'References',
                'model' => ReferenceMaterial::class,
            ],
            'sessions' => [
                'label' => 'Sessions',
                'model' => LegislativeSession::class,
            ],
        ];
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

        $rows = $items->getCollection()->map(fn (Model $model) => $this->present($type, $model));

        return view('admin.trash.index', [
            'types' => $types,
            'type' => $type,
            'counts' => $counts,
            'items' => $items,
            'rows' => $rows,
        ]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        $model = $this->findTrashed($type, $id);
        $model->restore();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', $this->labelFor($type).' restored.');
    }

    public function forceDestroy(string $type, int $id): RedirectResponse
    {
        $model = $this->findTrashed($type, $id);
        $model->forceDelete();

        return redirect()
            ->route('admin.trash.index', ['type' => $type])
            ->with('status', $this->labelFor($type).' permanently deleted.');
    }

    protected function findTrashed(string $type, int $id): Model
    {
        $model = $this->trashedQuery($type)->whereKey($id)->firstOrFail();

        return $model;
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

        $query = $class::query()->onlyTrashed();

        if ($type === 'resolutions') {
            $query->with('creator');
        }

        return $query;
    }

    /**
     * @return array{id: int, primary: string, secondary: string, deleted_at: string, open_url: string|null}
     */
    protected function present(string $type, Model $model): array
    {
        return match ($type) {
            'resolutions' => [
                'id' => $model->id,
                'primary' => $model->series.'-'.$model->resolution_no,
                'secondary' => (string) ($model->resolution_title ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => route('resolutions.show', $model),
            ],
            'ordinances' => [
                'id' => $model->id,
                'primary' => 'Ord. '.$model->ordinance_no.' ('.$model->series_year.')',
                'secondary' => (string) ($model->subject ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'appropriation-ordinances' => [
                'id' => $model->id,
                'primary' => 'AO '.$model->ordinance_no.' ('.$model->series_year.')',
                'secondary' => (string) ($model->subject ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'committees' => [
                'id' => $model->id,
                'primary' => (string) ($model->name ?: '—'),
                'secondary' => $model->is_active ? 'Active' : 'Inactive',
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'board-members' => [
                'id' => $model->id,
                'primary' => trim(($model->honorific ? $model->honorific.' ' : '').$model->name),
                'secondary' => (string) ($model->district ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'agenda' => [
                'id' => $model->id,
                'primary' => $model->displayLabel(),
                'secondary' => (string) ($model->title ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'references' => [
                'id' => $model->id,
                'primary' => (string) ($model->title ?: '—'),
                'secondary' => (string) ($model->reference_no ?: $model->document_type ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            'sessions' => [
                'id' => $model->id,
                'primary' => $model->displayTitle(),
                'secondary' => (string) ($model->venue ?: $model->status ?: '—'),
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
            default => [
                'id' => $model->id,
                'primary' => '#'.$model->id,
                'secondary' => '—',
                'deleted_at' => $model->deleted_at?->format('M d, Y g:i A') ?: '—',
                'open_url' => null,
            ],
        };
    }

    protected function labelFor(string $type): string
    {
        return self::types()[$type]['label'] ?? 'Item';
    }
}
