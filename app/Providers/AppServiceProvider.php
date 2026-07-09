<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\AppropriationOrdinance;
use App\Models\AgendaItem;
use App\Models\AgendaItemVersion;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeTerm;
use App\Models\IncomingDocument;
use App\Models\LegislativeSession;
use App\Models\ObDocument;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\AppropriationOrdinancePolicy;
use App\Policies\AgendaItemPolicy;
use App\Policies\AgendaItemVersionPolicy;
use App\Policies\BoardMemberPolicy;
use App\Policies\CommitteePolicy;
use App\Policies\CommitteeTermPolicy;
use App\Policies\IncomingDocumentPolicy;
use App\Policies\LegislativeSessionPolicy;
use App\Policies\ObDocumentPolicy;
use App\Policies\OrdinancePolicy;
use App\Policies\ResolutionPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Resolution::class, ResolutionPolicy::class);
        Gate::policy(IncomingDocument::class, IncomingDocumentPolicy::class);
        Gate::policy(AgendaItem::class, AgendaItemPolicy::class);
        Gate::policy(AgendaItemVersion::class, AgendaItemVersionPolicy::class);
        Gate::policy(Committee::class, CommitteePolicy::class);
        Gate::policy(BoardMember::class, BoardMemberPolicy::class);
        Gate::policy(CommitteeTerm::class, CommitteeTermPolicy::class);
        Gate::policy(LegislativeSession::class, LegislativeSessionPolicy::class);
        Gate::policy(Ordinance::class, OrdinancePolicy::class);
        Gate::policy(AppropriationOrdinance::class, AppropriationOrdinancePolicy::class);
        Gate::policy(ObDocument::class, ObDocumentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Paginator::defaultView('partials.splis-pagination');
    }
}
