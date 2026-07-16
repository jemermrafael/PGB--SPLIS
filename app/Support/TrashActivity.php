<?php

namespace App\Support;

use App\Http\Controllers\Admin\TrashController;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class TrashActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, Model $model, array $properties = []): void
    {
        ActivityLog::record($action, $model, $properties);
        TrashController::forgetCountCache();
    }
}
