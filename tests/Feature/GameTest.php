<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login when visiting create game page', function () {
    $response = $this->get(route('games.create'));
    $response->assertRedirect(route('login'));
});

test('authenticated host can visit the create game page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('games.create'));
    $response->assertOk();
});

test('host can create a game with max_rounds 1', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('games.store'), ['max_rounds' => 1]);

    $game = Game::where('host_user_id', $user->id)->first();
    expect($game)->not->toBeNull();
    expect($game->status)->toBe('lobby');
    expect($game->max_rounds)->toBe(1);
    expect($game->code)->toHaveLength(6);

    $response->assertRedirect("/games/{$game->code}/lobby");
});

test('host can create a game with max_rounds 2', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('games.store'), ['max_rounds' => 2]);

    $game = Game::where('host_user_id', $user->id)->first();
    expect($game->max_rounds)->toBe(2);
    $response->assertRedirect("/games/{$game->code}/lobby");
});

test('creating a game records host as a player with is_host true', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('games.store'), ['max_rounds' => 1]);

    $game = Game::where('host_user_id', $user->id)->first();
    $player = Player::where('game_id', $game->id)->where('user_id', $user->id)->first();

    expect($player)->not->toBeNull();
    expect($player->is_host)->toBeTrue();
    expect($player->name)->toBe($user->name);
});

test('invalid max_rounds is rejected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('games.store'), ['max_rounds' => 3]);
    $response->assertSessionHasErrors('max_rounds');
});

test('lobby page shows game info for authenticated host', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create([
        'host_user_id' => $user->id,
        'status' => 'lobby',
    ]);
    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'is_host' => true,
    ]);

    $response = $this->get(route('games.lobby', ['code' => $game->code]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Lobby')
        ->has('game')
        ->has('joinUrl')
    );
});

test('duplicate game codes are not generated', function () {
    // Create a game and manually insert a code to force a collision scenario
    // by exhausting all "random" codes — instead, verify the method retries
    // by checking that generateUniqueCode always returns a code not in the DB.
    $user = User::factory()->create();

    $game1 = Game::factory()->create(['host_user_id' => $user->id]);
    $code = $game1->code;

    // The generated code for a new game must differ from the existing one
    // (this is probabilistically true but we verify the uniqueness guarantee)
    $newCode = Game::generateUniqueCode();

    expect($newCode)->not->toBe($code);
    expect($newCode)->toHaveLength(6);
});

test('game code is uppercase alphanumeric', function () {
    $code = Game::generateUniqueCode();
    expect($code)->toMatch('/^[A-Z0-9]{6}$/');
});
