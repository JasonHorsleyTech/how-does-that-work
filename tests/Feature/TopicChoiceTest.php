<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper to set up a playing game with one choosing turn and topics
function setupChoosingTurn(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
        'current_round' => 1,
        'max_rounds' => 1,
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $activePlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
        'name' => 'Active Player',
    ]);

    // Topics submitted by host (so active player can use them)
    $topic1 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a microwave work?',
        'is_used' => false,
    ]);
    $topic2 = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does glue work?',
        'is_used' => false,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $activePlayer->id,
        'topic_choices' => [$topic1->id, $topic2->id],
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'choosing',
    ]);

    return compact('host', 'game', 'hostPlayer', 'activePlayer', 'topic1', 'topic2', 'turn');
}

test('play page loads for authenticated host with choosing turn', function () {
    ['host' => $host, 'game' => $game, 'turn' => $turn, 'activePlayer' => $activePlayer] = setupChoosingTurn();

    $this->actingAs($host)
        ->get("/games/{$game->code}/play")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('games/Play')
            ->has('game')
            ->has('player')
            ->has('currentTurn', fn ($t) => $t
                ->where('id', $turn->id)
                ->where('status', 'choosing')
                ->where('player_name', $activePlayer->name)
                ->has('topic_choices', 2)
                ->etc()
            )
            ->where('isActivePlayer', false)
        );
});

test('play page shows active player their topic choices via session', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupChoosingTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->get("/games/{$game->code}/play")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('games/Play')
            ->where('isActivePlayer', true)
            ->has('currentTurn', fn ($t) => $t
                ->where('status', 'choosing')
                ->has('topic_choices', 2)
                ->etc()
            )
        );
});

test('active player can choose a topic', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'topic1' => $topic1, 'topic2' => $topic2, 'turn' => $turn] = setupChoosingTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->post("/games/{$game->code}/turns/{$turn->id}/choose-topic", [
            'topic_id' => $topic1->id,
        ])
        ->assertRedirect("/games/{$game->code}/play");

    // Turn status transitions to recording
    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'topic_id' => $topic1->id,
        'status' => 'recording',
    ]);

    // Chosen topic is_used = true
    $this->assertDatabaseHas('topics', [
        'id' => $topic1->id,
        'is_used' => true,
    ]);

    // Unchosen topic remains available
    $this->assertDatabaseHas('topics', [
        'id' => $topic2->id,
        'is_used' => false,
    ]);
});

test('active player cannot choose a topic not in their turn choices', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn, 'hostPlayer' => $hostPlayer] = setupChoosingTurn();

    $otherTopic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'A different topic',
        'is_used' => false,
    ]);

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->post("/games/{$game->code}/turns/{$turn->id}/choose-topic", [
            'topic_id' => $otherTopic->id,
        ])
        ->assertSessionHasErrors('topic_id');

    $this->assertDatabaseHas('turns', ['id' => $turn->id, 'status' => 'choosing']);
});

test('non-active player cannot choose a topic for another player\'s turn', function () {
    ['game' => $game, 'turn' => $turn, 'topic1' => $topic1] = setupChoosingTurn();

    $otherPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $this->withSession(["player_id.{$game->code}" => $otherPlayer->id])
        ->post("/games/{$game->code}/turns/{$turn->id}/choose-topic", [
            'topic_id' => $topic1->id,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('turns', ['id' => $turn->id, 'status' => 'choosing']);
});

test('unauthenticated user cannot access play page', function () {
    ['game' => $game] = setupChoosingTurn();

    $this->get("/games/{$game->code}/play")
        ->assertForbidden();
});

test('play-state endpoint returns current turn info', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupChoosingTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->getJson("/games/{$game->code}/play-state")
        ->assertOk()
        ->assertJson([
            'gameStatus' => 'playing',
            'turnStatus' => 'choosing',
            'turnId' => $turn->id,
        ]);
});

test('game transitions first turn to choosing when game starts', function () {
    $host = User::factory()->create();
    $this->actingAs($host);

    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'submitting',
        'max_rounds' => 1,
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);
    $player1 = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);
    $player2 = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    // Create topics so turn assignment has something to work with
    Topic::factory()->create(['game_id' => $game->id, 'submitted_by_player_id' => $hostPlayer->id]);
    Topic::factory()->create(['game_id' => $game->id, 'submitted_by_player_id' => $hostPlayer->id]);
    Topic::factory()->create(['game_id' => $game->id, 'submitted_by_player_id' => $hostPlayer->id]);

    $this->post("/games/{$game->code}/start-game");

    // At least one turn should be in 'choosing' status
    $this->assertDatabaseHas('turns', [
        'game_id' => $game->id,
        'status' => 'choosing',
    ]);
});
