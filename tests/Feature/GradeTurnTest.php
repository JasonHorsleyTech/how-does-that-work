<?php

use App\Jobs\GradeTurn;
use App\Models\Game;
use App\Models\Player;
use App\Models\Topic;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

function buildGradeTurnFixture(): array
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
        'score' => 0,
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
        'transcript' => 'A zipper works by interlocking tiny teeth on two strips of fabric.',
    ]);

    return compact('game', 'hostPlayer', 'guestPlayer', 'topic', 'turn');
}

function fakeChatResponse(array $fields): void
{
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode($fields),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);
}

test('GradeTurn job stores all grading fields on the turn', function () {
    fakeChatResponse([
        'score' => 80,
        'grade' => 'B',
        'feedback' => 'Good explanation of the basic mechanism. You covered the key points accurately.',
        'actual_explanation' => 'A zipper works by using two rows of interlocking teeth joined by a slider.',
    ]);

    ['turn' => $turn] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    $freshTurn = $turn->fresh();
    expect($freshTurn->score)->toBe(80);
    expect($freshTurn->grade)->toBe('B');
    expect($freshTurn->feedback)->toContain('Good explanation');
    expect($freshTurn->actual_explanation)->toContain('zipper');
    expect($freshTurn->status)->toBe('complete');
});

test('GradeTurn job increments player score', function () {
    fakeChatResponse([
        'score' => 75,
        'grade' => 'C',
        'feedback' => 'Decent attempt.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    expect($guestPlayer->fresh()->score)->toBe(75);
});

test('GradeTurn job increments player score on top of existing score', function () {
    fakeChatResponse([
        'score' => 50,
        'grade' => 'C',
        'feedback' => 'Partial explanation.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $guestPlayer->update(['score' => 30]);

    $job = new GradeTurn($turn);
    $job->handle();

    expect($guestPlayer->fresh()->score)->toBe(80);
});

test('GradeTurn job sets game status to grading_complete', function () {
    fakeChatResponse([
        'score' => 60,
        'grade' => 'D',
        'feedback' => 'Missed most key points.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'game' => $game] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    expect($game->fresh()->status)->toBe('grading_complete');
});

test('GradeTurn job throws exception when GPT returns malformed JSON', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'this is not json at all',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    ['turn' => $turn] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

test('GradeTurn job throws exception when JSON is missing required keys', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['score' => 80]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    ['turn' => $turn] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

test('GradeTurn failed() sets turn status to grading_failed', function () {
    ['turn' => $turn] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->failed(new RuntimeException('GPT returned malformed JSON after 3 attempts'));

    expect($turn->fresh()->status)->toBe('grading_failed');
});

test('GradeTurn job clamps score to 0-100 range', function () {
    fakeChatResponse([
        'score' => 150,
        'grade' => 'A',
        'feedback' => 'Perfect.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    expect($turn->fresh()->score)->toBe(100);
    expect($guestPlayer->fresh()->score)->toBe(100);
});
