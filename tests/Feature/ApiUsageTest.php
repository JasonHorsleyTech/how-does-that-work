<?php

use App\Jobs\GradeTurn;
use App\Jobs\TranscribeAudio;
use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

function makeApiUsageFixture(): array
{
    $host = User::factory()->create(['credits' => 5]);
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
        'score' => 0,
    ]);

    $topic = Topic::factory()->create([
        'game_id' => $game->id,
        'submitted_by_player_id' => $hostPlayer->id,
        'text' => 'How does a microwave work?',
        'is_used' => true,
    ]);

    $turn = Turn::factory()->create([
        'game_id' => $game->id,
        'player_id' => $guestPlayer->id,
        'topic_id' => $topic->id,
        'round_number' => 1,
        'turn_order' => 1,
        'status' => 'grading',
        'transcript' => 'A microwave uses radiation to heat food.',
    ]);

    return compact('host', 'game', 'guestPlayer', 'topic', 'turn');
}

// ─── GradeTurn credit deduction ─────────────────────────────────────────────

test('GradeTurn job deducts one credit from host on success', function () {
    ['host' => $host, 'turn' => $turn] = makeApiUsageFixture();

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'score' => 70,
                        'grade' => 'C',
                        'feedback' => 'Decent attempt.',
                        'actual_explanation' => 'A microwave heats food using microwaves.',
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
        ]),
    ]);

    (new GradeTurn($turn))->handle();

    expect($host->fresh()->credits)->toBe(4);
});

test('GradeTurn job creates an api_usage_log record on success', function () {
    ['host' => $host, 'game' => $game, 'turn' => $turn] = makeApiUsageFixture();

    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'score' => 70,
                        'grade' => 'C',
                        'feedback' => 'Decent attempt.',
                        'actual_explanation' => 'A microwave heats food using microwaves.',
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
        ]),
    ]);

    (new GradeTurn($turn))->handle();

    $this->assertDatabaseHas('api_usage_logs', [
        'game_id' => $game->id,
        'user_id' => $host->id,
        'type' => 'gpt',
        'cost_credits' => 1,
    ]);
});

test('GradeTurn job skips grading and marks turn failed when host has no credits', function () {
    ['turn' => $turn] = makeApiUsageFixture();

    $turn->game->host->update(['credits' => 0]);

    (new GradeTurn($turn))->handle();

    expect($turn->fresh()->status)->toBe('grading_failed');
    $this->assertDatabaseMissing('api_usage_logs', ['type' => 'gpt']);
});

test('GradeTurn job does not deduct credits below zero', function () {
    ['host' => $host, 'turn' => $turn] = makeApiUsageFixture();
    $host->update(['credits' => 0]);

    (new GradeTurn($turn))->handle();

    expect($host->fresh()->credits)->toBe(0);
});

// ─── TranscribeAudio credit deduction ───────────────────────────────────────

test('TranscribeAudio job deducts one credit from host on success', function () {
    Queue::fake();
    Storage::fake('local');

    ['host' => $host, 'turn' => $turn] = makeApiUsageFixture();

    $turn->update(['audio_path' => "audio/GAME01/{$turn->id}.webm", 'status' => 'grading']);
    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    OpenAI::fake([
        TranscriptionResponse::fake(['text' => 'A microwave uses radiation to heat food.']),
    ]);

    (new TranscribeAudio($turn))->handle();

    expect($host->fresh()->credits)->toBe(4);
});

test('TranscribeAudio job creates an api_usage_log record on success', function () {
    Queue::fake();
    Storage::fake('local');

    ['host' => $host, 'game' => $game, 'turn' => $turn] = makeApiUsageFixture();

    $turn->update(['audio_path' => "audio/GAME01/{$turn->id}.webm", 'status' => 'grading']);
    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    OpenAI::fake([
        TranscriptionResponse::fake(['text' => 'A microwave uses radiation to heat food.']),
    ]);

    (new TranscribeAudio($turn))->handle();

    $this->assertDatabaseHas('api_usage_logs', [
        'game_id' => $game->id,
        'user_id' => $host->id,
        'type' => 'whisper',
        'cost_credits' => 1,
    ]);
});

test('TranscribeAudio job skips transcription and marks turn failed when host has no credits', function () {
    Queue::fake();
    Storage::fake('local');

    ['host' => $host, 'turn' => $turn] = makeApiUsageFixture();
    $host->update(['credits' => 0]);

    $turn->update(['audio_path' => "audio/GAME01/{$turn->id}.webm"]);
    Storage::disk('local')->put($turn->audio_path, 'fake audio content');

    (new TranscribeAudio($turn))->handle();

    expect($turn->fresh()->status)->toBe('grading_failed');
    $this->assertDatabaseMissing('api_usage_logs', ['type' => 'whisper']);
    Queue::assertNotPushed(GradeTurn::class);
});
