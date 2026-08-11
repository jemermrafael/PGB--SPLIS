<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\UserCapability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'legacy_user_id', 'is_active', 'board_member_id', 'municipality_id', 'notification_preferences', 'capabilities'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
            'capabilities' => 'array',
        ];
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function boardMember(): BelongsTo
    {
        return $this->belongsTo(BoardMember::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()
            ->withinRetention()
            ->whereNull('read_at');
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canEncode(): bool
    {
        return $this->role->canCreate();
    }

    /**
     * Module capabilities apply to Encoder / Encoder Delete.
     * Admin and Superadmin always have every module.
     * Null capabilities means all modules (legacy default).
     *
     * @return list<string>
     */
    public function effectiveCapabilities(): array
    {
        if ($this->canAdmin()) {
            return UserCapability::keys();
        }

        if (! $this->canEncode()) {
            return [];
        }

        if ($this->capabilities === null) {
            return UserCapability::keys();
        }

        return array_values(array_intersect(
            UserCapability::keys(),
            array_map('strval', $this->capabilities),
        ));
    }

    public function hasModuleCapability(string $capability): bool
    {
        return in_array($capability, $this->effectiveCapabilities(), true);
    }

    /**
     * Checkbox state for the user form (null capabilities → all checked).
     *
     * @return array<string, bool>
     */
    public function capabilitySelections(): array
    {
        $selected = $this->capabilities === null
            ? UserCapability::keys()
            : $this->effectiveCapabilities();

        return collect(UserCapability::keys())
            ->mapWithKeys(fn (string $key) => [$key => in_array($key, $selected, true)])
            ->all();
    }

    public function usesModuleCapabilities(): bool
    {
        return $this->hasRole(UserRole::Encoder, UserRole::EncoderDelete);
    }

    public function canDeleteResolutions(): bool
    {
        return $this->role->canDelete();
    }

    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    /**
     * Settings: Icon Library, page backgrounds, committee icon overrides — admin and superadmin.
     */
    public function canManageIconLibrary(): bool
    {
        return $this->canAdmin();
    }

    /**
     * Settings: email notification types and SMTP — admin and superadmin.
     */
    public function canManageEmailNotifications(): bool
    {
        return $this->canAdmin();
    }

    public function isBoardMember(): bool
    {
        return $this->role === UserRole::BoardMember;
    }

    public function isViceGovernorBoardMember(): bool
    {
        return $this->isBoardMember()
            && $this->boardMember?->isViceGovernor() === true;
    }

    /**
     * Full province-wide Executive Dashboard (admin/superadmin or Vice Governor BM).
     */
    public function seesFullExecutiveDashboard(): bool
    {
        return $this->canAdmin() || $this->isViceGovernorBoardMember();
    }

    public function isMunicipalViewer(): bool
    {
        return $this->role === UserRole::MunicipalViewer;
    }

    public function receivesInAppNotifications(): bool
    {
        return $this->isBoardMember() || $this->canAdmin() || $this->isMunicipalViewer();
    }

    public function canAdmin(): bool
    {
        return $this->role->canAdmin();
    }

    public function canRecordAttendance(): bool
    {
        return $this->role->canRecordAttendance();
    }
}
