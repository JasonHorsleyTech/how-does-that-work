<?php

use App\Jobs\GradeTurn;
use App\Jobs\TranscribeAudio;
use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\TranscriptionResponse;

uses(RefreshDatabase::class);

function makeGradingTurn(): array
{
    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
        'current_round' => 1,
    ]);

    $hostPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a zipper work?',
        'is_used' => true,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'grading',
    ]);

    $turn->update(['audio_path' => "audio/{$game->code}/{$turn->id}.webm"]);

    return compact('game', 'guestPlayer', 'turn');
}

test('uploading audio dispatches TranscribeAudio job', function () {
    Queue::fake();
    Storage::fake('local');

    $host = User::factory()->create();
    $game = Game::factory()->create([
        'host_user_id' => $host->id,
        'status' => 'playing',
    ]);

    Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => $host->id,
        'is_host' => true,
    ]);

    $guestPlayer = Player::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'is_host' => false,
    ]);

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $guestPlayer->id,
        'is_used' => true,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'recording',
    ]);

    $audioFile = UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');

    $this->withSession(["player_id.{$game->code}" => $guestPlayer->id])
        ->postJson("/api/games/{$game->code}/turns/{$turn->id}/audio", [
            'audio' => $audioFile,
        ])
        ->assertOk()
        ->assertJson(['status' => 'grading']);

    Queue::assertPushed(TranscribeAudio::class, function ($job) use ($turn) {
        return $job->turn->id === $turn->id;
    });
});

test('TranscribeAudio job stores transcript and dispatches GradeTurn on success', function () {
    Queue::fake();
    Storage::fake('local');

    OpenAI::fake([
        TranscriptionResponse::fake(['text' => 'A zipper works by interlocking teeth.']),
    ]);

    ['turn' => $turn, 'game' => $game] = makeGradingTurn();

    // Put a fake audio file in storage
    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    $job = new TranscribeAudio($turn);
    $job->handle();

    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'transcript' => 'A zipper works by interlocking teeth.',
    ]);

    Queue::assertPushed(GradeTurn::class, function ($job) use ($turn) {
        return $job->turn->id === $turn->id;
    });
});

test('TranscribeAudio job sets grading_failed when Whisper returns empty transcript', function () {
    Queue::fake();
    Storage::fake('local');

    OpenAI::fake([
        TranscriptionResponse::fake(['text' => '']),
    ]);

    ['turn' => $turn] = makeGradingTurn();

    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    $job = new TranscribeAudio($turn);
    $job->handle();

    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'status' => 'grading_failed',
    ]);

    Queue::assertNotPushed(GradeTurn::class);
});

test('TranscribeAudio job sets grading_failed when Whisper throws an exception', function () {
    Queue::fake();
    Storage::fake('local');

    OpenAI::fake([
        new \Exception('API error'),
    ]);

    ['turn' => $turn] = makeGradingTurn();

    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    $job = new TranscribeAudio($turn);
    $job->handle();

    $freshTurn = $turn->fresh();
    expect($freshTurn->status)->toBe('grading_failed');

    Queue::assertNotPushed(GradeTurn::class);
});

test('TranscribeAudio job sets grading_failed when audio file is missing from storage', function () {
    Queue::fake();
    Storage::fake('local');

    ['turn' => $turn] = makeGradingTurn();

    // Do NOT put the file in storage — simulate missing file

    $job = new TranscribeAudio($turn);
    $job->handle();

    $this->assertDatabaseHas('turns', [
        'id' => $turn->id,
        'status' => 'grading_failed',
    ]);

    Queue::assertNotPushed(GradeTurn::class);
});
