<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = $user->practiceSessions()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->get(['type', 'seconds', 'created_at']);

        $days = collect(range(29, 0))->map(function ($ago) use ($sessions) {
            $day = now()->subDays($ago)->toDateString();

            return [
                'date' => $day,
                'minutes' => (int) round($sessions->filter(fn ($s) => $s->created_at->toDateString() === $day)->sum('seconds') / 60),
            ];
        })->values();

        $byType = $sessions->groupBy('type')->map(fn ($g) => (int) round($g->sum('seconds') / 60));

        $latestTest = $user->placementResults()->latest()->first();
        $tests = $user->placementResults()->oldest()->get(['level', 'score', 'created_at']);

        $mistakeCategories = $user->mistakes()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')->orderByDesc('total')->limit(6)->get();

        $totalMinutes = (int) round($user->practiceSessions()->sum('seconds') / 60);

        return response()->json([
            'user' => AuthController::present($user),
            'today_minutes' => $days->last()['minutes'],
            'total_minutes' => $totalMinutes,
            'days' => $days,
            'by_type' => $byType,
            'conversations_count' => $user->conversations()->count(),
            'messages_count' => $user->conversations()->withCount(['messages' => fn ($q) => $q->where('role', 'user')])->get()->sum('messages_count'),
            'words_count' => $user->learnedWords()->count(),
            'lessons_done' => $user->lessonCompletions()->pluck('slug'),
            'skills' => $latestTest ? collect($latestTest->scores)->except('feedback') : null,
            'tests' => $tests,
            'mistake_categories' => $mistakeCategories,
            'recent_mistakes' => $user->mistakes()->latest()->limit(8)->get(['id', 'category', 'original', 'corrected', 'explanation', 'created_at']),
            'recent_words' => $user->learnedWords()->latest()->limit(24)->get(['id', 'word', 'meaning', 'example', 'created_at']),
            'recent_conversations' => $user->conversations()->latest('updated_at')->limit(5)->get(['id', 'title', 'mode', 'updated_at']),
        ]);
    }

    public function completeLesson(Request $request, string $slug, ProgressService $progress): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'type' => ['nullable', 'in:lesson,challenge,exercise'],
        ]);

        abort_unless(preg_match('/^[a-z0-9-]{2,80}$/', $slug), 422);

        $user = $request->user();
        $existing = $user->lessonCompletions()->where('slug', $slug)->first();

        $user->lessonCompletions()->updateOrCreate(['slug' => $slug], [
            'score' => max($data['score'], $existing?->score ?? 0),
        ]);

        // L'XP n'est gagné qu'une fois par leçon.
        $xp = $existing ? 0 : (int) round(20 + $data['score'] / 5);
        $progress->record($user, $data['type'] ?? 'lesson', $data['seconds'] ?? 180, $xp);

        return response()->json(['xp_gained' => $xp, 'user' => AuthController::present($user->fresh())]);
    }
}
