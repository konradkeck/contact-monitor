<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NoteLink extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'note_id',
        'linkable_type',
        'linkable_id',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
