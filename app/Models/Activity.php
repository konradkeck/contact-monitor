<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'person_id',
        'type',
        'reference_type',
        'reference_id',
        'occurred_at',
        'meta_json',
        'target_url',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function reference(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('reference');
    }

    public function targetUrl(): ?string
    {
        return $this->target_url ?? $this->meta_json['target_url'] ?? null;
    }

    /**
     * Classify event direction for timeline display.
     * meta_json['direction'] takes precedence if set.
     */
    public function direction(): string
    {
        if (! empty($this->meta_json['direction'])) {
            return $this->meta_json['direction'];
        }

        // For email activities: use is_outbound flag
        if (isset($this->meta_json['is_outbound'])) {
            return $this->meta_json['is_outbound'] ? 'internal' : 'customer';
        }

        return in_array($this->type, ['payment', 'renewal', 'cancellation', 'ticket', 'conversation'])
            ? 'customer'
            : 'internal';
    }

    /**
     * For conversation-type activities: infer the underlying channel_type.
     * Checks meta_json.channel_type first, then falls back to system_type mapping.
     */
    public function conversationChannelType(): ?string
    {
        if ($this->type !== 'conversation') {
            return null;
        }

        if (! empty($this->meta_json['channel_type'])) {
            return $this->meta_json['channel_type'];
        }

        return match ($this->meta_json['system_type'] ?? '') {
            'metricscube', 'whmcs' => 'ticket',
            'discord' => 'discord',
            'slack' => 'slack',
            'imap', 'gmail' => 'email',
            default => null,
        };
    }

    /** Human-readable label for timeline. */
    public function timelineLabel(): string
    {
        return match ($this->type) {
            'payment' => 'Payment received',
            'renewal' => 'Subscription renewed',
            'cancellation' => 'Cancellation request',
            'ticket' => 'Support ticket',
            'conversation' => ucfirst($this->conversationChannelType() ?? 'Conversation'),
            'note' => 'Other',
            'status_change' => 'Status changed',
            'campaign_run' => 'Campaign run',
            'ai_summary' => 'AI summary generated',
            'followup' => 'Follow-up',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    /** Tailwind color classes per type for timeline bubble border+bg. */
    public function timelineColor(): string
    {
        return match ($this->type) {
            'payment' => 'bg-green-50 text-green-800 border-green-200',
            'renewal' => 'bg-blue-50 text-blue-800 border-blue-200',
            'cancellation' => 'bg-red-50 text-red-800 border-red-200',
            'ticket' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'conversation' => 'bg-purple-50 text-purple-800 border-purple-200',
            'note' => 'bg-gray-50 text-gray-700 border-gray-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    /** Dot fill color on the timeline axis. */
    public function dotColor(): string
    {
        return match ($this->type) {
            'payment' => 'bg-green-400 ring-green-200',
            'renewal' => 'bg-blue-400 ring-blue-200',
            'cancellation' => 'bg-red-400 ring-red-200',
            'ticket' => 'bg-yellow-400 ring-yellow-200',
            'conversation' => 'bg-purple-400 ring-purple-200',
            'note' => 'bg-gray-400 ring-gray-200',
            default => 'bg-slate-300 ring-slate-200',
        };
    }
}
