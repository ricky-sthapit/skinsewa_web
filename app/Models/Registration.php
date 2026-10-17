<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Registration extends Model
{
    use Notifiable;

    protected $table = 'registration';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'code',
        'is_registered',
        'created_at',
        'role',
        'facility_name',
        'invited_by',
        'otp_expires_at',
        'otp_attempts',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_registered' => 'boolean',
            'created_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_attempts' => 'integer',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getUserTypeLabelAttribute(): string
    {
        return match ($this->role) {
            User::ROLE_MEDICAL_OFFICER => 'Health worker',
            User::ROLE_DERMATOLOGIST => 'Dermatologist',
            default => 'Unknown',
        };
    }

    public function getInvitationStatusAttribute(): string
    {
        if ($this->is_registered) {
            return 'accepted';
        }

        if ($this->revoked_at) {
            return 'revoked';
        }

        return ! $this->otp_expires_at || $this->otp_expires_at->isPast() ? 'expired' : 'pending';
    }
}
