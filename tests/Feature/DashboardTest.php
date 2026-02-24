<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows past games for the authenticated host', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'complete']);
    Player::factory()->create(['game_id' => $game->id, 'is_host' => true, 'score' => 80]);
    Player::factory()->create(['game_id' => $game->id, 'is_host' => false, 'score' => 60]);

    $this->actingAs($user);
    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('games', 1)
        ->where('games.0.code', $game->code)
        ->where('games.0.player_count', 2)
        ->where('games.0.status', 'complete')
    );
});

test('dashboard only shows games hosted by the authenticated user', function () {
    $host = User::factory()->create();
    $other = User::factory()->create();

    Game::factory()->create(['host_user_id' => $other->id]);

    $this->actingAs($host);
    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('games', 0)
    );
});

test('dashboard shows winner for completed games', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id, 'status' => 'complete']);
    $winner = Player::factory()->create(['game_id' => $game->id, 'score' => 90, 'name' => 'Top Player']);
    Player::factory()->create(['game_id' => $game->id, 'score' => 40]);

    $this->actingAs($user);
    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('games.0.winner.name', 'Top Player')
        ->where('games.0.winner.score', 90)
    );
});

test('dashboard shows games ordered newest first', function () {
    $user = User::factory()->create();
    $old = Game::factory()->create(['host_user_id' => $user->id, 'created_at' => now()->subDays(2)]);
    $new = Game::factory()->create(['host_user_id' => $user->id, 'created_at' => now()]);

    $this->actingAs($user);
    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('games.0.code', $new->code)
        ->where('games.1.code', $old->code)
    );
});
