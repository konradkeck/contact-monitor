<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartNoteFilter extends Model
{
    protected $fillable = [
        'type',
        'criteria',
        'as_internal_note',
        'is_active',
    ];

    protected $casts = [
        'criteria' => 'array',
        'as_internal_note' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function smartNotes(): HasMany
    {
        return $this->hasMany(SmartNote::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'email_message' => 'Email Message',
            'email_subject' => 'Email Subject',
            'discord_any'   => 'Discord',
            'slack_any'     => 'Slack',
            default         => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function summaryLabel(): string
    {
        $criteria = $this->criteria ?? [];

        return match ($this->type) {
            'email_message' => implode(' · ', array_filter([
                !empty($criteria['mailbox_slugs']) ? implode(', ', $criteria['mailbox_slugs']) : 'All mailboxes',
                !empty($criteria['address']) ? ($criteria['address'] . ' (' . ($criteria['direction'] ?? 'any') . ')') : null,
            ])),
            'email_subject' => 'Subject contains: ' . ($criteria['keyword'] ?? '—'),
            'discord_any'   => !empty($criteria['connection_slug'])
                ? 'Connection: ' . $criteria['connection_slug']
                : 'Any Discord',
            'slack_any'     => !empty($criteria['connection_slug'])
                ? 'Workspace: ' . $criteria['connection_slug']
                : 'Any Slack',
            default         => '',
        };
    }
}
