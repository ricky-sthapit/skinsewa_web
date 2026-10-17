<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    protected $table = 'places';

    public $timestamps = false;

    protected $fillable = ['name', 'name_ne', 'type', 'parent', 'level'];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent', 'id');
    }
}
