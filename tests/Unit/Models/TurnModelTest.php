<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('turn belongs to a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);
    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
    ]);

    expect($turn->game)->toBeInstanceOf(Game::class)
        ->and($turn->game->id)->toBe($game->id);
});

test('turn belongs to a player', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);
    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
    ]);

    expect($turn->player)->toBeInstanceOf(Player::class)
        ->and($turn->player->id)->toBe($player->id);
});

test('turn belongs to a topic', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);
    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => $topic->id,
    ]);

    expect($turn->topic)->toBeInstanceOf(Topic::class)
        ->and($turn->topic->id)->toBe($topic->id);
});

test('turn topic_id can be null', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'topic_id' => null,
    ]);

    expect($turn->topic)->toBeNull();
});
