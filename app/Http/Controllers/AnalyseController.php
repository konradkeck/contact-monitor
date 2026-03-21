<?php
namespace App\Http\Controllers;

use App\Models\AiChat;
use App\Models\AiChatParticipant;
use App\Models\AiProject;
use App\Models\AiModelConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyseController extends Controller
{
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        Inertia::setRootView('analyse');

        if (!$this->analyseEnabled()) {
            return Inertia::render('Analyse', $this->sharedProps($request, null));
        }

        // Redirect to last active chat if exists
        $lastChat = AiChat::where('user_id', auth()->id())
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->first();

        if ($lastChat) {
            return redirect()->route('analyse.chat.show', $lastChat->id);
        }

        return Inertia::render('Analyse', $this->sharedProps($request, null));
    }

    public function show(Request $request, AiChat $chat): Response
    {
        Inertia::setRootView('analyse');

        abort_unless($chat->canAccess(auth()->id()), 403);

        $messages = $chat->messages()
            ->select(['id', 'role', 'content', 'meta_json', 'created_at'])
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'content'    => $m->content,
                'meta'       => $m->meta_json,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        $participants = $chat->participants()
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($p) => [
                'user_id'  => $p->user_id,
                'name'     => $p->user->name,
                'email'    => $p->user->email,
                'added_at' => $p->added_at?->toIso8601String(),
            ]);

        $chatData = [
            'id'              => $chat->id,
            'title'           => $chat->title ?? 'New Conversation',
            'title_is_manual' => $chat->title_is_manual,
            'is_shared'       => $chat->is_shared,
            'is_archived'     => $chat->is_archived,
            'is_owner'        => $chat->isOwnedBy(auth()->id()),
            'project_id'      => $chat->project_id,
            'source_chat_id'  => $chat->source_chat_id,
            'last_message_at' => $chat->last_message_at?->toIso8601String(),
            'messages'        => $messages,
            'participants'    => $participants,
        ];

        return Inertia::render('Chat', array_merge(
            $this->sharedProps($request, $chat->id),
            ['chat' => $chatData]
        ));
    }

    public function project(Request $request, AiProject $project): Response
    {
        Inertia::setRootView('analyse');

        abort_unless($project->user_id === auth()->id(), 403);

        // Get owned chats in this project
        $ownedChats = AiChat::where('project_id', $project->id)
            ->where('user_id', auth()->id())
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => array_merge($c->toSidebarArray(), ['pinned' => false]));

        // Get pinned shared chats
        $pinnedChats = $project->pins()
            ->where('ai_chat_project_pins.user_id', auth()->id())
            ->with('chat')
            ->get()
            ->map(fn ($pin) => array_merge($pin->chat->toSidebarArray(), ['pinned' => true]));

        return Inertia::render('Project', array_merge(
            $this->sharedProps($request, null),
            [
                'project' => [
                    'id'    => $project->id,
                    'name'  => $project->name,
                    'chats' => $ownedChats->concat($pinnedChats)->sortByDesc('last_message_at')->values(),
                ],
            ]
        ));
    }

    private function sharedProps(Request $request, ?int $activeChatId): array
    {
        $userId = auth()->id();

        // Sidebar: owned private chats (not archived), cursor paginated
        $chats = AiChat::where('user_id', $userId)
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->cursorPaginate(30);

        // Shared with me
        $shared = AiChat::whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->with('owner:id,name')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn ($c) => array_merge($c->toSidebarArray(), [
                'owner_name' => $c->owner->name ?? 'Unknown',
            ]));

        // Projects
        $projects = AiProject::where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]);

        // Available users for sharing
        $users = \App\Models\User::where('id', '!=', $userId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return [
            'analyseEnabled' => $this->analyseEnabled(),
            'activeChatId'   => $activeChatId,
            'auth' => [
                'user' => [
                    'id'    => $userId,
                    'name'  => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ],
            'sidebar' => [
                'chats'      => $chats->items(),
                'nextCursor' => $chats->nextCursor()?->encode(),
                'shared'     => $shared,
                'projects'   => $projects,
            ],
            'users' => $users,
        ];
    }

    private function analyseEnabled(): bool
    {
        return \App\Models\AiCredential::exists()
            && \App\Models\AiModelConfig::where('action_type', 'analyze')->exists();
    }
}
