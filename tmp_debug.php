<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\CommitteeMembershipRole;
use App\Enums\UserRole;
use App\Models\AgendaItem;
use App\Models\BoardMember;
use App\Models\Committee;
use App\Models\CommitteeMembership;
use App\Models\CommitteeTerm;
use App\Models\User;
use App\Services\BoardMemberDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Use phpunit-style: just call the service after creating via artisan tinker approach won't have refresh DB easily.
echo "use feature test dump instead\n";
