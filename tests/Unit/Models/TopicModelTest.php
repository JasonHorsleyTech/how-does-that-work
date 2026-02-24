<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('topic belongs to a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    expect($topic->game)->toBeInstanceOf(Game::class)
        ->and($topic->game->id)->toBe($game->id);
});

test('topic belongs to a submitting player', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['host_user_id' => $user->id]);
    $player = Player::factory()->create(['game_id' => $game->id]);
    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $player->id,
    ]);

    expect($topic->submittedBy)->toBeInstanceOf(Player::class)
        ->and($topic->submittedBy->id)->toBe($player->id);
});

test('topic has many turns', function () {
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

    expect($topic->turns)->toHaveCount(2)
        ->each->toBeInstanceOf(Turn::class);
});
