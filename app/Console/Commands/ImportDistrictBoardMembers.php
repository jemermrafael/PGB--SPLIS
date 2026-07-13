<?php

namespace App\Console\Commands;

use App\Models\BoardMember;
use App\Models\BoardMemberTerm;
use App\Models\CommitteeTerm;
use Illuminate\Console\Command;

class ImportDistrictBoardMembers extends Command
{
    protected $signature = 'splis:import-district-board-members
                            {--dry-run : Preview without writing}';

    protected $description = 'Import historical 1st and 2nd District board member rosters by election term';

    /**
     * Canonical name aliases (sheet label => preferred stored name / match key).
     *
     * @var array<string, string>
     */
    protected array $nameAliases = [
        'Rolly Tigas' => 'Rolando Tigas',
        'Peping Villapando' => 'Jose Villapando, Sr.',
        'Jomar Gaza' => 'Jomar L. Gaza, J.D.',
        'Godofredo Galicia' => 'Godofredo B. Galicia, Jr., M.D.',
        'Maria Margarita Roque' => 'Maria Margarita R. Roque',
        'Noel Joseph Valdecañas' => 'Noel Joseph L. Valdecañas',
        'Jovy Banzon' => 'Jovy Z. Banzon',
        'Romano Del Rosario' => 'Romano L. Del Rosario, MPA',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($this->roster() as $termLabel => $districts) {
            [$from, $to] = array_map('intval', explode('-', $termLabel));

            $term = CommitteeTerm::query()
                ->where('year_from', $from)
                ->where('year_to', $to)
                ->first()
                ?? CommitteeTerm::query()->where('label', $termLabel)->first();

            if (! $term) {
                $this->error("Missing committee term: {$termLabel}");

                return self::FAILURE;
            }

            $this->line("Term {$term->label} (#{$term->id})");

            foreach ($districts as $district => $names) {
                foreach (array_values($names) as $index => $rawName) {
                    $name = trim($rawName);
                    if ($name === '') {
                        continue;
                    }

                    $canonical = $this->nameAliases[$name] ?? $name;
                    $member = $this->findMember($canonical) ?? $this->findMember($name);

                    if (! $member) {
                        if ($dryRun) {
                            $this->info("  [create] {$canonical} → {$district}");
                            $created++;
                            $linked++;

                            continue;
                        }

                        $member = BoardMember::query()->create([
                            'name' => $canonical,
                            'honorific' => config('board_members.default_honorific', 'Hon.'),
                            'district' => $term->is_current ? $district : null,
                            'is_active' => $term->is_current,
                        ]);
                        $created++;
                    }

                    $existing = BoardMemberTerm::query()
                        ->where('board_member_id', $member->id)
                        ->where('committee_term_id', $term->id)
                        ->first();

                    if ($existing) {
                        if ($dryRun) {
                            $this->line("  [keep] {$member->name} on {$district}");
                        } else {
                            $existing->update([
                                'district' => $district,
                                'is_active' => true,
                                'sort_order' => $index + 1,
                            ]);
                        }
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->info("  [link] {$member->name} → {$district}");
                        $linked++;

                        continue;
                    }

                    BoardMemberTerm::query()->create([
                        'board_member_id' => $member->id,
                        'committee_term_id' => $term->id,
                        'district' => $district,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ]);
                    $linked++;
                }
            }
        }

        $this->table(
            ['Created members', 'Assignments written', 'Already assigned'],
            [[$created, $linked, $skipped]],
        );

        if ($dryRun) {
            $this->comment('Dry run only — no changes saved.');
        }

        return self::SUCCESS;
    }

    protected function findMember(string $name): ?BoardMember
    {
        $exact = BoardMember::query()->where('name', $name)->first();
        if ($exact) {
            return $exact;
        }

        $needle = $this->normalize($name);

        return BoardMember::query()
            ->get()
            ->first(fn (BoardMember $member) => $this->normalize($member->name) === $needle);
    }

    protected function normalize(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\b(JR\.?|SR\.?|II|III|IV|M\.?D\.?|MPA|J\.?D\.?|OP)\b/u', '', $name) ?? $name;
        $name = preg_replace('/[^A-ZÑ\s]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? $name;

        return $name;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    protected function roster(): array
    {
        return [
            '2004-2007' => [
                '1st District' => [
                    'Rodolfo Izon',
                    'Edwin Enrile',
                    'Edward Roman',
                    'Rodolfo Salandanan',
                    'Orlando Miranda',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Edgardo Calimbas',
                    'Dante Manalaysay',
                    'Fernando Austria',
                    'Eduard Florendo',
                ],
            ],
            '2007-2010' => [
                '1st District' => [
                    'Rodolfo Izon',
                    'Gaudencio Ferrer',
                    'Edward Roman',
                    'Efren Pascual, Jr.',
                    'Orlando Miranda',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Edgardo Calimbas',
                    'Angel Peliglorio, Jr.',
                    'Gerardo Roxas',
                    'Eduard Florendo',
                ],
            ],
            '2010-2013' => [
                '1st District' => [
                    'Efren Cruz',
                    'Gaudencio Ferrer',
                    'Aristotle Gaza',
                    'Jose Alejandro Payumo III',
                    'Dexter Dominguez',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Jovy Banzon',
                    'Dante Manalaysay',
                    'Gerardo Roxas',
                    'Eduard Florendo',
                ],
            ],
            '2013-2016' => [
                '1st District' => [
                    'Rolando Tigas',
                    'Gaudencio Ferrer',
                    'Aristotle Gaza',
                    'Reynaldo Ibe',
                    'Dexter Dominguez',
                ],
                '2nd District' => [
                    'Gerardo Roxas',
                    'Edgardo Calimbas',
                    'Dante Manalaysay',
                    'Jovy Banzon',
                    'Jose Villapando, Sr.',
                ],
            ],
            '2016-2019' => [
                '1st District' => [
                    'Benjamin Serrano Jr.',
                    'Dexter Dominguez',
                    'Aristotle Gaza',
                    'Reynaldo Ibe',
                    'Rolly Tigas',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Edgardo Calimbas',
                    'Dante Manalaysay',
                    'Jovy Banzon',
                    'Peping Villapando',
                ],
            ],
            '2019-2022' => [
                '1st District' => [
                    'Benjamin Serrano Jr.',
                    'Jomar Gaza',
                    'Godofredo Galicia',
                    'Reynaldo Ibe',
                    'Maria Dela Fuente',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Edgardo Calimbas',
                    'Maria Margarita Roque',
                    'Jose Villapando, Sr.',
                    'Romano Del Rosario',
                ],
            ],
            '2022-2025' => [
                '1st District' => [
                    'Benjamin Serrano Jr.',
                    'Jomar Gaza',
                    'Antonino Roman III',
                    'Reynaldo Ibe',
                    'Maria Dela Fuente',
                ],
                '2nd District' => [
                    'Manuel Beltran',
                    'Noel Joseph Valdecañas',
                    'Maria Margarita Roque',
                ],
            ],
        ];
    }
}
