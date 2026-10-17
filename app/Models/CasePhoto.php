<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasePhoto extends Model
{
    protected $table = 'images';

    public $timestamps = false;

    protected $fillable = [
        'filename', 'case_id', 'query_id', 'user_id', 'created_at', 'storage_path', 'storage_disk',
        'mime_type', 'size_bytes', 'sort_order', 'caption', 'sha256',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CaseMessage::class, 'query_id');
    }
}
