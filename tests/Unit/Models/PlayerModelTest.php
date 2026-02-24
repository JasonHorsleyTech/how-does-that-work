<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('player belongs to a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);

    expect($player->game)->toBeInstanceOf(Game::class)
        ->and($player->game->id)->toBe($game->id);
});

test('player can belong to a user', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
    ]);

    expect($player->user)->toBeInstanceOf(User::class)
        ->and($player->user->id)->toBe($user->id);
});

test('player user_id can be null for guests', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
    ]);

    expect($player->user)->toBeNull();
});

test('player has many topics', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    Topic::factory()->count(3)->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    expect($player->topics)->toHaveCount(3)
        ->each->toBeInstanceOf(Topic::class);
});

test('player has many turns', function () {
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

    expect($player->turns)->toHaveCount(2)
        ->each->toBeInstanceOf(Turn::class);
});
