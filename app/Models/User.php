<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_MEDICAL_OFFICER = 'MEDICAL_OFFICER';

    public const ROLE_DERMATOLOGIST = 'DERMATOLOGIST';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_MEDICAL_OFFICER,
        self::ROLE_DERMATOLOGIST,
    ];

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'user_type',
        'is_admin',
        'phone',
        'facility_name',
        'specialty',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->user_type === self::ROLE_ADMIN || $this->is_admin;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function role(): string
    {
        return $this->isAdministrator() ? self::ROLE_ADMIN : $this->user_type;
    }

    /** The account's clinical job, independent of administrator access. */
    public function clinicalRole(): ?string
    {
        return in_array($this->user_type, [self::ROLE_MEDICAL_OFFICER, self::ROLE_DERMATOLOGIST], true)
            ? $this->user_type
            : null;
    }

    public function hasRole(string $role): bool
    {
        return $role === self::ROLE_ADMIN
            ? $this->isAdministrator()
            : $this->clinicalRole() === $role;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_values(array_filter([
            $this->isAdministrator() ? self::ROLE_ADMIN : null,
            $this->clinicalRole(),
        ]));
    }

    public function roleLabel(): string
    {
        $labels = [];
        if ($this->isAdministrator()) {
            $labels[] = 'Administrator';
        }
        if ($this->clinicalRole() === self::ROLE_MEDICAL_OFFICER) {
            $labels[] = 'Medical Officer';
        } elseif ($this->clinicalRole() === self::ROLE_DERMATOLOGIST) {
            $labels[] = 'Dermatologist';
        }

        return implode(' · ', $labels) ?: 'Care team';
    }

    public function fcmDeviceTokens(): HasMany
    {
        return $this->hasMany(FcmDeviceToken::class);
    }
}
