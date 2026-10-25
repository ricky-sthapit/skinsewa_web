<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Treatment extends Model
{
    protected $table = 'treatments';

    protected $fillable = ['diagnosis', 'prevention', 'treatment', 'referral', 'follow_up_at', 'treatment_by', 'case_id'];

    protected function casts(): array
    {
        return ['follow_up_at' => 'datetime'];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'treatment_by');
    }
}
