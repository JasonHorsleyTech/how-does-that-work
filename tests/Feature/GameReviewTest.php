<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;

function buildReviewGameFixture(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'complete',
        'current_round' => 1,
        'max_rounds' => 1,
        'state_updated_at' => now()->subHour(),
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
        'score' => 85,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
        'score' => 92,
    ]);

    $topic1 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a steam engine work?',
        'is_used' => true,
    ]);

    $topic2 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $guestPlayer->id,
        'text' => 'How does a battery store electricity?',
        'is_used' => true,
    ]);

    Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic1->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
        'transcript' => 'I think a steam engine uses steam to push pistons.',
        'grade' => 'A',
        'score' => 92,
        'feedback' => 'Excellent explanation.',
        'actual_explanation' => 'A steam engine converts thermal energy into mechanical work.',
    ]);

    Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $hostPlayer->id,
        'topic_id' => $topic2->id,
        'round_number' => 1,
        'turn_order' => 2,
        'status' => 'complete',
        'transcript' => 'Batteries use chemical reactions to store energy.',
        'grade' => 'B',
        'score' => 85,
        'feedback' => 'Good explanation.',
        'actual_explanation' => 'Batteries store energy via electrochemical reactions.',
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer];
}

test('host can view game review page', function () {
    [$host, $game] = buildReviewGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/review");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Review')
        ->has('game')
        ->has('players')
        ->has('allTurns', 2)
    );
});

test('review page includes transcript, feedback, and actual explanation for each turn', function () {
    [$host, $game] = buildReviewGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/review");

    $response->assertInertia(fn ($page) => $page
        ->component('games/Review')
        ->where('allTurns.0.transcript', 'I think a steam engine uses steam to push pistons.')
        ->where('allTurns.0.feedback', 'Excellent explanation.')
        ->where('allTurns.0.actual_explanation', 'A steam engine converts thermal energy into mechanical work.')
        ->where('allTurns.0.grade', 'A')
        ->where('allTurns.0.score', 92)
        ->where('allTurns.1.transcript', 'Batteries use chemical reactions to store energy.')
        ->where('allTurns.1.feedback', 'Good explanation.')
        ->where('allTurns.1.grade', 'B')
        ->where('allTurns.1.score', 85)
    );
});

test('review page shows players sorted by score descending', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildReviewGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/review");

    $response->assertInertia(fn ($page) => $page
        ->where('players.0.score', 92)
        ->where('players.0.name', $guestPlayer->name)
        ->where('players.1.score', 85)
        ->where('players.1.name', $hostPlayer->name)
    );
});

test('non-host authenticated user cannot access review page', function () {
    [$host, $game] = buildReviewGameFixture();

    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)->get("/games/{$game->code}/review");

    $response->assertStatus(403);
});

test('unauthenticated user cannot access review page', function () {
    [$host, $game] = buildReviewGameFixture();

    $response = $this->get("/games/{$game->code}/review");

    $response->assertRedirect('/login');
});

test('review page redirects to lobby for non-complete games', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
    ]);

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $response = $this->actingAs($host)->get("/games/{$game->code}/review");

    $response->assertRedirect("/games/{$game->code}/lobby");
});
