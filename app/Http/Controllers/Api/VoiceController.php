<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    /**
     * Fournit au navigateur un jeton éphémère (10 min) pour l'API Realtime.
     * La vraie clé API reste sur le serveur. Sans clé : mode « browser » (Web Speech API).
     */
    public function session(Request $request, OpenAIService $ai): JsonResponse
    {
        $data = $request->validate(['scenario' => ['nullable', 'string', 'max:200']]);
        $user = $request->user();

        $secret = $ai->createRealtimeSecret($user->level ?? 'A2', $data['scenario'] ?? null);

        return response()->json($secret
            ? ['mode' => 'realtime', 'client_secret' => $secret['value'], 'model' => $secret['model'], 'expires_at' => $secret['expires_at']]
            : ['mode' => 'browser']);
    }

    /** Enregistre la durée d'une session vocale et les échanges transcrits. */
    public function log(Request $request, ProgressService $progress): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:7200'],
            'transcript' => ['nullable', 'array', 'max:200'],
            'transcript.*.role' => ['required_with:transcript', 'in:user,assistant'],
            'transcript.*.content' => ['required_with:transcript', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if (! empty($data['transcript'])) {
            $conversation = $user->conversations()->create([
                'title' => '🎙️ Session vocale du '.now()->format('d/m H:i'),
                'mode' => 'voice',
            ]);
            $conversation->messages()->createMany($data['transcript']);
        }

        $progress->record($user, 'voice', $data['seconds'], (int) min(60, ceil($data['seconds'] / 20)));

        return response()->json(['ok' => true, 'user' => AuthController::present($user->fresh())]);
    }
}
