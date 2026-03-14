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

    /**
     * Compute all display variables needed by the timeline-items partial.
     * Returns an object with: url, isCustomer, chType, sysType, sysSlug, badgeTitle,
     * sourceLabel, titleText, modalUrl, rowClickable, ticketNotFound, useBadge, hoverText.
     */
    public function timelineDisplayData(array $convSubjectMap = []): object
    {
        $meta       = $this->meta_json ?? [];
        $url        = $this->targetUrl();
        $isCustomer = $this->direction() === 'customer';
        $chType     = $this->conversationChannelType();
        $sysType    = $meta['system_type'] ?? '';
        $sysSlug    = $meta['system_slug'] ?? '';
        $badgeTitle = $chType ? (ucfirst($chType) . ': ' . $sysSlug) : null;

        $mcType    = $meta['mc_type'] ?? '';
        $isMcTicket = in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true);

        // WHMCS-native ticket lookup
        $convExtId    = $meta['conversation_external_id'] ?? '';
        $convMapEntry = $convSubjectMap[$convExtId] ?? null;
        if (! $url && $convMapEntry) {
            $url = '/conversations/' . $convMapEntry['id'];
        }

        // MetricsCube ticket lookup
        $mcRelId    = $meta['relation_id'] ?? null;
        $mcMapEntry = ($isMcTicket && $mcRelId) ? ($convSubjectMap['ticket_' . $mcRelId] ?? null) : null;
        if ($isMcTicket && ! $url && $mcMapEntry) {
            $url = '/conversations/' . $mcMapEntry['id'];
        }

        // Source label
        $sourceLabel = null;
        $ticketNum   = null;
        if ($chType === 'email') {
            $sourceLabel = $meta['contact_email'] ?? null;
        } elseif ($chType === 'discord' || $chType === 'slack') {
            $sourceLabel = $meta['description'] ?? null;
        } elseif ($chType === 'ticket') {
            preg_match('/ticket_(\d+)/', $convExtId, $_tm);
            $ticketNum = $_tm[1] ?? null;
        }

        // Title text
        $titleText = null;
        if ($chType === 'email') {
            $titleText = $meta['subject'] ?? $meta['description'] ?? null;
        } elseif ($chType === 'ticket') {
            if ($isMcTicket) {
                if ($mcMapEntry) {
                    $titleText = $mcMapEntry['subject'];
                } else {
                    $desc     = $meta['description'] ?? null;
                    $customer = trim($meta['customer'] ?? '');
                    if ($desc && $customer && mb_stripos($desc, $customer) === 0) {
                        $desc = trim(mb_substr($desc, mb_strlen($customer)));
                        $desc = preg_replace('/^[\s\-\x{2013}\x{2014}]+/u', '', $desc);
                    }
                    $titleText = $desc;
                }
            } else {
                $subject   = $meta['subject'] ?? ($convMapEntry ? $convMapEntry['subject'] : null);
                $titleText = $ticketNum
                    ? ('#' . $ticketNum . ($subject ? " \xE2\x80\x94 " . $subject : ''))
                    : ($subject ?? $meta['description'] ?? null);
            }
        } elseif ($chType === 'discord' || $chType === 'slack') {
            $titleText = null;
        } else {
            $titleText = $meta['description'] ?? $meta['text'] ?? $meta['subject'] ?? $meta['title'] ?? null;
        }

        // Modal URL
        $modalDate = null;
        if (in_array($chType, ['discord', 'slack'], true)) {
            $modalDate = $this->occurred_at->format('Y-m-d');
        }
        $modalUrl = ($url && preg_match('#^/conversations/(\d+)$#', $url))
            ? $url . '/modal' . ($modalDate ? '?date=' . $modalDate : '')
            : null;

        $rowClickable = in_array($chType, ['discord', 'slack'], true) && $modalUrl;

        // Ticket not found indicator
        $ticketNotFound = null;
        if ($isMcTicket && ! $url) {
            $ticketNotFound = $mcRelId;
        } elseif ($chType === 'ticket' && ! $url) {
            preg_match('/ticket_(\d+)/', $convExtId, $_tm3);
            $ticketNotFound = $_tm3[1] ?? null;
        }

        $useBadge = ($chType === null && $sysType !== 'metricscube')
                 || ($chType === null && $sysType === 'metricscube' && ! $isMcTicket);

        // Hover text
        $hoverText = $titleText ?? '';
        if ($ticketNotFound !== null) {
            $notFoundMsg = 'Cannot find corresponding ticket in WHMCS' . ($sysSlug ? ' ' . $sysSlug : '');
            $hoverText   = ($hoverText !== '' ? $hoverText . "\n" : '') . $notFoundMsg;
        }

        return (object) compact(
            'url', 'isCustomer', 'chType', 'sysType', 'sysSlug', 'badgeTitle',
            'sourceLabel', 'titleText', 'modalUrl', 'rowClickable', 'ticketNotFound',
            'useBadge', 'hoverText'
        );
    }
}
