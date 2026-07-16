@extends('layouts.app')

@section('title', 'Role permissions — '.config('app.name'))

@section('content')
<div class="max-w-6xl">
    <div class="splis-page-header">
        <div>
            <h1 class="splis-page-title">Role permissions</h1>
            <p class="splis-page-subtitle">Reference matrix of what each account role can do in SPLIS. Enforcement is still in policies — this page is documentation.</p>
        </div>
    </div>

    <div class="splis-table-wrap">
        <table class="splis-table text-sm">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Action</th>
                    @foreach ($roles as $role)
                        <th class="whitespace-nowrap">{{ $role['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="font-medium whitespace-nowrap">{{ $row['group'] }}</td>
                        <td>{{ $row['action'] }}</td>
                        @foreach ($roles as $role)
                            @php $cell = $row['cells'][$role['key']] ?? 'no'; @endphp
                            <td @class([
                                'font-semibold text-emerald-700 dark:text-emerald-400' => $cell === 'yes',
                                'text-amber-700 dark:text-amber-300' => $cell === 'limited',
                                'text-slate-400' => $cell === 'no',
                            ])>
                                {{ \App\Support\RolePermissionMatrix::cellLabel($cell) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="splis-help-callout mt-6 text-sm text-slate-600 dark:text-slate-300">
        <p><strong>Limited</strong> means scoped access (e.g. Board Members see scheduled OB / their committees; municipal viewers see their municipality’s requests).</p>
        <p class="mt-2"><strong>Encoder with Delete</strong> historically meant resolution delete; soft-delete (`Delete`) is now allowed for all encode roles. Only Superadmin can open Trash, restore, or permanently delete.</p>
    </div>
</div>
@endsection
