<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;

// Helper: game in grading_complete with one complete turn and one pending turn
function buildAdvanceFixture(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'grading_complete',
        'current_round' => 1,
        'max_rounds' => 1,
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

    // Completed turn (just finished grading)
    $completedTurn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic1->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
    ]);

    // Next pending turn
    $pendingTurn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $hostPlayer->id,
        'topic_id' => null,
        'round_number' => 1,
        'turn_order' => 2,
        'status' => 'pending',
        'topic_choices' => [$topic2->id],
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $pendingTurn];
}

test('host can advance to next turn', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $pendingTurn] = buildAdvanceFixture();

    $oldStateUpdatedAt = $game->state_updated_at;

    $response = $this->actingAs($host)->post("/games/{$game->code}/advance");

    $response->assertRedirect("/games/{$game->code}/play");

    $game->refresh();
    expect($game->status)->toBe('playing');
    expect($game->state_updated_at->isAfter($oldStateUpdatedAt))->toBeTrue();

    $pendingTurn->refresh();
    expect($pendingTurn->status)->toBe('choosing');
});

test('host advance transitions to round_complete when no pending turns remain', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'grading_complete',
        'current_round' => 1,
        'max_rounds' => 1,
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

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'is_used' => true,
    ]);

    // Only one turn, already complete — no pending turns
    Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'complete',
    ]);

    $oldStateUpdatedAt = $game->state_updated_at;

    $response = $this->actingAs($host)->post("/games/{$game->code}/advance");

    $response->assertRedirect("/games/{$game->code}/play");

    $game->refresh();
    expect($game->status)->toBe('round_complete');
    expect($game->state_updated_at->isAfter($oldStateUpdatedAt))->toBeTrue();
});

test('non-host player cannot advance', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $pendingTurn] = buildAdvanceFixture();

    // Create a second authenticated user who is not the host
    $otherUser = User::factory()->create();
    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $otherUser->id,
        'is_host' => false,
    ]);

    $response = $this->actingAs($otherUser)->post("/games/{$game->code}/advance");

    $response->assertStatus(403);

    $game->refresh();
    expect($game->status)->toBe('grading_complete');
});

test('guest player session cannot advance', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $pendingTurn] = buildAdvanceFixture();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->post("/games/{$game->code}/advance");

    $response->assertStatus(403);

    $game->refresh();
    expect($game->status)->toBe('grading_complete');
});

test('unauthenticated request to advance returns 403', function () {
    [$host, $game] = buildAdvanceFixture();

    $response = $this->post("/games/{$game->code}/advance");

    $response->assertStatus(403);
});

test('advance only sets the lowest pending turn to choosing', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $completedTurn, $pendingTurn] = buildAdvanceFixture();

    // Add a third turn with higher turn_order
    $thirdPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $laterTurn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $thirdPlayer->id,
        'round_number' => 1,
        'turn_order' => 3,
        'status' => 'pending',
    ]);

    $this->actingAs($host)->post("/games/{$game->code}/advance");

    $pendingTurn->refresh();
    $laterTurn->refresh();

    expect($pendingTurn->status)->toBe('choosing');
    expect($laterTurn->status)->toBe('pending');
});
