<?php

namespace App\Support;

/**
 * Banque de questions du test de niveau. Les bonnes réponses restent côté serveur.
 */
class PlacementBank
{
    public static function sections(): array
    {
        return [
            [
                'key' => 'reading',
                'title' => 'Compréhension écrite',
                'passage' => "Maya moved to Lisbon two years ago to work for a small tech start-up. At first, she found it hard to make friends because most of her colleagues were much older and already had families. However, after she joined a local running club, everything changed. Every Saturday morning they run along the river, and afterwards they have breakfast together in a café near the old market. Maya says that learning Portuguese has been the biggest challenge, but her new friends are patient and always correct her mistakes with a smile. Although she sometimes misses her hometown, she has no plans to leave.",
                'questions' => [
                    ['id' => 'r1', 'text' => 'Why did Maya move to Lisbon?', 'options' => ['To study Portuguese', 'For a job', 'To join a running club', 'To live with her family'], 'answer' => 1],
                    ['id' => 'r2', 'text' => 'Why was it difficult to make friends at first?', 'options' => ['She did not speak English', 'Her colleagues were older with families', 'She worked at night', 'She lived far from the city'], 'answer' => 1],
                    ['id' => 'r3', 'text' => 'What does the club do after running?', 'options' => ['They go to the market', 'They swim in the river', 'They eat breakfast together', 'They have a Portuguese lesson'], 'answer' => 2],
                    ['id' => 'r4', 'text' => 'Which statement is TRUE?', 'options' => ['Maya wants to go back home soon', 'Maya finds Portuguese easy', 'Maya intends to stay in Lisbon', 'Maya never thinks about her hometown'], 'answer' => 2],
                ],
            ],
            [
                'key' => 'grammar',
                'title' => 'Grammaire & vocabulaire',
                'questions' => [
                    ['id' => 'g1', 'level' => 'A1', 'text' => 'She ___ a teacher.', 'options' => ['am', 'is', 'are', 'be'], 'answer' => 1],
                    ['id' => 'g2', 'level' => 'A1', 'text' => 'I ___ coffee every morning.', 'options' => ['drinks', 'drinking', 'drink', 'am drink'], 'answer' => 2],
                    ['id' => 'g3', 'level' => 'A2', 'text' => 'Last weekend we ___ to the beach.', 'options' => ['go', 'gone', 'were go', 'went'], 'answer' => 3],
                    ['id' => 'g4', 'level' => 'A2', 'text' => 'This book is ___ than the film.', 'options' => ['more interesting', 'most interesting', 'interestinger', 'more interested'], 'answer' => 0],
                    ['id' => 'g5', 'level' => 'B1', 'text' => 'I ___ here since 2019.', 'options' => ['live', 'am living', 'have lived', 'lived'], 'answer' => 2],
                    ['id' => 'g6', 'level' => 'B1', 'text' => 'If it rains tomorrow, we ___ at home.', 'options' => ['stay', 'will stay', 'would stay', 'stayed'], 'answer' => 1],
                    ['id' => 'g7', 'level' => 'B2', 'text' => 'If I ___ about the meeting, I would have come.', 'options' => ['knew', 'had known', 'have known', 'would know'], 'answer' => 1],
                    ['id' => 'g8', 'level' => 'B2', 'text' => 'Could you ___ the meeting until Friday? (= reporter)', 'options' => ['put off', 'put up', 'put out', 'put on'], 'answer' => 0],
                    ['id' => 'g9', 'level' => 'C1', 'text' => 'Hardly ___ the house when it started to rain.', 'options' => ['I had left', 'had I left', 'I left', 'did I leave'], 'answer' => 1],
                    ['id' => 'g10', 'level' => 'C1', 'text' => 'Her explanation was so ___ that nobody could follow it.', 'options' => ['convoluted', 'concise', 'lucid', 'candid'], 'answer' => 0],
                    ['id' => 'g11', 'level' => 'C2', 'text' => 'The minister\'s remarks were seen as a thinly ___ attack on his rival.', 'options' => ['hidden', 'veiled', 'covered', 'masked'], 'answer' => 1],
                    ['id' => 'g12', 'level' => 'C2', 'text' => 'Not until the results were published ___ the scale of the problem.', 'options' => ['we realised', 'did we realise', 'we did realise', 'had we realise'], 'answer' => 1],
                ],
            ],
            [
                'key' => 'listening',
                'title' => 'Compréhension orale',
                'questions' => [
                    ['id' => 'l1', 'audio' => 'Hi, my name is Tom. I am from Canada and I have two brothers.', 'text' => 'Where is Tom from?', 'options' => ['Australia', 'Canada', 'England', 'Ireland'], 'answer' => 1],
                    ['id' => 'l2', 'audio' => 'The train to Manchester will leave from platform seven at a quarter past ten.', 'text' => 'When does the train leave?', 'options' => ['10:15', '10:45', '7:10', '10:07'], 'answer' => 0],
                    ['id' => 'l3', 'audio' => 'I was going to take the job in Berlin, but in the end I decided the salary was not worth moving so far away from my family.', 'text' => 'What did the speaker decide?', 'options' => ['To move to Berlin', 'To ask for a higher salary', 'To refuse the job', 'To move with the family'], 'answer' => 2],
                    ['id' => 'l4', 'audio' => 'Frankly, the report raises more questions than it answers; the methodology is sound, but the conclusions seem somewhat premature.', 'text' => 'What is the speaker\'s opinion of the report?', 'options' => ['It is excellent overall', 'Its method is poor', 'Its conclusions come too early', 'It answers every question'], 'answer' => 2],
                ],
            ],
            [
                'key' => 'writing',
                'title' => 'Expression écrite',
                'prompt' => 'Describe your ideal weekend. What would you do, where, and with whom? Explain why. (50-120 words)',
            ],
            [
                'key' => 'speaking',
                'title' => 'Expression orale',
                'prompt' => 'Talk about a place you love. Where is it, what does it look like, and why is it special to you? (Speak for about 30-60 seconds)',
            ],
        ];
    }

    /** Version publique : sans les bonnes réponses. */
    public static function publicSections(): array
    {
        return array_map(function (array $section) {
            if (isset($section['questions'])) {
                $section['questions'] = array_map(function ($q) {
                    unset($q['answer']);

                    return $q;
                }, $section['questions']);
            }

            return $section;
        }, self::sections());
    }

    public static function levelFromScore(int $score): string
    {
        return match (true) {
            $score < 20 => 'A1',
            $score < 38 => 'A2',
            $score < 56 => 'B1',
            $score < 74 => 'B2',
            $score < 89 => 'C1',
            default => 'C2',
        };
    }
}
