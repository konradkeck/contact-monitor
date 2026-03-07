<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignRun extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'status',
        'parameters_json',
        'result_summary',
        'file_path',
        'generated_at',
    ];

    protected $casts = [
        'parameters_json' => 'array',
        'generated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
