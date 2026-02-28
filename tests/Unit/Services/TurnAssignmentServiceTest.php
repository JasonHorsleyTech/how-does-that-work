<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use App\Services\TurnAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper: create a game with N non-host players + 1 host, each with 3 submitted topics.
 * The host is also included in the game as a player (and now gets turns too).
 * Returns [$game, $players] where $players includes non-host players only (for backward compat).
 */
function createGameWithPlayers(int $playerCount, int $maxRounds = 1): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
        'max_rounds' => $maxRounds,
    ]);

    // Host player record (with topics, since host now plays too)
    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    Topic::factory()->count(3)->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
    ]);

    $players = Player::factory()->count($playerCount)->create([
        'game_id' => $game->id,
        'is_host' => false,
    ]);

    foreach ($players as $player) {
        Topic::factory()->count(3)->create([
            'game_id' => $game->id,
            'submitted_by_player_id' => $player->id,
        ]);
    }

    return [$game, $players];
}

test('no turn assigns a player their own submitted topic', function () {
    [$game] = createGameWithPlayers(5);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::with('player')->where('game_id', $game->id)->get();

    foreach ($turns as $turn) {
        $playerTopicIds = Topic::where('submitted_by_player_id', $turn->player_id)
            ->pluck('id')
            ->all();

        foreach ($turn->topic_choices as $choiceId) {
            expect($playerTopicIds)->not->toContain($choiceId);
        }
    }
});

test('no topic appears in two different turns topic_choices', function () {
    [$game] = createGameWithPlayers(5);

    (new TurnAssignmentService)->assignTurns($game);

    $allChoices = Turn::where('game_id', $game->id)
        ->get()
        ->flatMap(fn ($t) => $t->topic_choices)
        ->all();

    expect(count($allChoices))->toBe(count(array_unique($allChoices)));
});

test('algorithm works correctly with 2 non-host players (3 total with host)', function () {
    [$game, $players] = createGameWithPlayers(2);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->orderBy('turn_order')->get();

    // 3 players (host + 2) × 3 topics each = 9 topics; 3 turns × 2 choices = 6 claimed
    expect($turns)->toHaveCount(3);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBeGreaterThanOrEqual(1);
        expect($turn->status)->toBe('pending');
        expect($turn->round_number)->toBe(1);
    }

    // Turn orders should be 1, 2, and 3
    expect($turns->pluck('turn_order')->sort()->values()->all())->toBe([1, 2, 3]);
});

test('algorithm works correctly with 5 non-host players (6 total with host)', function () {
    [$game] = createGameWithPlayers(5);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 6 players (host + 5) × 3 topics = 18 topics; 6 turns × 2 choices = 12 claimed
    expect($turns)->toHaveCount(6);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBe(2);
    }
});

test('algorithm works correctly with 10 non-host players (11 total with host)', function () {
    [$game] = createGameWithPlayers(10);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 11 players (host + 10) × 3 topics = 33 topics; 11 turns × 2 choices = 22 claimed
    expect($turns)->toHaveCount(11);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBe(2);
    }
});

test('generates turns for both rounds when max_rounds is 2', function () {
    [$game] = createGameWithPlayers(3, maxRounds: 2);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 4 players (host + 3) × 3 topics = 12 topics; round 1: 4×2=8 claimed; round 2: 4 remain
    expect($turns->where('round_number', 1))->toHaveCount(4);

    $round1Choices = $turns->where('round_number', 1)->flatMap(fn ($t) => $t->topic_choices)->values();
    $round2Choices = $turns->where('round_number', 2)->flatMap(fn ($t) => $t->topic_choices)->values();

    // No overlap between round 1 and round 2 choices
    $overlap = $round1Choices->intersect($round2Choices);
    expect($overlap)->toHaveCount(0);
});

test('skips player turn when no eligible topics remain', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
        'max_rounds' => 1,
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    // Two non-host players, but all topics submitted by player A
    $playerA = Player::factory()->create(['game_id' => $game->id, 'is_host' => false]);
    $playerB = Player::factory()->create(['game_id' => $game->id, 'is_host' => false]);

    // Only 2 topics, both by playerA → playerA can't use them
    Topic::factory()->count(2)->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $playerA->id,
    ]);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // playerA has no eligible topics (all 2 were submitted by playerA) — always skipped
    $playerATurns = $turns->where('player_id', $playerA->id);
    expect($playerATurns)->toHaveCount(0);

    // Only 2 topics total (enough for 1 turn with 2 choices), so only one of host/playerB gets a turn
    expect($turns)->toHaveCount(1);

    // The turn must belong to either host or playerB (not playerA)
    $turnPlayerId = $turns->first()->player_id;
    expect([$hostPlayer->id, $playerB->id])->toContain($turnPlayerId);
});

test('host and single guest both get turns in a 2-player game', function () {
    [$game] = createGameWithPlayers(1);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 2 players (host + 1 guest) × 3 topics = 6 topics; 2 turns × 2 choices = 4 claimed
    expect($turns)->toHaveCount(2);

    // Both players should have a turn
    $playerIds = $turns->pluck('player_id')->unique();
    expect($playerIds)->toHaveCount(2);

    // No player gets their own submitted topics
    foreach ($turns as $turn) {
        $playerTopicIds = Topic::where('submitted_by_player_id', $turn->player_id)
            ->pluck('id')
            ->all();

        foreach ($turn->topic_choices as $choiceId) {
            expect($playerTopicIds)->not->toContain($choiceId);
        }
    }
});
