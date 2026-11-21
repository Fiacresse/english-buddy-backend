<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Toutes les requêtes OpenAI passent par ici : la clé API ne quitte jamais le serveur.
 * Sans clé valide, le service bascule en « mode démo » pour que l'app reste utilisable.
 */
class OpenAIService
{
    private const BASE = 'https://api.openai.com/v1';

    /** Message lisible décrivant le dernier échec d'appel à OpenAI (null si OK). */
    public ?string $lastError = null;

    public function isConfigured(): bool
    {
        $key = (string) config('services.openai.key');

        return $key !== '' && ! Str::startsWith($key, 'sk-ta-cle');
    }

    /**
     * Réponse du tuteur + analyse des erreurs du dernier message de l'élève.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, corrections: array, better_version: ?string, new_words: array}
     */
    public function tutorReply(array $history, string $level, ?string $scenario): array
    {
        if (! $this->isConfigured()) {
            return $this->demoTutorReply($history, $scenario);
        }

        $system = $this->tutorPrompt($level, $scenario)."\n\n".<<<'TXT'
Respond ONLY with a JSON object of this exact shape:
{
  "reply": "your conversational answer (2-4 sentences, end with a question to keep the conversation going)",
  "corrections": [{"original": "wrong fragment", "corrected": "fixed fragment", "explanation": "short explanation in French", "category": "grammar|vocabulary|spelling|tense|preposition|article|word_order|punctuation"}],
  "better_version": "a more natural rewrite of the learner's last message, or null if it was already natural",
  "new_words": [{"word": "useful word you used in your reply", "meaning": "short French translation", "example": "short example sentence"}]
}
Only correct real mistakes in the learner's LAST message. "new_words": 0 to 2 items, adapted to the level.
TXT;

        $data = $this->chatJson($system, $history, 600);

        return [
            'reply' => (string) ($data['reply'] ?? "Sorry, could you say that again?"),
            'corrections' => array_values(array_filter((array) ($data['corrections'] ?? []), 'is_array')),
            'better_version' => $data['better_version'] ?? null,
            'new_words' => array_values(array_filter((array) ($data['new_words'] ?? []), 'is_array')),
        ];
    }

    /**
     * Évalue une production écrite ou orale (transcription) du test de niveau.
     *
     * @return array{score: int, feedback: string}
     */
    public function evaluateProduction(string $kind, string $prompt, string $text): array
    {
        if (! $this->isConfigured()) {
            return $this->heuristicProduction($text);
        }

        $system = <<<TXT
You are a certified CEFR examiner. Evaluate a learner's {$kind} production in English.
Task given to the learner: "{$prompt}"
Score from 0 to 100 where 0-15=A1, 16-35=A2, 36-55=B1, 56-75=B2, 76-90=C1, 91-100=C2.
Consider range, accuracy, coherence and task completion.
Respond ONLY with JSON: {"score": <int>, "feedback": "2 sentences of feedback in French"}
TXT;

        $data = $this->chatJson($system, [['role' => 'user', 'content' => $text]], 200);

        if (! isset($data['score'])) {
            return $this->heuristicProduction($text);
        }

        return [
            'score' => max(0, min(100, (int) $data['score'])),
            'feedback' => (string) ($data['feedback'] ?? ''),
        ];
    }

    /**
     * Crée un jeton éphémère pour une session vocale Realtime (WebRTC côté navigateur).
     */
    public function createRealtimeSecret(string $level, ?string $scenario): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(15)
                ->post(self::BASE.'/realtime/client_secrets', [
                    'expires_after' => ['anchor' => 'created_at', 'seconds' => 600],
                    'session' => [
                        'type' => 'realtime',
                        'model' => config('services.openai.realtime_model'),
                        'instructions' => $this->tutorPrompt($level, $scenario)."\n".$this->voicePacing($level),
                        'audio' => [
                            'input' => [
                                'transcription' => ['model' => 'gpt-4o-mini-transcribe', 'language' => 'en'],
                                'turn_detection' => ['type' => 'server_vad', 'silence_duration_ms' => 700],
                            ],
                            'output' => ['voice' => config('services.openai.voice')],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI realtime secret failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return [
                'value' => $response->json('value'),
                'expires_at' => $response->json('expires_at'),
                'model' => config('services.openai.realtime_model'),
            ];
        } catch (Throwable $e) {
            Log::warning('OpenAI realtime secret error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function tutorPrompt(string $level, ?string $scenario): string
    {
        $style = match ($level) {
            'A1' => 'Use very simple words and short present-tense sentences. Speak slowly. Max 10 words per sentence.',
            'A2' => 'Use simple everyday vocabulary and short sentences. Avoid idioms.',
            'B1' => 'Use clear standard English with some common phrasal verbs.',
            'B2' => 'Use natural English with idioms and varied structures.',
            'C1', 'C2' => 'Speak like a native: nuanced vocabulary, idioms, complex structures.',
            default => 'Use clear, simple English.',
        };

        $role = $scenario
            ? "We are doing a role-play. Scenario: {$scenario}. Stay in character and play the other person."
            : 'You are a friendly conversation partner chatting about everyday topics and the learner\'s interests.';

        return "You are Buddy, a warm and encouraging English tutor for a French-speaking learner at CEFR level {$level}. {$role} {$style} "
            .'Gently correct important mistakes. Never write long lectures.';
    }

    private function voicePacing(string $level): string
    {
        return in_array($level, ['A1', 'A2'], true)
            ? 'Speak slowly and articulate clearly. Keep each turn under 2 sentences. When the learner makes a mistake, repeat the correct sentence naturally ("Oh, you went to the park!").'
            : 'Speak at a natural pace. Keep each turn short (2-3 sentences). Briefly point out an important mistake, then continue the conversation.';
    }

    private function chatJson(string $system, array $history, int $maxTokens): array
    {
        $this->lastError = null;

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(40)
                ->retry(2, 500, throw: false)
                ->post(self::BASE.'/chat/completions', [
                    'model' => config('services.openai.model'),
                    'response_format' => ['type' => 'json_object'],
                    'max_completion_tokens' => $maxTokens,
                    'messages' => array_merge([['role' => 'system', 'content' => $system]], $history),
                ]);

            if ($response->failed()) {
                Log::warning('OpenAI chat failed', ['status' => $response->status(), 'body' => $response->body()]);
                $this->lastError = match (true) {
                    $response->status() === 401 => 'Clé OpenAI invalide. Vérifie OPENAI_API_KEY dans le fichier .env du serveur.',
                    $response->status() === 429 && str_contains($response->body(), 'credits') || str_contains($response->body(), 'insufficient_quota')
                        => 'Le compte OpenAI n’a plus de crédit. Ajoute du crédit sur platform.openai.com (Billing).',
                    $response->status() === 429 => 'L’IA est très sollicitée, réessaie dans quelques secondes.',
                    default => 'L’IA est momentanément indisponible. Réessaie dans un instant.',
                };

                return [];
            }

            $data = json_decode((string) $response->json('choices.0.message.content'), true) ?: [];
            if (! $data) {
                $this->lastError = 'Réponse de l’IA illisible. Réessaie.';
            }

            return $data;
        } catch (Throwable $e) {
            Log::warning('OpenAI chat error', ['error' => $e->getMessage()]);
            $this->lastError = 'Impossible de joindre OpenAI depuis le serveur (réseau ou certificat SSL).';

            return [];
        }
    }

    /* ----------------------------- Mode démo ----------------------------- */

    private function demoTutorReply(array $history, ?string $scenario): array
    {
        $last = (string) (collect($history)->where('role', 'user')->last()['content'] ?? '');
        $corrections = [];

        $rules = [
            ['/\bi\b/', 'I', 'Le pronom « I » prend toujours une majuscule.', 'spelling'],
            ['/\bhe have\b/i', 'he has', 'À la 3e personne du singulier, « have » devient « has ».', 'grammar'],
            ['/\bshe have\b/i', 'she has', 'À la 3e personne du singulier, « have » devient « has ».', 'grammar'],
            ['/\bi am agree\b/i', 'I agree', '« Agree » est un verbe : pas besoin de « am ».', 'grammar'],
            ['/\bdepend of\b/i', 'depend on', 'On dit « depend on », pas « depend of ».', 'preposition'],
            ['/\bsince (\d+) (years|days|months)\b/i', 'for $1 $2', 'Pour une durée, on utilise « for ».', 'preposition'],
            ['/\bi have (\d+) years\b/i', 'I am $1 years old', 'L\'âge s\'exprime avec « to be ».', 'grammar'],
            ['/\byesterday i go\b/i', 'yesterday I went', 'Action passée : utilise le prétérit « went ».', 'tense'],
            ['/\bpeoples\b/i', 'people', '« People » est déjà un pluriel.', 'vocabulary'],
            ['/\binformations\b/i', 'information', '« Information » est indénombrable.', 'vocabulary'],
        ];

        $better = $last;
        foreach ($rules as [$pattern, $replacement, $explanation, $category]) {
            if (preg_match($pattern, $better, $m)) {
                $fixed = preg_replace($pattern, $replacement, $m[0]);
                $corrections[] = compact('explanation', 'category') + ['original' => $m[0], 'corrected' => $fixed];
                $better = preg_replace($pattern, $replacement, $better);
            }
        }

        $replies = $scenario
            ? [
                "Great! Let's keep going with our role-play. What would you like to do next?",
                "Of course. Could you tell me a little more about what you need?",
                "Sounds good. Is there anything else I can help you with today?",
            ]
            : [
                "That's really interesting! Can you tell me more about it?",
                "I see what you mean. How did that make you feel?",
                "Nice! What do you usually do on weekends?",
                "Good point. Why do you think that is?",
            ];

        $words = [
            ['word' => 'actually', 'meaning' => 'en fait', 'example' => 'Actually, I prefer tea.'],
            ['word' => 'to look forward to', 'meaning' => 'avoir hâte de', 'example' => 'I look forward to meeting you.'],
            ['word' => 'awesome', 'meaning' => 'génial', 'example' => 'That movie was awesome!'],
            ['word' => 'to figure out', 'meaning' => 'comprendre, trouver', 'example' => 'I need to figure out the answer.'],
        ];

        return [
            'reply' => '🧪 (Mode démo) '.$replies[count($history) % count($replies)],
            'corrections' => $corrections,
            'better_version' => $corrections ? $better : null,
            'new_words' => [$words[count($history) % count($words)]],
        ];
    }

    private function heuristicProduction(string $text): array
    {
        $words = str_word_count(strtolower($text), 1);
        $count = count($words);
        if ($count === 0) {
            return ['score' => 0, 'feedback' => 'Aucune réponse détectée.'];
        }

        $diversity = count(array_unique($words)) / $count;
        $longWords = count(array_filter($words, fn ($w) => strlen($w) >= 7)) / $count;
        $connectors = preg_match_all('/\b(because|however|although|therefore|moreover|while|whereas|since|despite|unless)\b/i', $text);

        $score = min(100, (int) round(min($count, 120) / 120 * 45 + $diversity * 25 + $longWords * 60 + min($connectors, 5) * 4));

        return [
            'score' => $score,
            'feedback' => 'Évaluation estimée (mode démo) : longueur, richesse du vocabulaire et connecteurs logiques.',
        ];
    }
}
