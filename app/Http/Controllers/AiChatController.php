<?php
namespace App\Http\Controllers;

use App\Ai\Pricing\PricingRegistry;
use App\Ai\Providers\AiProviderFactory;
use App\Ai\ToolRegistry;
use App\Events\AiMessageChunk;
use App\Events\AiMessageComplete;
use App\Events\ChatTitleGenerated;
use App\Events\ParticipantUpdated;
use App\Events\UserMessageAdded;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiChatParticipant;
use App\Models\AiModelConfig;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    /**
     * Create a new chat.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:ai_projects,id'],
            'title'      => ['nullable', 'string', 'max:200'],
        ]);

        // Verify project ownership if provided
        if (!empty($data['project_id'])) {
            $project = \App\Models\AiProject::findOrFail($data['project_id']);
            abort_unless($project->user_id === auth()->id(), 403);
        }

        $chat = AiChat::create([
            'user_id'    => auth()->id(),
            'project_id' => $data['project_id'] ?? null,
            'title'      => $data['title'] ?? null,
        ]);

        return response()->json(['chat' => $chat->toSidebarArray()]);
    }

    /**
     * Update chat (rename, archive, move to project).
     */
    public function update(Request $request, AiChat $chat): JsonResponse
    {
        abort_unless($chat->isOwnedBy(auth()->id()), 403);

        $data = $request->validate([
            'title'       => ['nullable', 'string', 'max:200'],
            'is_archived' => ['nullable', 'boolean'],
            'project_id'  => ['nullable', 'integer', 'exists:ai_projects,id'],
        ]);

        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $chat->title           = $data['title'];
            $chat->title_is_manual = true;
        }

        if (array_key_exists('is_archived', $data)) {
            $chat->is_archived = $data['is_archived'];
        }

        if (array_key_exists('project_id', $data)) {
            if ($data['project_id']) {
                $project = \App\Models\AiProject::findOrFail($data['project_id']);
                abort_unless($project->user_id === auth()->id(), 403);
            }
            $chat->project_id = $data['project_id'];
        }

        $chat->save();

        return response()->json(['chat' => $chat->toSidebarArray()]);
    }

    /**
     * Delete chat.
     */
    public function destroy(AiChat $chat): JsonResponse
    {
        abort_unless($chat->isOwnedBy(auth()->id()), 403);
        $chat->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Send a message and stream AI response via WebSocket.
     * Supports tool use: the AI can call MCP tools to read/write system data.
     */
    public function sendMessage(Request $request, AiChat $chat): JsonResponse
    {
        abort_unless($chat->canAccess(auth()->id()), 403);
        abort_if($chat->is_archived, 422, 'Cannot send to archived conversation.');

        $data = $request->validate([
            'content'    => ['required', 'string', 'max:32000'],
            'message_id' => ['nullable', 'integer'],
        ]);

        $userId         = auth()->id();
        $isFirstMessage = !$chat->messages()->where('role', 'user')->exists();

        // Save user message
        $userMessage = AiChatMessage::create([
            'chat_id'   => $chat->id,
            'role'      => 'user',
            'content'   => $data['content'],
            'meta_json' => ['user_id' => $userId, 'user_name' => auth()->user()->name],
        ]);

        // Update last_message_at
        $chat->update(['last_message_at' => now()]);

        // Broadcast user message to other participants
        if ($chat->is_shared) {
            $this->safeBroadcast(new UserMessageAdded($chat->id, [
                'id'         => $userMessage->id,
                'role'       => 'user',
                'content'    => $userMessage->content,
                'meta'       => $userMessage->meta_json,
                'created_at' => ($userMessage->created_at ?? now())->toIso8601String(),
            ]));
        }

        // Get AI model config
        $modelConfig = AiModelConfig::forAction('analyze');
        if (!$modelConfig) {
            return response()->json(['error' => 'No AI model configured for analyze action.'], 422);
        }

        try {
            $provider = AiProviderFactory::make($modelConfig->credential);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI provider error: ' . $e->getMessage()], 422);
        }

        // Build message history for AI (includes system prompt)
        $history = $this->buildMessageHistory($chat);
        $tools   = ToolRegistry::toolDefinitions();

        // Create placeholder assistant message
        $assistantMessage = AiChatMessage::create([
            'chat_id' => $chat->id,
            'role'    => 'assistant',
            'content' => '',
        ]);

        // Clear stop flag
        $stopKey = "analyse.stop.{$chat->id}";
        Cache::forget($stopKey);

        $fullContent   = '';
        $inputTokens   = 0;
        $outputTokens  = 0;
        $stopped       = false;
        $chunkIndex    = 0;
        $toolsUsed     = [];
        $maxToolRounds = 10;

        try {
            for ($round = 0; $round < $maxToolRounds; $round++) {
                $roundToolCalls = [];
                $roundText      = '';

                // Stream AI response
                foreach ($provider->stream($modelConfig->model_name, $history, ['tools' => $tools]) as $chunk) {
                    if (Cache::get($stopKey)) {
                        Cache::forget($stopKey);
                        $stopped = true;
                        break 2;
                    }

                    if (is_array($chunk)) {
                        $inputTokens  += $chunk['usage']['input_tokens'] ?? 0;
                        $outputTokens += $chunk['usage']['output_tokens'] ?? 0;
                        $roundToolCalls = $chunk['tool_calls'] ?? [];
                        break;
                    }

                    $roundText .= $chunk;
                    $fullContent .= $chunk;
                    $this->safeBroadcast(new AiMessageChunk($chat->id, $assistantMessage->id, $chunk, $chunkIndex++));
                }

                // No tool calls — done
                if (empty($roundToolCalls)) {
                    break;
                }

                // Build the assistant content_blocks for the API
                $assistantContentBlocks = [];
                if ($roundText !== '') {
                    $assistantContentBlocks[] = ['type' => 'text', 'text' => $roundText];
                }
                foreach ($roundToolCalls as $tc) {
                    $block = [
                        'type'  => 'tool_use',
                        'id'    => $tc['id'],
                        'name'  => $tc['name'],
                        'input' => $tc['input'],
                    ];
                    // Preserve Gemini raw part (includes thoughtSignature)
                    if (isset($tc['_gemini_part'])) {
                        $block['_gemini_part'] = $tc['_gemini_part'];
                    }
                    $assistantContentBlocks[] = $block;
                }

                // Add assistant response with tool_use blocks to history
                $history[] = ['role' => 'assistant', 'content' => $assistantContentBlocks];

                // Execute each tool and build tool_result blocks
                $toolResultBlocks = [];
                foreach ($roundToolCalls as $tc) {
                    try {
                        $result = ToolRegistry::execute($tc['name'], $tc['input']);
                        $toolResultBlocks[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $tc['id'],
                            '_tool_name'  => $tc['name'],
                            'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                        ];
                        $toolsUsed[] = ['name' => $tc['name'], 'input' => $tc['input']];
                    } catch (\Throwable $e) {
                        $toolResultBlocks[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $tc['id'],
                            '_tool_name'  => $tc['name'],
                            'content'     => json_encode(['error' => $e->getMessage()]),
                            'is_error'    => true,
                        ];
                    }
                }

                // Add tool results to history
                $history[] = ['role' => 'user', 'content' => $toolResultBlocks];
            }
        } catch (\Throwable $e) {
            $this->safeBroadcast(new AiMessageComplete($chat->id, $assistantMessage->id, '', 0, 0, true));
            $assistantMessage->delete();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        // Save final message content + tool metadata
        $updateData = ['content' => $fullContent];
        if (!empty($toolsUsed)) {
            $updateData['meta_json'] = ['tool_calls' => $toolsUsed];
        }
        $assistantMessage->update($updateData);
        $chat->update(['last_message_at' => now()]);

        // Broadcast completion
        $this->safeBroadcast(new AiMessageComplete(
            $chat->id, $assistantMessage->id,
            $fullContent, $inputTokens, $outputTokens, $stopped
        ));

        // Log usage
        if ($inputTokens > 0 || $outputTokens > 0) {
            $costInput  = PricingRegistry::calculateCost($modelConfig->model_name, $inputTokens, 0);
            $costOutput = PricingRegistry::calculateCost($modelConfig->model_name, 0, $outputTokens);
            AiUsageLog::record(
                'analyze', $modelConfig->credential_id, $modelConfig->model_name,
                $inputTokens, $outputTokens, $costInput, $costOutput,
                mb_substr($data['content'], 0, 200),
                AiChat::class, $chat->id
            );
        }

        // Generate title on first message
        if ($isFirstMessage && !$chat->title_is_manual && $fullContent) {
            $this->generateTitle($chat, $data['content'], $modelConfig);
        }

        return response()->json([
            'ok'         => true,
            'message_id' => $assistantMessage->id,
            'message'    => [
                'id'         => $assistantMessage->id,
                'role'       => 'assistant',
                'content'    => $fullContent,
                'created_at' => ($assistantMessage->created_at ?? now())->toIso8601String(),
            ],
        ]);
    }

    /**
     * Stop AI generation for a chat.
     */
    public function stop(AiChat $chat): JsonResponse
    {
        abort_unless($chat->canAccess(auth()->id()), 403);
        Cache::put("analyse.stop.{$chat->id}", true, 60);
        return response()->json(['ok' => true]);
    }

    /**
     * Branch from a specific message.
     */
    public function branch(Request $request, AiChat $chat): JsonResponse
    {
        abort_unless($chat->canAccess(auth()->id()), 403);

        $data = $request->validate([
            'message_id' => ['required', 'integer', 'exists:ai_chat_messages,id'],
        ]);

        $sourceMessage = AiChatMessage::findOrFail($data['message_id']);
        abort_unless($sourceMessage->chat_id === $chat->id, 422);

        $branchedChat = null;

        DB::transaction(function () use ($chat, $sourceMessage, &$branchedChat) {
            // Create new chat
            $branchedChat = AiChat::create([
                'user_id'           => auth()->id(),
                'title'             => ($chat->title ? 'Branch: ' . $chat->title : null),
                'source_chat_id'    => $chat->id,
                'source_message_id' => $sourceMessage->id,
                'last_message_at'   => now(),
            ]);

            // Copy all messages up to and including source message
            $messages = AiChatMessage::where('chat_id', $chat->id)
                ->where('id', '<=', $sourceMessage->id)
                ->orderBy('created_at')
                ->get();

            foreach ($messages as $msg) {
                AiChatMessage::create([
                    'chat_id'    => $branchedChat->id,
                    'role'       => $msg->role,
                    'content'    => $msg->content,
                    'meta_json'  => $msg->meta_json,
                    'created_at' => $msg->created_at,
                ]);
            }

            // Add system event message
            AiChatMessage::create([
                'chat_id' => $branchedChat->id,
                'role'    => 'system_event',
                'content' => 'Branched from "' . ($chat->title ?? 'conversation') . '"',
            ]);
        });

        return response()->json(['chat' => $branchedChat->toSidebarArray()]);
    }

    /**
     * Share a chat with another user.
     */
    public function share(Request $request, AiChat $chat): JsonResponse
    {
        abort_unless($chat->isOwnedBy(auth()->id()), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'different:' . auth()->id()],
        ]);

        AiChatParticipant::firstOrCreate(
            ['chat_id' => $chat->id, 'user_id' => $data['user_id']],
            ['added_by' => auth()->id()]
        );

        $chat->update(['is_shared' => true]);

        $participants = $this->formatParticipants($chat);
        $this->safeBroadcast(new ParticipantUpdated($chat->id, $participants));

        return response()->json(['participants' => $participants]);
    }

    /**
     * Remove a participant from a shared chat.
     */
    public function removeParticipant(AiChat $chat, User $user): JsonResponse
    {
        abort_unless($chat->isOwnedBy(auth()->id()), 403);

        AiChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->delete();

        $remainingParticipants = AiChatParticipant::where('chat_id', $chat->id)->count();
        if ($remainingParticipants === 0) {
            $chat->update(['is_shared' => false]);
        }

        $participants = $this->formatParticipants($chat->fresh());
        $this->safeBroadcast(new ParticipantUpdated($chat->id, $participants));

        return response()->json(['participants' => $participants]);
    }

    /**
     * Participant leaves / hides the chat from their sidebar.
     */
    public function leave(AiChat $chat): JsonResponse
    {
        abort_if($chat->isOwnedBy(auth()->id()), 422, 'Owner cannot leave their own conversation.');

        AiChatParticipant::where('chat_id', $chat->id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * List private chats (cursor paginated, for sidebar infinite scroll).
     */
    public function list(Request $request): JsonResponse
    {
        $chats = AiChat::where('user_id', auth()->id())
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->cursorPaginate(30);

        return response()->json([
            'chats'      => array_map(fn ($c) => $c->toSidebarArray(), $chats->items()),
            'nextCursor' => $chats->nextCursor()?->encode(),
        ]);
    }

    /**
     * List chats shared with the current user.
     */
    public function shared(): JsonResponse
    {
        $userId = auth()->id();
        $shared = AiChat::whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with('owner:id,name')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => array_merge($c->toSidebarArray(), [
                'owner_name' => $c->owner->name ?? 'Unknown',
            ]));

        return response()->json(['shared' => $shared]);
    }

    /**
     * Full-text search across chats and messages.
     */
    public function search(Request $request): JsonResponse
    {
        $q      = trim($request->input('q', ''));
        $userId = auth()->id();

        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        if (DB::getDriverName() !== 'pgsql') {
            // SQLite fallback (for tests)
            $results = AiChat::where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
                })
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%");
                })
                ->limit(20)
                ->get()
                ->map(fn ($c) => [
                    'chat_id'         => $c->id,
                    'title'           => $c->title ?? 'New Conversation',
                    'snippet'         => '',
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                    'is_owned'        => $c->user_id === $userId,
                ]);

            return response()->json(['results' => $results]);
        }

        // PostgreSQL FTS
        $results = DB::select("
            SELECT
                c.id AS chat_id,
                COALESCE(c.title, 'New Conversation') AS title,
                ts_headline('english', m.content, plainto_tsquery('english', ?), 'MaxWords=10, MinWords=5') AS snippet,
                c.last_message_at,
                c.user_id = ? AS is_owned
            FROM ai_chats c
            LEFT JOIN ai_chat_messages m ON m.chat_id = c.id
                AND m.content_tsv @@ plainto_tsquery('english', ?)
            WHERE
                (c.user_id = ? OR EXISTS (
                    SELECT 1 FROM ai_chat_participants p WHERE p.chat_id = c.id AND p.user_id = ?
                ))
                AND (
                    c.title_tsv @@ plainto_tsquery('english', ?)
                    OR m.id IS NOT NULL
                )
            ORDER BY c.last_message_at DESC
            LIMIT 20
        ", [$q, $userId, $q, $userId, $userId, $q]);

        return response()->json(['results' => $results]);
    }

    /**
     * Broadcast safely — swallow exceptions when Reverb is not running.
     */
    private function safeBroadcast($event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            // Reverb not running — silently ignore
        }
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function buildMessageHistory(AiChat $chat): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        $messages = $chat->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        return array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );
    }

    private function buildSystemPrompt(): string
    {
        $appName = config('app.name', 'Contact Monitor');
        $today   = now()->format('Y-m-d');
        $dayName = now()->format('l');

        return <<<PROMPT
You are an AI assistant for {$appName}, a multi-channel contact hub that centralizes company communications with clients across email, Gmail, Slack, Discord, tickets, and other integrations.

Today's date is {$today} ({$dayName}).

You have access to tools that let you search and query CRM data including companies, people, conversations, activities, and notes. You also have tools to create and modify records.

When the user asks about data in the system, ALWAYS use the appropriate tool to look it up. Never guess or make up data. Available data operations:
- Search/list companies, people, conversations, activities, notes
- Get detailed information about a specific company or person
- Create/update companies and people
- Add domains, accounts, identities
- Create notes, archive conversations
- Merge companies or people

When presenting data:
- Format results clearly using markdown tables or bullet points
- Include relevant IDs so the user can reference specific records
- When listing items, show the most useful fields (name, domain, email, etc.)
- If results are paginated, mention the total count and current page

Respond in the same language the user writes in. Be concise and direct.
PROMPT;
    }

    private function formatParticipants(AiChat $chat): array
    {
        return $chat->participants()
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($p) => [
                'user_id'  => $p->user_id,
                'name'     => $p->user->name,
                'email'    => $p->user->email,
                'added_at' => $p->added_at?->toIso8601String(),
            ])
            ->toArray();
    }

    private function generateTitle(AiChat $chat, string $firstUserMessage, \App\Models\AiModelConfig $modelConfig): void
    {
        try {
            $provider = AiProviderFactory::make($modelConfig->credential);
            $result   = $provider->complete($modelConfig->model_name, [
                ['role' => 'system', 'content' => 'You generate concise conversation titles. Respond with only the title, max 6 words, no quotes, no punctuation at end.'],
                ['role' => 'user',   'content' => mb_substr($firstUserMessage, 0, 500)],
            ]);

            $title = trim($result['content']);
            if ($title) {
                $chat->update(['title' => $title, 'title_is_manual' => false]);
                $this->safeBroadcast(new ChatTitleGenerated($chat->id, $title));
            }
        } catch (\Throwable) {
            // Title generation is non-critical, swallow errors
        }
    }
}
