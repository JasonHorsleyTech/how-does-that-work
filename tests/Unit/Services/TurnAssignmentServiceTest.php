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
 * Helper: create a game with N non-host players, each with 3 submitted topics.
 * Returns [$game, $players].
 */
function createGameWithPlayers(int $playerCount, int $maxRounds = 1): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
        'max_rounds' => $maxRounds,
    ]);

    // Host player record
    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
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

test('algorithm works correctly with 2 players', function () {
    [$game, $players] = createGameWithPlayers(2);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->orderBy('turn_order')->get();

    // 2 players × 3 topics each = 6 topics; 2 turns possible (2 choices each = 4 claimed)
    expect($turns)->toHaveCount(2);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBeGreaterThanOrEqual(1);
        expect($turn->status)->toBe('pending');
        expect($turn->round_number)->toBe(1);
    }

    // Turn orders should be 1 and 2
    expect($turns->pluck('turn_order')->sort()->values()->all())->toBe([1, 2]);
});

test('algorithm works correctly with 5 players', function () {
    [$game] = createGameWithPlayers(5);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 5 players × 3 topics = 15 topics; 5 players get 2 choices each = 10 claimed
    expect($turns)->toHaveCount(5);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBe(2);
    }
});

test('algorithm works correctly with 10 players', function () {
    [$game] = createGameWithPlayers(10);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 10 players × 3 topics = 30 topics; 10 turns × 2 choices = 20 claimed
    expect($turns)->toHaveCount(10);

    foreach ($turns as $turn) {
        expect($turn->topic_choices)->toBeArray();
        expect(count($turn->topic_choices))->toBe(2);
    }
});

test('generates turns for both rounds when max_rounds is 2', function () {
    [$game] = createGameWithPlayers(3, maxRounds: 2);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // 3 players × 3 topics = 9 topics; round 1: 3×2=6 claimed; round 2: 3 remain, 3 players
    // Round 2 players each get 1 choice (only 1 eligible each after round 1)
    // Total turns: 3 (round 1) + up to 3 (round 2)
    expect($turns->where('round_number', 1))->toHaveCount(3);

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

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    // Two non-host players, but all topics submitted by player A
    $playerA = Player::factory()->create(['game_id' => $game->id, 'is_host' => false]);
    $playerB = Player::factory()->create(['game_id' => $game->id, 'is_host' => false]);

    // Only 2 topics, both by playerA → playerA can't use them; playerB needs topics not by B
    Topic::factory()->count(2)->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $playerA->id,
    ]);

    (new TurnAssignmentService)->assignTurns($game);

    $turns = Turn::where('game_id', $game->id)->get();

    // playerA has no eligible topics (all 2 were submitted by playerA)
    // playerB can use both topics submitted by playerA
    $playerATurns = $turns->where('player_id', $playerA->id);
    $playerBTurns = $turns->where('player_id', $playerB->id);

    expect($playerATurns)->toHaveCount(0);
    expect($playerBTurns)->toHaveCount(1);
});
