<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'phc', 'finding', 'history', 'symptoms', 'patient_id', 'created_by',
        'assigned_to', 'status', 'priority', 'consent_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CasePhoto::class, 'case_id')->orderBy('sort_order')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'case_id')->orderBy('id');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class, 'case_id')->orderByDesc('created_at')->orderByDesc('id');
    }
}
