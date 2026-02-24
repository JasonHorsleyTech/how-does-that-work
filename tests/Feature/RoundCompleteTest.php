<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;

// Helper: game in round_complete with completed turns and pending round-2 turns
function buildRoundCompleteFixture(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'round_complete',
        'current_round' => 1,
        'max_rounds' => 2,
        'state_updated_at' => now()->subMinute(),
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
        'score' => 0,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
        'score' => 75,
    ]);

    $topic1 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'is_used' => true,
    ]);

    $topic2 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $guestPlayer->id,
        'is_used' => false,
    ]);

    // Completed turn from round 1
    $completedTurn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic1->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
        'grade' => 'B',
        'score' => 75,
        'feedback' => 'Good explanation.',
        'actual_explanation' => 'The actual answer.',
    ]);

    // Pending turn for round 2 (pre-generated)
    $round2Turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => null,
        'round_number' => 2,
        'turn_order' => 1,
        'status' => 'pending',
        'topic_choices' => [$topic2->id],
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $round2Turn, $topic2];
}

test('host can view round complete page', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn] = buildRoundCompleteFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/round-complete");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/RoundComplete')
        ->has('game')
        ->has('player')
        ->has('players')
        ->has('roundTurns')
    );
});

test('round complete page shows players sorted by score', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildRoundCompleteFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/round-complete");

    $response->assertInertia(fn ($page) => $page
        ->component('games/RoundComplete')
        ->where('players.0.score', 75)
        ->where('players.0.name', $guestPlayer->name)
        ->where('players.1.score', 0)
        ->where('players.1.name', $hostPlayer->name)
    );
});

test('round complete page shows turn grade history for current round', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn] = buildRoundCompleteFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/round-complete");

    $response->assertInertia(fn ($page) => $page
        ->component('games/RoundComplete')
        ->has('roundTurns', 1)
        ->where('roundTurns.0.grade', 'B')
        ->where('roundTurns.0.score', 75)
        ->where('roundTurns.0.player_name', $guestPlayer->name)
    );
});

test('round complete page shows game max_rounds', function () {
    [$host, $game] = buildRoundCompleteFixture();

    $response = $this->actingAs($host)->get("/games/{$game->code}/round-complete");

    $response->assertInertia(fn ($page) => $page
        ->component('games/RoundComplete')
        ->where('game.max_rounds', 2)
        ->where('game.current_round', 1)
    );
});

test('guest player can view round complete page via session', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildRoundCompleteFixture();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->get("/games/{$game->code}/round-complete");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('games/RoundComplete'));
});

test('unauthenticated request to round complete returns 403', function () {
    [$host, $game] = buildRoundCompleteFixture();

    $response = $this->get("/games/{$game->code}/round-complete");

    $response->assertStatus(403);
});

test('host can start next round', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $round2Turn] = buildRoundCompleteFixture();

    $response = $this->actingAs($host)->post("/games/{$game->code}/start-next-round");

    $response->assertRedirect("/games/{$game->code}/play");

    $game->refresh();
    expect($game->status)->toBe('playing');
    expect($game->current_round)->toBe(2);

    $round2Turn->refresh();
    expect($round2Turn->status)->toBe('choosing');
});

test('non-host cannot start next round', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildRoundCompleteFixture();

    $otherUser = User::factory()->create();
    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $otherUser->id,
        'is_host' => false,
    ]);

    $response = $this->actingAs($otherUser)->post("/games/{$game->code}/start-next-round");

    $response->assertStatus(403);
});

test('guest session cannot start next round', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = buildRoundCompleteFixture();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->post("/games/{$game->code}/start-next-round");

    $response->assertStatus(403);
});

test('round complete state is correct after all turns in current round are done', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'grading_complete',
        'current_round' => 1,
        'max_rounds' => 2,
        'state_updated_at' => now()->subMinute(),
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $topic1 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'is_used' => true,
    ]);

    $topic2 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $guestPlayer->id,
        'is_used' => false,
    ]);

    // Round 1 turn: complete
    Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic1->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
    ]);

    // Round 2 turn: pending (pre-generated)
    Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => null,
        'round_number' => 2,
        'turn_order' => 1,
        'status' => 'pending',
        'topic_choices' => [$topic2->id],
    ]);

    // Host advances — no more pending turns in round 1, so round_complete
    $response = $this->actingAs($host)->post("/games/{$game->code}/advance");

    $response->assertRedirect("/games/{$game->code}/round-complete");

    $game->refresh();
    expect($game->status)->toBe('round_complete');
    expect($game->current_round)->toBe(1); // Still round 1 until host starts next round
});
