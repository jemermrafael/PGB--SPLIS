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

    <div class="splis-help-callout mt-6 space-y-2 text-sm text-slate-600 dark:text-slate-300">
        <p><strong>Limited</strong> means scoped access — e.g. Board Members see scheduled sessions, their committees, and their own reports; municipal viewers see their municipality’s requests and related documents; Vice Governor Board Members can open the full executive dashboard.</p>
        <p><strong>Soft-delete (trash)</strong> is available to all encode roles (Encoder, Encoder with Delete, Admin, Superadmin) where policies allow. Only Superadmin can open Trash, restore, or permanently delete.</p>
        <p><strong>Encoder with Delete</strong> is a legacy label; soft-delete is no longer restricted to that role alone. Capability flags per encoder module are not implemented yet — encode access is still all-or-nothing via <code class="text-xs">canEncode()</code>.</p>
        <p>This page is documentation. Enforcement lives in policies and route middleware.</p>
    </div>
</div>
@endsection
