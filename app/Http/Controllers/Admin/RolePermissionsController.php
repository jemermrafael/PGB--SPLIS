<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RolePermissionMatrix;
use Illuminate\View\View;

class RolePermissionsController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.role-permissions.index', [
            'roles' => RolePermissionMatrix::roles(),
            'rows' => RolePermissionMatrix::rows(),
        ]);
    }
}
