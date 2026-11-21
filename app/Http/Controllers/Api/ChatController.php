<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIService;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->conversations()
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'mode' => $c->mode,
                'scenario' => $c->scenario,
                'messages_count' => $c->messages_count,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]);

        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scenario' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:120'],
            'opening' => ['nullable', 'string', 'max:500'],
            'mode' => ['nullable', 'in:text,voice'],
        ]);

        $conversation = $request->user()->conversations()->create([
            'title' => $data['title'] ?? 'Nouvelle conversation',
            'scenario' => $data['scenario'] ?? null,
            'mode' => $data['mode'] ?? 'text',
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $data['opening'] ?? "Hi {$request->user()->name}! I'm Buddy 👋 What would you like to talk about today?",
        ]);

        return response()->json($this->payload($conversation), 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        return response()->json($this->payload($conversation));
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function send(Request $request, Conversation $conversation, OpenAIService $ai, ProgressService $progress): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $user = $request->user();

        $data = $request->validate(['content' => ['required', 'string', 'max:1000']]);

        // Contrôle des coûts : plafond quotidien de messages par utilisateur.
        $limit = (int) config('services.openai.daily_message_limit');
        $today = Message::whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->where('role', 'user')->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= $limit) {
            return response()->json(['message' => "Limite quotidienne de {$limit} messages atteinte. Reviens demain ! 🌙"], 429);
        }

        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $data['content']]);

        $history = $conversation->messages()->latest('id')->limit(12)->get()->reverse()
            ->map(fn (Message $m) => ['role' => $m->role, 'content' => $m->content])->values()->all();

        $result = $ai->tutorReply($history, $user->level ?? 'A2', $conversation->scenario);

        // Échec de l'IA : on n'enregistre rien et on explique pourquoi.
        if ($ai->lastError) {
            $userMessage->delete();

            return response()->json(['message' => $ai->lastError], 503);
        }

        $userMessage->update(['feedback' => [
            'corrections' => $result['corrections'],
            'better_version' => $result['better_version'],
        ]]);

        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'feedback' => ['new_words' => $result['new_words']],
        ]);

        foreach ($result['corrections'] as $c) {
            $user->mistakes()->create([
                'category' => Str::limit((string) ($c['category'] ?? 'grammar'), 38, ''),
                'original' => (string) ($c['original'] ?? ''),
                'corrected' => (string) ($c['corrected'] ?? ''),
                'explanation' => $c['explanation'] ?? null,
            ]);
        }

        foreach ($result['new_words'] as $w) {
            if (! empty($w['word'])) {
                $user->learnedWords()->firstOrCreate(
                    ['word' => Str::limit(mb_strtolower(trim($w['word'])), 78, '')],
                    ['meaning' => $w['meaning'] ?? null, 'example' => $w['example'] ?? null],
                );
            }
        }

        if ($conversation->title === 'Nouvelle conversation') {
            $conversation->title = Str::limit($data['content'], 48);
        }
        $conversation->touch();

        $progress->record($user, 'chat', 45, 10);

        return response()->json([
            'user_message' => $this->presentMessage($userMessage->fresh()),
            'assistant_message' => $this->presentMessage($assistant),
            'xp' => $user->xp,
            'demo' => ! $ai->isConfigured(),
        ]);
    }

    private function payload(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'mode' => $conversation->mode,
            'scenario' => $conversation->scenario,
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn ($m) => $this->presentMessage($m)),
        ];
    }

    private function presentMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'feedback' => $m->feedback,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }

    private function authorizeOwner(Request $request, Conversation $conversation): void
    {
        abort_if($conversation->user_id !== $request->user()->id, 404);
    }
}
