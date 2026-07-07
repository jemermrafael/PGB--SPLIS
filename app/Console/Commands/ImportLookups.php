<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Category2;
use App\Models\Category3;
use App\Models\Category4;
use App\Models\Department;
use App\Models\Legacy\LegacyCategory1;
use App\Models\Legacy\LegacyCategory2;
use App\Models\Legacy\LegacyCategory3;
use App\Models\Legacy\LegacyCategory4;
use App\Models\Legacy\LegacyDepartment;
use App\Models\Legacy\LegacyMunicipality;
use App\Models\Municipality;
use App\Models\SeriesYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLookups extends Command
{
    protected $signature = 'splis:import-lookups';

    protected $description = 'Import lookup tables from legacy SP MySQL (SPRESO_DB_*) into splis';

    public function handle(): int
    {
        $this->info('Importing categories...');

        foreach (LegacyCategory1::all() as $row) {
            Category::updateOrCreate(
                ['legacy_id' => $row->ID],
                ['description' => $row->Desc ?? 'Unknown']
            );
        }

        foreach (LegacyCategory2::all() as $row) {
            $parent = Category::where('legacy_id', $row->Cat1_ID)->first();
            if ($parent) {
                Category2::updateOrCreate(
                    ['legacy_id' => $row->ID],
                    ['category_id' => $parent->id, 'description' => $row->Desc ?? 'Unknown']
                );
            }
        }

        foreach (LegacyCategory3::all() as $row) {
            $parent = Category2::where('legacy_id', $row->Cat2_ID)->first();
            if ($parent) {
                Category3::updateOrCreate(
                    ['legacy_id' => $row->ID],
                    ['category2_id' => $parent->id, 'description' => $row->Desc ?? 'Unknown']
                );
            }
        }

        foreach (LegacyCategory4::all() as $row) {
            $parent = Category3::where('legacy_id', $row->Cat3_ID)->first();
            if ($parent) {
                Category4::updateOrCreate(
                    ['legacy_id' => $row->ID],
                    ['category3_id' => $parent->id, 'description' => $row->Desc ?? 'Unknown']
                );
            }
        }

        $this->info('Importing departments...');
        foreach (LegacyDepartment::all() as $row) {
            Department::updateOrCreate(
                ['code' => $row->Code],
                ['description' => $row->Desc ?? '', 'abbreviation' => $row->Abb ?? null]
            );
        }

        $this->info('Importing municipalities...');
        foreach (LegacyMunicipality::all() as $row) {
            Municipality::updateOrCreate(
                ['code' => $row->Code],
                [
                    'description' => $row->Desc ?? '',
                    'zipcode' => $row->zipcode ?? null,
                    'district' => $row->district ?? null,
                ]
            );
        }

        $this->info('Importing series years...');
        $years = DB::connection('spreso')->table('zseriesyr')->pluck('seriesyr');
        foreach ($years as $year) {
            SeriesYear::updateOrCreate(['year' => (int) $year]);
        }

        $distinct = DB::connection('spreso')->table('sp')->distinct()->orderByDesc('Series')->pluck('Series');
        foreach ($distinct as $year) {
            if ($year) {
                SeriesYear::updateOrCreate(['year' => (int) $year]);
            }
        }

        $this->info('Lookup import complete.');

        return self::SUCCESS;
    }
}
