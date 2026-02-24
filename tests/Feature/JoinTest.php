<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can visit join page for a lobby game', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->get("/join/{$game->code}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Join')
        ->where('game.code', $game->code)
        ->whereNull('error')
        ->whereNotNull('suggestedName')
    );
});

test('join page shows error when game has already started', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    $response = $this->get("/join/{$game->code}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Join')
        ->where('error', 'This game has already started')
    );
});

test('join page shows error when game is full', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create(['status' => 'lobby', 'host_user_id' => $host->id]);

    Player::factory()->count(10)->create(['game_id' => $game->id]);

    $response = $this->get("/join/{$game->code}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Join')
        ->where('error', 'This game is full')
    );
});

test('join page returns 404 for unknown game code', function () {
    $response = $this->get('/join/XXXXXX');
    $response->assertNotFound();
});

test('guest can join a lobby game and player record is created', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->post("/join/{$game->code}", ['name' => 'Bold Llama']);

    $response->assertRedirect("/games/{$game->code}/lobby");

    $player = Player::where('game_id', $game->id)->where('name', 'Bold Llama')->first();
    expect($player)->not->toBeNull();
    expect($player->user_id)->toBeNull();
    expect($player->is_host)->toBeFalse();
    expect($player->has_submitted)->toBeFalse();
});

test('joining stores player_id in the session', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->post("/join/{$game->code}", ['name' => 'Sneaky Ferret']);

    $player = Player::where('game_id', $game->id)->first();

    $response->assertSessionHas("player_id.{$game->code}", $player->id);
});

test('cannot join a game that has already started', function () {
    $game = Game::factory()->create(['status' => 'playing']);

    $response = $this->post("/join/{$game->code}", ['name' => 'Bold Llama']);

    $response->assertSessionHasErrors('game');
    expect(Player::where('game_id', $game->id)->count())->toBe(0);
});

test('cannot join a full game', function () {
    $host = User::factory()->create();
    $game = Game::factory()->create(['status' => 'lobby', 'host_user_id' => $host->id]);

    Player::factory()->count(10)->create(['game_id' => $game->id]);

    $response = $this->post("/join/{$game->code}", ['name' => 'Late Arrival']);

    $response->assertSessionHasErrors('game');
    expect(Player::where('game_id', $game->id)->count())->toBe(10);
});

test('joining requires a name', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->post("/join/{$game->code}", ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('name cannot exceed 50 characters', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->post("/join/{$game->code}", ['name' => str_repeat('a', 51)]);

    $response->assertSessionHasErrors('name');
});

test('join is case-insensitive for the game code', function () {
    $game = Game::factory()->create(['status' => 'lobby', 'code' => 'ABC123']);

    $response = $this->post('/join/abc123', ['name' => 'Fuzzy Wombat']);

    $response->assertRedirect('/games/ABC123/lobby');

    expect(Player::where('game_id', $game->id)->count())->toBe(1);
});

test('suggested name is a two-word animal combo', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->get("/join/{$game->code}");

    $response->assertInertia(fn ($page) => $page
        ->where('suggestedName', fn ($name) => preg_match('/^\w+ \w+$/', $name) === 1)
    );
});
