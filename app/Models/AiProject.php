<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProject extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(AiChat::class, 'project_id');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(AiChatProjectPin::class, 'project_id');
    }
}
