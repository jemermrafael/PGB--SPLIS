<?php

namespace App\Providers;

use App\Models\Resolution;
use App\Policies\ResolutionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Resolution::class, ResolutionPolicy::class);
    }
}
