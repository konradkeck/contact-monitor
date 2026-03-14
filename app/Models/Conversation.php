<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'primary_person_id',
        'channel_type',
        'system_type',
        'system_slug',
        'subject',
        'external_thread_id',
        'message_count',
        'started_at',
        'last_message_at',
        'is_archived',
        'archived_at',
        'sync_protected',
        'meta_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'archived_at' => 'datetime',
        'message_count' => 'integer',
        'is_archived' => 'boolean',
        'sync_protected' => 'boolean',
        'meta_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function primaryPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'primary_person_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('occurred_at');
    }

    /**
     * Replace Discord mention IDs (<@123>) with display names.
     */
    public function resolveMentions(?string $text, array $discordMentionMap = []): string
    {
        if ($this->channel_type === 'discord') {
            return preg_replace_callback(
                '/<@!?(\d+)>/',
                fn ($m) => '@'.($discordMentionMap[$m[1]] ?? $m[1]),
                $text ?? ''
            );
        }

        return $text ?? '';
    }

    /**
     * Ticket display data for the messages partial header.
     */
    public function ticketDisplayData(\Illuminate\Support\Collection $messages): object
    {
        $firstMsgMeta = $messages->first()?->meta_json ?? [];
        $ticketStatus = $firstMsgMeta['status'] ?? $firstMsgMeta['ticket_status'] ?? $this->meta_json['status'] ?? null;
        $ticketDept = $firstMsgMeta['dept'] ?? $this->meta_json['dept'] ?? null;
        $priority = $firstMsgMeta['priority'] ?? null;
        preg_match('/ticket_(\d+)/', $this->external_thread_id ?? '', $m);
        $ticketNumber = $m[1] ?? null;
        $ticketTitle = $this->subject ?? null;
        $hasTicketInfo = $ticketStatus || $ticketDept || $priority || $ticketNumber;

        $statusColor = match (strtolower($ticketStatus ?? '')) {
            'open' => 'green',
            'answered' => 'blue',
            'customer-reply', 'pending' => 'yellow',
            'closed', 'resolved' => 'gray',
            default => 'blue',
        };
        $priorityColor = match (strtolower($priority ?? '')) {
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'gray',
            default => 'blue',
        };

        $ticketHeading = '';
        if ($ticketNumber) {
            $ticketHeading .= '#'.$ticketNumber;
        }
        if ($ticketNumber && $ticketTitle) {
            $ticketHeading .= ' — ';
        }
        if ($ticketTitle) {
            $ticketHeading .= $ticketTitle;
        }

        return (object) compact(
            'ticketStatus', 'ticketDept', 'priority', 'ticketNumber',
            'ticketTitle', 'hasTicketInfo', 'statusColor', 'priorityColor', 'ticketHeading'
        );
    }

    /**
     * Channel display config for the messages partial.
     */
    public function channelConfig(): array
    {
        $map = [
            'email'   => ['label' => 'Email',   'color' => 'bg-sky-100 text-sky-800 border-sky-200',        'icon' => "\u{2709}"],
            'slack'   => ['label' => 'Slack',   'color' => 'bg-[#f4ede8] text-[#4A154B] border-[#d9b8d3]', 'icon' => '#'],
            'discord' => ['label' => 'Discord', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'icon' => "\u{25C8}"],
            'ticket'  => ['label' => 'Ticket',  'color' => 'bg-amber-50 text-amber-800 border-amber-200',   'icon' => "\u{1F3AB}"],
        ];

        return $map[$this->channel_type] ?? [
            'label' => ucfirst($this->channel_type),
            'color' => 'bg-gray-100 text-gray-700 border-gray-200',
            'icon'  => "\u{1F4AC}",
        ];
    }
}
