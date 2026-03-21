<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatProjectPin extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'chat_id', 'project_id'];

    protected $casts = [
        'pinned_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AiChat::class, 'chat_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AiProject::class, 'project_id');
    }
}
