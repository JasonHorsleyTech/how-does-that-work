<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

// Helper: create a game in grading_complete status with one complete turn
function buildResultsGame(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'grading_complete',
        'current_round' => 1,
        'max_rounds' => 1,
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'name' => 'Host Player',
        'is_host' => true,
        'score' => 0,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'name' => 'Guest Player',
        'is_host' => false,
        'score' => 75,
    ]);

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
        'status' => 'complete',
        'score' => 75,
        'grade' => 'B',
        'feedback' => 'Good understanding of the heating mechanism.',
        'actual_explanation' => 'Microwaves use electromagnetic radiation to excite water molecules.',
    ]);

    return [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn];
}

test('host can view results page for a completed turn', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    $response = $this->actingAs($host)->get("/games/{$game->code}/results/{$turn->id}");

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('games/Results')
        ->where('turn.id', $turn->id)
        ->where('turn.player_name', 'Guest Player')
        ->where('turn.topic_text', 'How does a microwave work?')
        ->where('turn.grade', 'B')
        ->where('turn.score', 75)
        ->where('turn.feedback', 'Good understanding of the heating mechanism.')
        ->where('turn.actual_explanation', 'Microwaves use electromagnetic radiation to excite water molecules.')
        ->where('player.is_host', true)
        ->has('players', 2)
    );
});

test('guest player can view results page via session', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    $response = $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->get("/games/{$game->code}/results/{$turn->id}");

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('games/Results')
        ->where('player.id', $guestPlayer->id)
        ->where('player.is_host', false)
    );
});

test('results page players are sorted by score descending', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    // Give host player a lower score
    $hostPlayer->update(['score' => 20]);
    $guestPlayer->update(['score' => 75]);

    $response = $this->actingAs($host)->get("/games/{$game->code}/results/{$turn->id}");

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('games/Results')
        ->where('players.0.name', 'Guest Player')
        ->where('players.0.score', 75)
        ->where('players.1.name', 'Host Player')
        ->where('players.1.score', 20)
    );
});

test('results page shows grading_failed turn status', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    $turn->update([
        'status' => 'grading_failed',
        'score' => null,
        'grade' => null,
        'feedback' => null,
        'actual_explanation' => null,
    ]);

    $response = $this->actingAs($host)->get("/games/{$game->code}/results/{$turn->id}");

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('games/Results')
        ->where('turn.status', 'grading_failed')
        ->where('turn.grade', null)
        ->where('turn.score', null)
    );
});

test('unauthenticated user without session cannot view results', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    $response = $this->get("/games/{$game->code}/results/{$turn->id}");

    $response->assertStatus(403);
});

test('play-state returns completedTurnId when game is grading_complete', function () {
    [$host, $game, $hostPlayer, $guestPlayer, $topic, $turn] = buildResultsGame();

    $response = $this->actingAs($host)->getJson("/games/{$game->code}/play-state");

    $response->assertOk()
        ->assertJson([
            'gameStatus' => 'grading_complete',
            'completedTurnId' => $turn->id,
        ]);
});

test('play-state returns null completedTurnId when game is playing', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
    ]);
    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $response = $this->actingAs($host)->getJson("/games/{$game->code}/play-state");

    $response->assertOk()
        ->assertJson(['completedTurnId' => null]);
});
