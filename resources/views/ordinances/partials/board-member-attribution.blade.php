@php
    use App\Enums\OrdinanceBoardMemberRole;

    /** @var \App\Models\Ordinance $ordinance */

    $attributionRows = collect(OrdinanceBoardMemberRole::cases())
        ->map(fn (OrdinanceBoardMemberRole $role) => [
            'role' => $role,
            'members' => $ordinance->membersForRole($role),
        ])
        ->filter(fn (array $row) => $row['members']->isNotEmpty());
@endphp

@if ($attributionRows->isNotEmpty())
    <div class="md:col-span-2">
        <dt class="splis-label">Board Members</dt>
        <dd class="mt-2 space-y-2">
            @foreach ($attributionRows as $row)
                <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:w-44">{{ $row['role']->label() }}</span>
                    <span class="text-slate-900 dark:text-slate-100">
                        @foreach ($row['members'] as $member)
                            <a href="{{ route('board-members.show', $member) }}" class="splis-doc-list-link font-medium">{{ $member->displayName() }}</a>@if (! $loop->last)<span class="text-slate-500">, </span>@endif
                        @endforeach
                    </span>
                </div>
            @endforeach
        </dd>
    </div>
@endif
