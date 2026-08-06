<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Static permission matrix for documentation (Admin → Role permissions).
 *
 * @phpstan-type Cell 'yes'|'no'|'limited'
 */
class RolePermissionMatrix
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function roles(): array
    {
        return collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role === UserRole::Guest)
            ->map(fn (UserRole $role) => [
                'key' => $role->value,
                'label' => $role->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{group: string, action: string, cells: array<string, Cell>}>
     */
    public static function rows(): array
    {
        $e = UserRole::Encoder->value;
        $ed = UserRole::EncoderDelete->value;
        $a = UserRole::Admin->value;
        $s = UserRole::Superadmin->value;
        $b = UserRole::BoardMember->value;
        $m = UserRole::MunicipalViewer->value;

        $encode = [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'no', $m => 'no'];
        $admin = [$e => 'no', $ed => 'no', $a => 'yes', $s => 'yes', $b => 'no', $m => 'no'];
        $super = [$e => 'no', $ed => 'no', $a => 'no', $s => 'yes', $b => 'no', $m => 'no'];
        $staff = [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'no', $m => 'no'];
        $everyoneButMunicipal = [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'yes', $m => 'no'];

        return [
            // Agenda
            ['group' => 'Agenda', 'action' => 'View list / search', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'limited']],
            ['group' => 'Agenda', 'action' => 'Create / edit', 'cells' => $encode],
            ['group' => 'Agenda', 'action' => 'Soft-delete (trash)', 'cells' => $encode],
            ['group' => 'Agenda', 'action' => 'Archive / restore archive', 'cells' => $admin],
            ['group' => 'Agenda', 'action' => 'Add / remove from Order of Business', 'cells' => $encode],

            // Incoming
            ['group' => 'Incoming', 'action' => 'View list', 'cells' => $staff],
            ['group' => 'Incoming', 'action' => 'Create / edit / link / publish', 'cells' => $encode],

            // Resolutions
            ['group' => 'Resolutions', 'action' => 'View list / search', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'limited']],
            ['group' => 'Resolutions', 'action' => 'Create / edit', 'cells' => $encode],
            ['group' => 'Resolutions', 'action' => 'Soft-delete (trash)', 'cells' => $encode],
            ['group' => 'Resolutions', 'action' => 'Restore / permanent delete', 'cells' => $super],

            // Ordinances
            ['group' => 'Ordinances', 'action' => 'View list / search', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'yes', $m => 'limited']],
            ['group' => 'Ordinances', 'action' => 'Create / edit / soft-delete', 'cells' => $encode],

            // Appropriation ordinances
            ['group' => 'Appropriation ordinances', 'action' => 'View list / search', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'limited']],
            ['group' => 'Appropriation ordinances', 'action' => 'Create / edit / soft-delete', 'cells' => $encode],

            // Committees & roster
            ['group' => 'Committees / board members', 'action' => 'View committees & roster', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'no']],
            ['group' => 'Committees / board members', 'action' => 'Manage / soft-delete', 'cells' => $encode],
            ['group' => 'Committees / board members', 'action' => 'Committee monitoring', 'cells' => $staff],
            ['group' => 'Committees / board members', 'action' => 'Schedule committee referrals', 'cells' => $encode],
            ['group' => 'Committees / board members', 'action' => 'Staff committee reports list', 'cells' => $encode],

            // Directory
            ['group' => 'Directory', 'action' => 'View / manage entries', 'cells' => $encode],

            // References
            ['group' => 'References', 'action' => 'View', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'no', $m => 'yes']],
            ['group' => 'References', 'action' => 'Create / edit / archive / soft-delete', 'cells' => $encode],

            // Order of Business
            ['group' => 'Order of Business', 'action' => 'View sessions', 'cells' => [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'no']],
            ['group' => 'Order of Business', 'action' => 'Sessions / OB Maker', 'cells' => $encode],
            ['group' => 'Order of Business', 'action' => 'Record attendance', 'cells' => $admin],

            // Board Member portal
            ['group' => 'Board Member portal', 'action' => 'Dashboard / My Agenda / My Committees', 'cells' => [$e => 'no', $ed => 'no', $a => 'no', $s => 'no', $b => 'yes', $m => 'no']],
            ['group' => 'Board Member portal', 'action' => 'Submit / edit own committee reports', 'cells' => [$e => 'no', $ed => 'no', $a => 'no', $s => 'no', $b => 'limited', $m => 'no']],
            ['group' => 'Board Member portal', 'action' => 'Watchlist / My Profile', 'cells' => [$e => 'no', $ed => 'no', $a => 'no', $s => 'no', $b => 'yes', $m => 'no']],

            // Municipal portal
            ['group' => 'Municipal portal', 'action' => 'My Requests (own municipality)', 'cells' => [$e => 'no', $ed => 'no', $a => 'no', $s => 'no', $b => 'no', $m => 'yes']],

            // Admin
            ['group' => 'Admin', 'action' => 'Executive dashboard / analytics', 'cells' => [$e => 'no', $ed => 'no', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'no']],
            ['group' => 'Admin', 'action' => 'Archives', 'cells' => $admin],
            ['group' => 'Admin', 'action' => 'Email notification settings', 'cells' => $admin],
            ['group' => 'Admin', 'action' => 'Icon Library / Page backgrounds', 'cells' => $admin],
            ['group' => 'Admin', 'action' => 'Users / Data Sync / Backups / Trash', 'cells' => $super],
            ['group' => 'Admin', 'action' => 'Role permissions page', 'cells' => $super],
        ];
    }

    public static function cellLabel(string $cell): string
    {
        return match ($cell) {
            'yes' => 'Yes',
            'limited' => 'Limited',
            default => 'No',
        };
    }
}
