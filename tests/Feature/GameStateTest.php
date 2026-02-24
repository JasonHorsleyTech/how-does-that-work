<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// Helper: create a host + game with players in a given status
function makeGameForState(string $status, int $maxRounds = 1): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => $status,
        'current_round' => 1,
        'max_rounds' => $maxRounds,
        'state_updated_at' => now(),
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'name' => 'Host',
        'is_host' => true,
        'has_submitted' => false,
        'score' => 0,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'name' => 'Guest',
        'is_host' => false,
        'has_submitted' => true,
        'score' => 50,
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer];
}

beforeEach(function () {
    Cache::flush();
});

test('returns 403 when no player session and not authenticated', function () {
    [, $game] = makeGameForState('lobby');

    $this->getJson("/api/games/{$game->code}/state")
        ->assertStatus(403);
});

test('returns 403 when player_id in session does not belong to the game', function () {
    [, $game] = makeGameForState('lobby');

    $this->withSession(['player_id.'.$game->code => 9999])
        ->getJson("/api/games/{$game->code}/state")
        ->assertStatus(403);
});

test('returns correct shape for lobby status', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('lobby');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonStructure([
            'game' => ['status', 'current_round'],
            'current_turn',
            'players' => [
                '*' => ['id', 'name', 'score', 'has_submitted'],
            ],
            'last_updated',
        ])
        ->assertJsonPath('game.status', 'lobby')
        ->assertJsonPath('game.current_round', 1)
        ->assertJsonPath('current_turn', null);

    $players = $response->json('players');
    expect($players)->toHaveCount(2);
});

test('returns correct shape for submitting status with has_submitted flags', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('submitting');

    $hostPlayer->update(['has_submitted' => false]);
    $guestPlayer->update(['has_submitted' => true]);

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'submitting')
        ->assertJsonPath('current_turn', null);

    $players = collect($response->json('players'));
    $hostData = $players->firstWhere('id', $hostPlayer->id);
    $guestData = $players->firstWhere('id', $guestPlayer->id);

    expect($hostData['has_submitted'])->toBeFalse();
    expect($guestData['has_submitted'])->toBeTrue();
});

test('returns current_turn with null topic when status is choosing', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('playing');

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => null,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'choosing',
        'topic_choices' => [],
    ]);

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'playing')
        ->assertJsonPath('current_turn.id', $turn->id)
        ->assertJsonPath('current_turn.player_name', 'Guest')
        ->assertJsonPath('current_turn.topic', null)
        ->assertJsonPath('current_turn.status', 'choosing')
        ->assertJsonPath('current_turn.time_remaining', null);
});

test('returns current_turn with topic text when status is recording and topic chosen', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('playing');

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a microwave work?',
        'is_used' => true,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'recording',
        'started_at' => null,
        'topic_choices' => [$topic->id],
    ]);

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('current_turn.topic', 'How does a microwave work?')
        ->assertJsonPath('current_turn.status', 'recording')
        ->assertJsonPath('current_turn.time_remaining', null);
});

test('returns time_remaining when recording has started', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('playing');

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does glue work?',
        'is_used' => true,
    ]);

    $startedAt = now()->subSeconds(30);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'recording',
        'started_at' => $startedAt,
        'topic_choices' => [$topic->id],
    ]);

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk();
    $timeRemaining = $response->json('current_turn.time_remaining');
    // Should be approximately 90 seconds (120 - 30), allow ±2s for execution time
    expect($timeRemaining)->toBeGreaterThanOrEqual(88);
    expect($timeRemaining)->toBeLessThanOrEqual(92);
});

test('returns null current_turn when game is in grading_complete status', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('grading_complete');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'grading_complete')
        ->assertJsonPath('current_turn', null);
});

test('returns null current_turn when game is in round_complete status', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('round_complete');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'round_complete')
        ->assertJsonPath('current_turn', null);
});

test('returns null current_turn when game is complete', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('complete');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'complete')
        ->assertJsonPath('current_turn', null);
});

test('guest player with valid session can access state endpoint', function () {
    [, $game, , $guestPlayer] = makeGameForState('lobby');

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->getJson("/api/games/{$game->code}/state");

    $response->assertOk()
        ->assertJsonPath('game.status', 'lobby');
});

test('last_updated reflects game state_updated_at', function () {
    [$host, $game] = makeGameForState('lobby');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk();
    $lastUpdated = $response->json('last_updated');
    expect($lastUpdated)->not->toBeNull();
    expect($lastUpdated)->toBe($game->state_updated_at->toISOString());
});

test('players array contains id, name, score, has_submitted for each player', function () {
    [$host, $game, $hostPlayer, $guestPlayer] = makeGameForState('lobby');

    $response = $this->actingAs($host)->getJson("/api/games/{$game->code}/state");

    $response->assertOk();
    $players = $response->json('players');
    expect($players)->toHaveCount(2);

    foreach ($players as $player) {
        expect($player)->toHaveKeys(['id', 'name', 'score', 'has_submitted']);
    }
});
