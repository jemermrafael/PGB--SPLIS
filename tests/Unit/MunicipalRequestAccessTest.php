<?php

namespace Tests\Unit;

use App\Models\AgendaItem;
use App\Models\Municipality;
use App\Support\MunicipalRequestAccess;
use Tests\TestCase;

class MunicipalRequestAccessTest extends TestCase
{
    public function test_sender_matches_municipality_case_insensitively(): void
    {
        $municipality = new Municipality(['description' => 'MARIVELES']);

        $this->assertTrue(MunicipalRequestAccess::senderMatches('Mariveles', $municipality));
        $this->assertTrue(MunicipalRequestAccess::senderMatches('mariveles', $municipality));
        $this->assertFalse(MunicipalRequestAccess::senderMatches('BM Gaza', $municipality));
        $this->assertFalse(MunicipalRequestAccess::senderMatches('PGO', $municipality));
    }

    public function test_agenda_belongs_to_municipality_by_exact_sender(): void
    {
        $municipality = new Municipality(['description' => 'MARIVELES']);
        $agenda = new AgendaItem(['sender' => 'Mariveles', 'status' => AgendaItem::STATUS_PENDING]);

        $this->assertTrue(MunicipalRequestAccess::agendaBelongsToMunicipality($agenda, $municipality));
    }
}
