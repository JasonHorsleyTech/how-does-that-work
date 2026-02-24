<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;

// Helper: completed game with multiple players and all turns done
function buildCompletedGameFixture(): array
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

    $turn1 = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic1->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
        'grade' => 'A',
        'score' => 92,
        'feedback' => 'Excellent explanation.',
        'actual_explanation' => 'The real answer.',
    ]);

    $turn2 = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $hostPlayer->id,
        'topic_id' => $topic2->id,
        'round_number' => 1,
        'turn_order' => 2,
        'status' => 'complete',
        'grade' => 'B',
        'score' => 85,
        'feedback' => 'Good explanation.',
        'actual_explanation' => 'The real answer.',
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer, $turn1, $turn2];
}

test('host can view game complete page', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/complete");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Complete')
        ->has('game')
        ->has('player')
        ->has('players')
        ->has('allTurns')
    );
});

test('game complete page shows players sorted by score descending', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/complete");

    $response->assertInertia(fn ($page) => $page
        ->component('games/Complete')
        ->where('players.0.score', 92)
        ->where('players.0.name', $guestPlayer->name)
        ->where('players.1.score', 85)
        ->where('players.1.name', $hostPlayer->name)
    );
});

test('game complete page includes all completed turns', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $turn1, $turn2] = buildCompletedGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/complete");

    $response->assertInertia(fn ($page) => $page
        ->component('games/Complete')
        ->has('allTurns', 2)
        ->where('allTurns.0.grade', 'A')
        ->where('allTurns.0.score', 92)
        ->where('allTurns.1.grade', 'B')
        ->where('allTurns.1.score', 85)
    );
});

test('guest player can view game complete page via session', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->get("/games/{$game->code}/complete");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('games/Complete'));
});

test('unauthenticated request to game complete returns 403', function () {
    [$host, $game] = buildCompletedGameFixture();

    $response = $this->get("/games/{$game->code}/complete");

    $response->assertStatus(403);
});

test('play endpoint redirects to complete when game is complete', function () {
    [$host, $game, $hostPlayer] = buildCompletedGameFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/play");

    $response->assertRedirect("/games/{$game->code}/complete");
});

test('host can play again which creates a new game in lobby', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $response = $this->actingAs($host)->post("/games/{$game->code}/play-again");

    $response->assertRedirect();

    // A new game in lobby status should exist for this host
    $newGame = Game::where('host_user_id', $host->id)
        ->where('status', 'lobby')
        ->latest()
        ->first();

    expect($newGame)->not->toBeNull();
    expect($newGame->code)->not->toBe($game->code);
    expect($newGame->max_rounds)->toBe($game->max_rounds);

    // Host should be a player in the new game
    $newHostPlayer = $newGame->players()->where('user_id', $host->id)->first();
    expect($newHostPlayer)->not->toBeNull();
    expect($newHostPlayer->is_host)->toBeTrue();
});

test('guest session cannot play again', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->post("/games/{$game->code}/play-again");

    $response->assertStatus(403);
});

test('non-host user cannot play again', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildCompletedGameFixture();

    $otherUser = User::factory()->create();
    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $otherUser->id,
        'is_host' => false,
    ]);

    $response = $this->actingAs($otherUser)->post("/games/{$game->code}/play-again");

    $response->assertStatus(403);
});
