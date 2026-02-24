<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('game belongs to a host user', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);

    expect($game->host)->toBeInstanceOf(User::class)
        ->and($game->host->id)->toBe($user->id);
});

test('game has many players', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    Player::factory()->count(3)->create(['game_id' => $game->id]);

    expect($game->players)->toHaveCount(3)
        ->each->toBeInstanceOf(Player::class);
});

test('game has many topics', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    Topic::factory()->count(3)->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    expect($game->topics)->toHaveCount(3)
        ->each->toBeInstanceOf(Topic::class);
});

test('game has many turns', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);
    Turn::factory()->count(2)->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
    ]);

    expect($game->turns)->toHaveCount(2)
        ->each->toBeInstanceOf(Turn::class);
});
