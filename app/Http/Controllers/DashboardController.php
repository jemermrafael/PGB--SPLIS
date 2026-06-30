<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Resolution;
use App\Models\SeriesYear;
use App\Services\ResolutionRepository;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ResolutionRepository $repository): View
    {
        $currentYear = (int) date('Y');
        $legacyCount = $repository->legacyCount();
        $newCount = $repository->newCount();

        return view('dashboard', [
            'totalResolutions' => $repository->totalCount(),
            'legacyCount' => $legacyCount,
            'newCount' => $newCount,
            'currentYearCount' => Resolution::query()->where('series', $currentYear)->count(),
            'recentActivity' => ActivityLog::query()->with('user')->latest('created_at')->limit(10)->get(),
            'categories' => Category::forSelect(),
            'departments' => Department::orderBy('description')->get(),
            'municipalities' => Municipality::orderBy('description')->get(),
            'seriesYears' => SeriesYear::orderByDesc('year')->pluck('year'),
        ]);
    }
}
