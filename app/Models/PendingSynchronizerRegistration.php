<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingSynchronizerRegistration extends Model
{
    protected $fillable = ['token', 'api_token', 'expires_at', 'registered_at', 'registered_url'];

    protected $casts = [
        'expires_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function isRegistered(): bool
    {
        return $this->registered_at !== null;
    }
}
