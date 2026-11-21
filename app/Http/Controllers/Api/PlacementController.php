<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use App\Services\ProgressService;
use App\Support\PlacementBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    private const WEIGHTS = ['reading' => 0.2, 'grammar' => 0.25, 'listening' => 0.2, 'writing' => 0.2, 'speaking' => 0.15];

    private const LEVEL_WEIGHT = ['A1' => 1, 'A2' => 1.5, 'B1' => 2, 'B2' => 2.5, 'C1' => 3, 'C2' => 3.5];

    public function questions(): JsonResponse
    {
        return response()->json(['sections' => PlacementBank::publicSections()]);
    }

    public function submit(Request $request, OpenAIService $ai, ProgressService $progress): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'writing' => ['nullable', 'string', 'max:3000'],
            'speaking' => ['nullable', 'string', 'max:3000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
        ]);

        $answers = $data['answers'];
        $scores = [];
        $feedback = [];

        foreach (PlacementBank::sections() as $section) {
            $key = $section['key'];

            if (isset($section['questions'])) {
                $total = 0;
                $earned = 0;
                foreach ($section['questions'] as $q) {
                    $w = self::LEVEL_WEIGHT[$q['level'] ?? 'B1'];
                    $total += $w;
                    if (array_key_exists($q['id'], $answers) && (int) $answers[$q['id']] === $q['answer']) {
                        $earned += $w;
                    }
                }
                $scores[$key] = (int) round($earned / $total * 100);
            } else {
                $text = trim((string) ($data[$key] ?? ''));
                $eval = $text === ''
                    ? ['score' => 0, 'feedback' => 'Section non complétée.']
                    : $ai->evaluateProduction($key === 'writing' ? 'written' : 'spoken (speech transcript)', $section['prompt'], $text);
                $scores[$key] = $eval['score'];
                $feedback[$key] = $eval['feedback'];
            }
        }

        $overall = (int) round(collect(self::WEIGHTS)->map(fn ($w, $k) => $scores[$k] * $w)->sum());
        $level = PlacementBank::levelFromScore($overall);

        $user = $request->user();
        $result = $user->placementResults()->create([
            'level' => $level,
            'score' => $overall,
            'scores' => $scores + ['feedback' => $feedback],
        ]);

        $user->update(['level' => $level]);
        $progress->record($user, 'test', (int) ($data['duration_seconds'] ?? 600), 100);

        return response()->json([
            'id' => $result->id,
            'level' => $level,
            'score' => $overall,
            'scores' => $scores,
            'feedback' => $feedback,
            'user' => AuthController::present($user->fresh()),
        ]);
    }
}
