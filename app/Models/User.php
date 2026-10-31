<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'ign', 'full_name', 'email', 'password', 'role', 'is_active', 'pin_set_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values, so a new model matches the DB defaults before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'pin_set_at' => 'datetime',
        ];
    }

    /**
     * Usernames are stored lowercase, so the unique index ignores case.
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower($value),
        );
    }

    /**
     * The IGN keeps its casing; a lowercase copy carries the unique index.
     */
    protected function ign(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => [
                'ign' => $value,
                'ign_normalized' => mb_strtolower($value),
            ],
        );
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    /**
     * A user's PIN lives in the nullable password column. Admin passwords are not PINs.
     */
    public function hasPin(): bool
    {
        return $this->hasRole(Role::User) && $this->password !== null;
    }

    /**
     * The account part of leaderboard eligibility (FR-L2): active, and has a PIN or is an admin.
     * M6's Eligibility adds the attempt conditions on top.
     */
    #[Scope]
    protected function leaderboardEligible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('role', Role::Admin)
                ->orWhereNotNull('password'));
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }
}
