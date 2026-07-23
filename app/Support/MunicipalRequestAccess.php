<?php

namespace App\Support;

use App\Models\AgendaItem;
use App\Models\AppropriationOrdinance;
use App\Models\Municipality;
use App\Models\Ordinance;
use App\Models\Resolution;
use App\Models\User;

class MunicipalRequestAccess
{
    public static function senderFor(User $user): ?string
    {
        if (! $user->isMunicipalViewer() || $user->municipality_id === null) {
            return null;
        }

        return $user->municipality?->senderLabel();
    }

    public static function senderMatches(?string $sender, Municipality $municipality): bool
    {
        if ($sender === null || trim($sender) === '') {
            return false;
        }

        return mb_strtolower(trim($sender)) === mb_strtolower($municipality->senderLabel());
    }

    public static function agendaBelongsToMunicipality(AgendaItem $agenda, Municipality $municipality): bool
    {
        return self::senderMatches($agenda->sender, $municipality);
    }

    public static function userCanViewAgenda(User $user, AgendaItem $agenda): bool
    {
        if ($user->canEncode() || $user->isBoardMember()) {
            return true;
        }

        if (! $user->isMunicipalViewer() || $user->municipality === null) {
            return false;
        }

        return self::agendaBelongsToMunicipality($agenda, $user->municipality);
    }

    public static function userCanViewResolution(User $user, Resolution $resolution): bool
    {
        if ($user->canEncode() || $user->isBoardMember()) {
            return true;
        }

        if (! $user->isMunicipalViewer() || $user->municipality === null) {
            return false;
        }

        $agenda = $resolution->relationLoaded('publishedFromAgenda')
            ? $resolution->publishedFromAgenda
            : $resolution->publishedFromAgenda()->withTrashed()->first();

        return $agenda instanceof AgendaItem
            && self::agendaBelongsToMunicipality($agenda, $user->municipality);
    }

    public static function userCanViewOrdinance(User $user, Ordinance $ordinance): bool
    {
        if ($user->canEncode() || $user->isBoardMember() || $user->isMunicipalViewer()) {
            return true;
        }

        return false;
    }

    public static function userCanViewAppropriationOrdinance(User $user, AppropriationOrdinance $appropriationOrdinance): bool
    {
        if ($user->canEncode() || $user->isBoardMember()) {
            return true;
        }

        if (! $user->isMunicipalViewer() || $user->municipality === null) {
            return false;
        }

        $agenda = $appropriationOrdinance->relationLoaded('agendaItem')
            ? $appropriationOrdinance->agendaItem
            : $appropriationOrdinance->agendaItem()->withTrashed()->first();

        if ($agenda instanceof AgendaItem && self::agendaBelongsToMunicipality($agenda, $user->municipality)) {
            return true;
        }

        if ($appropriationOrdinance->agenda_item_id === null) {
            return false;
        }

        $linkedAgenda = AgendaItem::query()->withTrashed()->find($appropriationOrdinance->agenda_item_id);

        return $linkedAgenda instanceof AgendaItem
            && self::agendaBelongsToMunicipality($linkedAgenda, $user->municipality);
    }
}
