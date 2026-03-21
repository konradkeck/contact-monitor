<?php
namespace App\Http\Controllers;

use App\Models\AiChatProjectPin;
use App\Models\AiProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProjectController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $project = AiProject::create([
            'user_id' => auth()->id(),
            'name'    => $data['name'],
        ]);

        return response()->json(['project' => ['id' => $project->id, 'name' => $project->name]]);
    }

    public function update(Request $request, AiProject $project): JsonResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $project->update(['name' => $data['name']]);
        return response()->json(['project' => ['id' => $project->id, 'name' => $project->name]]);
    }

    public function destroy(AiProject $project): JsonResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);
        // Unassign owned chats
        $project->chats()->update(['project_id' => null]);
        // Remove pins
        AiChatProjectPin::where('project_id', $project->id)->delete();
        $project->delete();
        return response()->json(['ok' => true]);
    }

    public function pinChat(Request $request, AiProject $project): JsonResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);
        $data = $request->validate(['chat_id' => ['required', 'integer', 'exists:ai_chats,id']]);

        AiChatProjectPin::firstOrCreate([
            'user_id'    => auth()->id(),
            'chat_id'    => $data['chat_id'],
            'project_id' => $project->id,
        ]);

        return response()->json(['ok' => true]);
    }

    public function unpinChat(AiProject $project, int $chatId): JsonResponse
    {
        abort_unless($project->user_id === auth()->id(), 403);
        AiChatProjectPin::where('user_id', auth()->id())
            ->where('project_id', $project->id)
            ->where('chat_id', $chatId)
            ->delete();
        return response()->json(['ok' => true]);
    }
}
