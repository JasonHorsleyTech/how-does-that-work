<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;

// Billing page
test('authenticated user can view billing page', function () {
    $user = User::factory()->create(['credits' => 50]);

    $response = $this->actingAs($user)->get('/billing');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Billing')
        ->where('credits', 50)
    );
});

test('guest cannot view billing page', function () {
    $response = $this->get('/billing');

    $response->assertRedirect('/login');
});

// Start Game credit check
test('host cannot start game with zero credits', function () {
    $user = User::factory()->create(['credits' => 0]);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'submitting',
    ]);

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'is_host' => true,
    ]);

    $response = $this->actingAs($user)->post("/games/{$game->code}/start-game");

    $response->assertSessionHasErrors('game');
    $game->refresh();
    expect($game->status)->toBe('submitting');
});

test('host can start game with credits', function () {
    $user = User::factory()->create(['credits' => 10]);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'submitting',
    ]);

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'is_host' => true,
    ]);

    // Create 2 non-host players so the game can be started
    Player::factory()->count(2)->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $response = $this->actingAs($user)->post("/games/{$game->code}/start-game");

    $response->assertRedirect("/games/{$game->code}/play");
});

// Submit page passes hostCredits
test('submit page passes hostCredits to host', function () {
    $user = User::factory()->create(['credits' => 25]);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'submitting',
    ]);

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'is_host' => true,
    ]);

    $response = $this->actingAs($user)->get("/games/{$game->code}/submit");

    $response->assertInertia(fn ($page) => $page
        ->component('games/Submit')
        ->where('hostCredits', 25)
    );
});

test('submit page passes null hostCredits to guests', function () {
    $game = Game::factory()->create(['status' => 'submitting']);

    $player = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $response = $this->withSession(["player_id.{$game->code}" => $player->id])
        ->get("/games/{$game->code}/submit");

    $response->assertInertia(fn ($page) => $page
        ->component('games/Submit')
        ->where('hostCredits', null)
    );
});

// Stripe webhook — increments user credits on checkout.session.completed
test('stripe webhook increments user credits on checkout.session.completed', function () {
    config(['cashier.webhook.secret' => null]);
    $user = User::factory()->create(['credits' => 0]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'client_reference_id' => (string) $user->id,
                'payment_status' => 'paid',
            ],
        ],
    ]);

    $response = $this->postJson('/stripe/webhook', json_decode($payload, true), [
        'Content-Type' => 'application/json',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    expect($user->fresh()->credits)->toBe(100);
});

test('stripe webhook ignores unknown event types', function () {
    config(['cashier.webhook.secret' => null]);
    $user = User::factory()->create(['credits' => 50]);

    $response = $this->postJson('/stripe/webhook', [
        'type' => 'payment_intent.created',
        'data' => [
            'object' => [
                'client_reference_id' => (string) $user->id,
            ],
        ],
    ]);

    $response->assertOk();
    expect($user->fresh()->credits)->toBe(50);
});

test('stripe webhook handles missing client_reference_id gracefully', function () {
    config(['cashier.webhook.secret' => null]);
    $response = $this->postJson('/stripe/webhook', [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'payment_status' => 'paid',
            ],
        ],
    ]);

    $response->assertOk();
});

test('stripe webhook handles non-existent user gracefully', function () {
    config(['cashier.webhook.secret' => null]);
    $response = $this->postJson('/stripe/webhook', [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'client_reference_id' => '99999',
                'payment_status' => 'paid',
            ],
        ],
    ]);

    $response->assertOk();
});

test('stripe webhook returns 400 for invalid signature when secret is set', function () {
    config(['cashier.webhook.secret' => 'test_secret']);

    $response = $this->post('/stripe/webhook', [], [
        'Content-Type' => 'application/json',
        'Stripe-Signature' => 'invalid_signature',
    ]);

    $response->assertStatus(400);
});

// Credit deduction in GradeTurn job
test('grading a turn deducts one credit from host', function () {
    $user = User::factory()->create(['credits' => 10]);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'playing',
    ]);

    $player = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'score' => 0,
    ]);

    $topic = \App\Models\Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    $turn = \App\Models\Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
        'status' => 'grading',
        'transcript' => 'A test explanation.',
    ]);

    $fakeResponse = \OpenAI\Responses\Chat\CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'score' => 80,
                        'grade' => 'B',
                        'feedback' => 'Good explanation.',
                        'actual_explanation' => 'The accurate answer.',
                    ]),
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    \OpenAI\Laravel\Facades\OpenAI::fake([$fakeResponse]);

    (new \App\Jobs\GradeTurn($turn))->handle();

    expect($user->fresh()->credits)->toBe(9);
});

test('grading does not reduce credits below zero', function () {
    $user = User::factory()->create(['credits' => 0]);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'playing',
    ]);

    $player = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'score' => 0,
    ]);

    $topic = \App\Models\Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    $turn = \App\Models\Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
        'status' => 'grading',
        'transcript' => 'A test explanation.',
    ]);

    $fakeResponse = \OpenAI\Responses\Chat\CreateResponse::fake([
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'score' => 80,
                        'grade' => 'B',
                        'feedback' => 'Good explanation.',
                        'actual_explanation' => 'The accurate answer.',
                    ]),
                ],
                'finish_reason' => 'stop',
            ],
        ],
    ]);

    \OpenAI\Laravel\Facades\OpenAI::fake([$fakeResponse]);

    (new \App\Jobs\GradeTurn($turn))->handle();

    expect($user->fresh()->credits)->toBe(0);
});
