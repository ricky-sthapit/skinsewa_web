<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationalResource extends Model
{
    protected $fillable = [
        'display_name',
        'original_file_name',
        'file_path',
        'file_size_bytes',
    ];

    protected function casts(): array
    {
        return ['file_size_bytes' => 'integer'];
    }
}
