<?php

use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Helper to set up a playing game with one recording turn and a chosen topic
function setupAudioUploadTurn(): array
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

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a microwave work?',
        'is_used' => true,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $activePlayer->id,
        'topic_id' => $topic->id,
        'topic_choices' => [$topic->id],
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'recording',
        'started_at' => null,
    ]);

    return compact('host', 'game', 'hostPlayer', 'activePlayer', 'topic', 'turn');
}

test('active player can start recording — sets started_at', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->postJson("/games/{$game->code}/turns/{$turn->id}/start-recording")
        ->assertOk()
        ->assertJsonStructure(['started_at']);

    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'status' => 'recording',
    ]);

    $this->assertNotNull($turn->fresh()->started_at);
});

test('non-active player cannot call start-recording', function () {
    ['game' => $game, 'turn' => $turn] = setupAudioUploadTurn();

    $otherPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $this->withSession(["player_id.{$game->code}" => $otherPlayer->id])
        ->postJson("/games/{$game->code}/turns/{$turn->id}/start-recording")
        ->assertForbidden();
});

test('start-recording returns 422 when turn is not in recording state', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $turn->update(['status' => 'choosing']);

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->postJson("/games/{$game->code}/turns/{$turn->id}/start-recording")
        ->assertUnprocessable();
});

test('active player can upload audio — transitions turn to grading', function () {
    Queue::fake();
    Storage::fake('local');

    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $audioFile = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->postJson("/api/games/{$game->code}/turns/{$turn->id}/audio", [
            'audio' => $audioFile,
        ])
        ->assertOk()
        ->assertJson(['status' => 'grading']);

    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'status' => 'grading',
    ]);

    $updatedTurn = $turn->fresh();
    $this->assertNotNull($updatedTurn->audio_path);
    $this->assertNotNull($updatedTurn->completed_at);

    Storage::disk('local')->assertExists($updatedTurn->audio_path);
});

test('non-active player cannot upload audio for another player\'s turn', function () {
    Storage::fake('local');

    ['game' => $game, 'turn' => $turn] = setupAudioUploadTurn();

    $otherPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $audioFile = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

    $this->withSession(["player_id.{$game->code}" => $otherPlayer->id])
        ->postJson("/api/games/{$game->code}/turns/{$turn->id}/audio", [
            'audio' => $audioFile,
        ])
        ->assertForbidden();
});

test('audio upload returns 422 when turn is not in recording state', function () {
    Storage::fake('local');

    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $turn->update(['status' => 'choosing']);

    $audioFile = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->postJson("/api/games/{$game->code}/turns/{$turn->id}/audio", [
            'audio' => $audioFile,
        ])
        ->assertUnprocessable();
});

test('audio upload requires an audio file', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->postJson("/api/games/{$game->code}/turns/{$turn->id}/audio", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');
});

test('play-state returns recordingStarted and timeRemaining when started_at is set', function () {
    ['game' => $game, 'activePlayer' => $activePlayer, 'turn' => $turn] = setupAudioUploadTurn();

    $turn->update(['started_at' => now()->subSeconds(30)]);

    $response = $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->getJson("/games/{$game->code}/play-state")
        ->assertOk();

    $response->assertJson([
        'turnStatus' => 'recording',
        'recordingStarted' => true,
    ]);

    $timeRemaining = $response->json('timeRemaining');
    expect($timeRemaining)->toBeInt()->toBeGreaterThanOrEqual(88)->toBeLessThanOrEqual(92);
});

test('play-state returns recordingStarted false when started_at is null', function () {
    ['game' => $game, 'activePlayer' => $activePlayer] = setupAudioUploadTurn();

    $this->withSession(["player_id.{$game->code}" => $activePlayer->id])
        ->getJson("/games/{$game->code}/play-state")
        ->assertOk()
        ->assertJson([
            'turnStatus' => 'recording',
            'recordingStarted' => false,
            'timeRemaining' => null,
        ]);
});
