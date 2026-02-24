<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Lobby page access ---

test('authenticated host can access the lobby page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);

    $response = $this->get(route('games.lobby', ['code' => $game->code]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Lobby')
        ->where('isHost', true)
        ->has('game')
        ->has('joinUrl')
    );
});

test('guest player with valid session can access the lobby page', function () {
    $game = Game::factory()->create(['status' => 'lobby']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    $response = $this->withSession(["player_id.{$game->code}" => $player->id])
        ->get(route('games.lobby', ['code' => $game->code]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Lobby')
        ->where('isHost', false)
    );
});

test('unauthenticated user without session is forbidden from the lobby', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->get(route('games.lobby', ['code' => $game->code]));

    $response->assertForbidden();
});

test('authenticated user with no player record is forbidden from the lobby', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->get(route('games.lobby', ['code' => $game->code]));

    $response->assertForbidden();
});

// --- Polling endpoint ---

test('polling endpoint returns player list for authenticated host', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    $host = Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'name' => 'Host Player']);
    $guest = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'name' => 'Guest One']);

    $response = $this->getJson(route('games.players', ['code' => $game->code]));

    $response->assertOk();
    $response->assertJsonStructure([
        'players' => [['id', 'name', 'is_host']],
        'nonHostCount',
    ]);
    $response->assertJsonCount(2, 'players');
    $response->assertJson(['nonHostCount' => 1]);
});

test('polling endpoint returns player list for guest with valid session', function () {
    $game = Game::factory()->create(['status' => 'lobby']);
    $player = Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    $response = $this->withSession(["player_id.{$game->code}" => $player->id])
        ->getJson(route('games.players', ['code' => $game->code]));

    $response->assertOk();
    $response->assertJsonStructure(['players', 'nonHostCount']);
    $response->assertJsonCount(1, 'players');
    $response->assertJson(['nonHostCount' => 1]);
});

test('polling endpoint returns 403 for unauthenticated user without session', function () {
    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->getJson(route('games.players', ['code' => $game->code]));

    $response->assertForbidden();
});

test('polling endpoint returns 403 for authenticated user not in the game', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['status' => 'lobby']);

    $response = $this->getJson(route('games.players', ['code' => $game->code]));

    $response->assertForbidden();
});

test('nonHostCount reflects the correct number of non-host players', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);
    Player::factory()->count(3)->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false]);

    $response = $this->getJson(route('games.players', ['code' => $game->code]));

    $response->assertOk();
    $response->assertJson(['nonHostCount' => 3]);
    $response->assertJsonCount(4, 'players');
});
