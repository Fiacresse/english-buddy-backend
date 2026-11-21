<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => '']); // mode démo, aucun appel réseau
    }

    public function test_full_learning_flow(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonPath('user.level', null);

        $headers = ['Authorization' => 'Bearer '.$register->json('token')];

        $questions = $this->getJson('/api/placement', $headers)->assertOk();
        $this->assertArrayNotHasKey('answer', $questions->json('sections.0.questions.0'));

        $result = $this->postJson('/api/placement', [
            'answers' => ['r1' => 1, 'r2' => 1, 'r3' => 2, 'g1' => 1, 'g2' => 2, 'g3' => 3, 'g5' => 2, 'l1' => 1, 'l2' => 0],
            'writing' => 'My ideal weekend would be in the mountains with my friends because I love hiking and fresh air. However, I also enjoy reading.',
            'speaking' => 'I love the beach near my hometown because it is calm.',
            'duration_seconds' => 900,
        ], $headers)->assertOk();
        $this->assertContains($result->json('level'), ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']);

        $conversation = $this->postJson('/api/conversations', [], $headers)->assertCreated();
        $id = $conversation->json('id');

        $reply = $this->postJson("/api/conversations/{$id}/messages", [
            'content' => 'yesterday i go to the cinema with my friends',
        ], $headers)->assertOk();
        $this->assertNotEmpty($reply->json('user_message.feedback.corrections'));
        $this->assertTrue($reply->json('demo'));

        $this->postJson('/api/lessons/present-simple/complete', ['score' => 80], $headers)
            ->assertOk()->assertJsonPath('xp_gained', 36);

        $voice = $this->postJson('/api/voice/session', [], $headers)->assertOk();
        $this->assertSame('browser', $voice->json('mode'));

        $this->getJson('/api/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('user.streak', 1)
            ->assertJsonPath('conversations_count', 1)
            ->assertJsonPath('words_count', 1);
    }

    public function test_users_cannot_read_others_conversations(): void
    {
        $a = $this->postJson('/api/register', ['name' => 'A', 'email' => 'a@x.io', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $b = $this->postJson('/api/register', ['name' => 'B', 'email' => 'b@x.io', 'password' => 'secret123', 'password_confirmation' => 'secret123']);

        $id = $this->postJson('/api/conversations', [], ['Authorization' => 'Bearer '.$a->json('token')])->json('id');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/conversations/{$id}", ['Authorization' => 'Bearer '.$b->json('token')])->assertNotFound();
    }
}
