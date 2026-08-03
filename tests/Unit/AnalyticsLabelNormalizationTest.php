<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Committee;
use App\Support\CommitteeLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsLabelNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_analytics_groups_supplemental_budget_variants(): void
    {
        $this->assertSame('SUPPLEMENTAL BUDGET', Category::analyticsGroupLabel('SUPPLEMENTAL BUDGET'));
        $this->assertSame('SUPPLEMENTAL BUDGET', Category::analyticsGroupLabel(' supplemental budget '));
        $this->assertSame('SUPPLEMENTAL BUDGET', Category::analyticsGroupLabel('SUPPLEMENTAL BUDGET NO. 03'));
        $this->assertSame('SUPPLEMENTAL BUDGET', Category::analyticsGroupLabel('SUPPLEMENTAL BUDGET NO.01'));
        $this->assertSame('SUPPLEMENTAL BUDGET', Category::analyticsGroupLabel('Supplemental'));
        $this->assertSame('SUPPLEMENTAL INVESTMENT PROGRAM', Category::analyticsGroupLabel('SUPPLEMENTAL INVESTMENT'));
        $this->assertSame('SUPPLEMENTAL INVESTMENT PROGRAM', Category::analyticsGroupLabel('SUPPLEMENTAL INVESTMENT PROGRAM'));
    }

    public function test_committee_lookup_maps_short_finance_and_housing_names(): void
    {
        $finance = Committee::query()->create([
            'name' => 'Finance, Budget, Appropriation, and Ways & Means',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $housing = Committee::query()->create([
            'name' => 'Housing and Land Use',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->assertSame($finance->id, CommitteeLookup::findByName('Finance')?->id);
        $this->assertSame($housing->id, CommitteeLookup::findByName('Housing')?->id);
    }
}
