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
}
