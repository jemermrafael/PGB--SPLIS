<?php

namespace App\Providers;

use App\Models\AgendaItem;
use App\Models\IncomingDocument;
use App\Models\Resolution;
use App\Policies\AgendaItemPolicy;
use App\Policies\IncomingDocumentPolicy;
use App\Policies\ResolutionPolicy;
use Illuminate\Pagination\Paginator;
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
        Gate::policy(IncomingDocument::class, IncomingDocumentPolicy::class);
        Gate::policy(AgendaItem::class, AgendaItemPolicy::class);

        Paginator::defaultView('partials.splis-pagination');
    }
}
