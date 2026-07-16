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
        $viewStaff = [$e => 'yes', $ed => 'yes', $a => 'yes', $s => 'yes', $b => 'limited', $m => 'limited'];

        return [
            ['group' => 'Resolutions', 'action' => 'View list / search', 'cells' => array_merge($viewStaff, [$b => 'no', $m => 'limited'])],
            ['group' => 'Resolutions', 'action' => 'Create / edit', 'cells' => $encode],
            ['group' => 'Resolutions', 'action' => 'Move to trash', 'cells' => $encode],
            ['group' => 'Resolutions', 'action' => 'Restore / permanent delete', 'cells' => $super],
            ['group' => 'Ordinances', 'action' => 'Create / edit / trash', 'cells' => $encode],
            ['group' => 'Appropriation ordinances', 'action' => 'Create / edit', 'cells' => $encode],
            ['group' => 'Appropriation ordinances', 'action' => 'Move to trash', 'cells' => $admin],
            ['group' => 'Agenda', 'action' => 'Create / edit / trash', 'cells' => array_merge($encode, [])],
            ['group' => 'Committees / board members', 'action' => 'Manage / trash', 'cells' => $encode],
            ['group' => 'References', 'action' => 'Create / edit / archive / trash', 'cells' => $encode],
            ['group' => 'Order of Business', 'action' => 'Sessions / OB Maker', 'cells' => $encode],
            ['group' => 'Order of Business', 'action' => 'Record attendance', 'cells' => $admin],
            ['group' => 'Order of Business', 'action' => 'Board view (scheduled)', 'cells' => [$e => 'no', $ed => 'no', $a => 'yes', $s => 'yes', $b => 'yes', $m => 'no']],
            ['group' => 'Admin', 'action' => 'Executive dashboard', 'cells' => $admin],
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
