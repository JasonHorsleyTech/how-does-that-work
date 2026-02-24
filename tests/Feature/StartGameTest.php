<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('host can force-start the game and status transitions to playing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => true]);
    Player::factory()->count(2)->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);

    $response = $this->post(route('games.start-game', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/play");
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'playing']);
});

test('host can force-start even when no players have submitted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => false]);
    Player::factory()->count(3)->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);

    $response = $this->post(route('games.start-game', ['code' => $game->code]));

    $response->assertRedirect("/games/{$game->code}/play");
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'playing']);
});

test('non-host cannot start the game', function () {
    $hostUser = User::factory()->create();
    $guestUser = User::factory()->create();
    $this->actingAs($guestUser);

    $game = Game::factory()->create(['host_user_id' => $hostUser->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $hostUser->id, 'is_host' => true]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $guestUser->id, 'is_host' => false]);

    $response = $this->post(route('games.start-game', ['code' => $game->code]));

    $response->assertForbidden();
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'submitting']);
});

test('unauthenticated user cannot start the game', function () {
    $game = Game::factory()->create(['status' => 'submitting']);

    $response = $this->post(route('games.start-game', ['code' => $game->code]));

    $response->assertRedirect('/login');
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'submitting']);
});

test('host cannot start game when game is not in submitting status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'lobby']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true]);

    $response = $this->post(route('games.start-game', ['code' => $game->code]));

    $response->assertSessionHasErrors('game');
    $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'lobby']);
});

test('submission status endpoint includes player submission status for host', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => true, 'name' => 'Host']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false, 'name' => 'Guest A']);

    $response = $this->getJson(route('games.submission-status', ['code' => $game->code]));

    $response->assertOk();
    $response->assertJson([
        'submittedCount' => 1,
        'totalCount' => 2,
        'gameStatus' => 'submitting',
    ]);
    $response->assertJsonCount(2, 'players');

    $players = $response->json('players');
    $hostEntry = collect($players)->firstWhere('name', 'Host');
    $guestEntry = collect($players)->firstWhere('name', 'Guest A');

    expect($hostEntry['has_submitted'])->toBeTrue();
    expect($guestEntry['has_submitted'])->toBeFalse();

    // Ensure topic text is never returned
    foreach ($players as $p) {
        expect($p)->not->toHaveKey('topics');
        expect($p)->not->toHaveKey('text');
    }
});

test('submit page passes players prop to host view', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'submitting']);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'is_host' => true, 'has_submitted' => true]);
    Player::factory()->create(['game_id' => $game->id, 'user_id' => null, 'is_host' => false, 'has_submitted' => false]);

    $response = $this->get(route('games.submit', ['code' => $game->code]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('games/Submit')
        ->has('players', 2)
        ->has('players.0', fn ($player) => $player
            ->has('name')
            ->has('has_submitted')
            ->missing('topics')
        )
    );
});
