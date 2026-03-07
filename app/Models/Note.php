<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'content',
        'source',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(NoteLink::class);
    }
}
