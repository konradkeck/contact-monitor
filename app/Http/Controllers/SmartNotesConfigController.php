<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Identity;
use App\Models\SmartNote;
use App\Models\SmartNoteFilter;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmartNotesConfigController extends Controller
{
    public function index(): View
    {
        $enabled  = SystemSetting::get('smart_notes_enabled', false);
        $filters  = SmartNoteFilter::orderBy('created_at')->get();
        $activeTab = request('tab', 'filtering');

        return view('configuration.smart-notes.index', compact('enabled', 'filters', 'activeTab'));
    }

    public function createFilter(): View
    {
        $emailMailboxes = Conversation::select('system_type', 'system_slug')
            ->whereIn('channel_type', ['email', 'ticket'])
            ->distinct()
            ->orderBy('system_slug')
            ->get();

        $discordConnections = Conversation::select('system_slug')
            ->where('channel_type', 'discord')
            ->distinct()
            ->orderBy('system_slug')
            ->get()
            ->pluck('system_slug');

        $slackWorkspaces = Conversation::select('system_slug')
            ->where('channel_type', 'slack')
            ->distinct()
            ->orderBy('system_slug')
            ->get()
            ->pluck('system_slug');

        return view('configuration.smart-notes.create-filter', compact('emailMailboxes', 'discordConnections', 'slackWorkspaces'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        SystemSetting::set('smart_notes_enabled', (bool) $request->boolean('enabled'));

        return back()->with('success', 'Settings saved.');
    }

    public function storeFilter(Request $request): RedirectResponse
    {
        $request->validate([
            'type'             => ['required', 'in:email_message,email_subject,discord_any,slack_any'],
            'as_internal_note' => ['boolean'],
        ]);

        $type     = $request->input('type');
        $criteria = match ($type) {
            'email_message' => [
                'mailbox_slugs' => array_values(array_filter((array) $request->input('mailbox_slugs', []))),
                'address'       => $request->validate(['address' => 'required|string|email'])['address'],
                'direction'     => $request->input('direction', 'any'),
            ],
            'email_subject' => [
                'keyword' => $request->validate(['keyword' => 'required|string'])['keyword'],
            ],
            'discord_any' => array_filter([
                'connection_slug' => $request->input('connection_slug') ?: null,
            ], fn ($v) => $v !== null),
            'slack_any' => array_filter([
                'connection_slug' => $request->input('connection_slug') ?: null,
            ], fn ($v) => $v !== null),
        };

        SmartNoteFilter::create([
            'type'             => $type,
            'criteria'         => $criteria,
            'as_internal_note' => $request->boolean('as_internal_note'),
            'is_active'        => true,
        ]);

        return redirect()->route('smart-notes.config.index')->with('success', 'Filter added.');
    }

    public function destroyFilter(SmartNoteFilter $filter): RedirectResponse
    {
        $filter->delete();

        return back()->with('success', 'Filter deleted.');
    }

    public function scan(Request $request): RedirectResponse
    {
        $filters = SmartNoteFilter::where('is_active', true)->get();

        $scanned = 0;
        $created = 0;

        foreach ($filters as $filter) {
            $criteria = $filter->criteria ?? [];

            $conversations = match ($filter->type) {
                'email_message' => $this->queryEmailMessage($criteria),
                'email_subject' => $this->queryEmailSubject($criteria),
                'discord_any'   => $this->queryDiscordAny($criteria),
                'slack_any'     => $this->querySlackAny($criteria),
                default         => collect(),
            };

            foreach ($conversations as $conversation) {
                $scanned++;

                $alreadyExists = SmartNote::withTrashed()
                    ->where('source_external_id', $conversation->external_thread_id)
                    ->where('smart_note_filter_id', $filter->id)
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                $firstMessage = ConversationMessage::where('conversation_id', $conversation->id)
                    ->where('direction', '!=', 'system')
                    ->orderBy('occurred_at')
                    ->first();

                if (! $firstMessage) {
                    continue;
                }

                SmartNote::create([
                    'smart_note_filter_id' => $filter->id,
                    'source_type'          => $this->mapChannelToSourceType($conversation->channel_type),
                    'source_external_id'   => $conversation->external_thread_id,
                    'content'              => $firstMessage->body_text ?? '',
                    'sender_name'          => $firstMessage->author_name ?? null,
                    'sender_value'         => $firstMessage->sender_external_id ?? null,
                    'occurred_at'          => $firstMessage->occurred_at ?? $conversation->started_at,
                    'as_internal_note'     => $filter->as_internal_note,
                    'status'               => 'unrecognized',
                    'segments_json'        => null,
                ]);

                $created++;
            }
        }

        return back()->with('success', "Scanned {$scanned} conversations, created {$created} new Smart Notes.");
    }

    private function queryEmailMessage(array $criteria)
    {
        $address   = strtolower($criteria['address'] ?? '');
        $direction = $criteria['direction'] ?? 'any';

        $query = Conversation::whereIn('channel_type', ['email', 'ticket']);

        if (!empty($criteria['mailbox_slugs'])) {
            $query->whereIn('system_slug', $criteria['mailbox_slugs']);
        }

        if (!empty($address)) {
            $query->whereHas('participants', function ($q) use ($address, $direction) {
                $q->whereHas('identity', function ($q2) use ($address) {
                    $q2->where('type', 'email')->where('value_normalized', $address);
                });
                if ($direction === 'from') {
                    $q->where('role', 'sender');
                } elseif ($direction === 'to') {
                    $q->where('role', '!=', 'sender');
                }
            });
        }

        return $query->get();
    }

    private function queryEmailSubject(array $criteria)
    {
        $keyword = $criteria['keyword'] ?? '';

        if (empty($keyword)) {
            return collect();
        }

        return Conversation::whereIn('channel_type', ['email', 'ticket'])
            ->where('subject', 'ilike', '%' . $keyword . '%')
            ->get();
    }

    private function queryDiscordAny(array $criteria)
    {
        $query = Conversation::where('channel_type', 'discord');

        if (! empty($criteria['connection_slug'])) {
            $query->where('system_slug', $criteria['connection_slug']);
        }

        return $query->get();
    }

    private function querySlackAny(array $criteria)
    {
        $query = Conversation::where('channel_type', 'slack');

        if (! empty($criteria['connection_slug'])) {
            $query->where('system_slug', $criteria['connection_slug']);
        }

        return $query->get();
    }

    private function mapChannelToSourceType(string $channelType): string
    {
        return match ($channelType) {
            'email'   => 'email',
            'ticket'  => 'ticket',
            'discord' => 'discord',
            'slack'   => 'slack',
            default   => $channelType,
        };
    }
}
