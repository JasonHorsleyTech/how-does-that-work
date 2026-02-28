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
        'score' => 72.5,
        'feedback' => 'Good explanation of the basic mechanism. You covered the key points accurately.',
        'actual_explanation' => 'A zipper works by using two rows of interlocking teeth joined by a slider.',
    ]);

    ['turn' => $turn] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    $freshTurn = $turn->fresh();
    expect($freshTurn->score)->toBe(72.5);
    expect($freshTurn->grade)->toBe('C');
    expect($freshTurn->feedback)->toContain('Good explanation');
    expect($freshTurn->actual_explanation)->toContain('zipper');
    expect($freshTurn->status)->toBe('complete');
});

test('GradeTurn job increments player score with decimal values', function () {
    fakeChatResponse([
        'score' => 45.5,
        'feedback' => 'Decent attempt.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    expect($guestPlayer->fresh()->score)->toBe(45.5);
});

test('GradeTurn job increments player score on top of existing score', function () {
    fakeChatResponse([
        'score' => 34.5,
        'feedback' => 'Partial explanation.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $guestPlayer->update(['score' => 30.5]);

    $job = new GradeTurn($turn);
    $job->handle();

    expect($guestPlayer->fresh()->score)->toBe(65.0);
});

test('GradeTurn job sets game status to grading_complete', function () {
    fakeChatResponse([
        'score' => 28.5,
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
        'score' => 150.5,
        'feedback' => 'Perfect.',
        'actual_explanation' => 'A zipper works by interlocking teeth.',
    ]);

    ['turn' => $turn, 'guestPlayer' => $guestPlayer] = buildGradeTurnFixture();

    $job = new GradeTurn($turn);
    $job->handle();

    expect($turn->fresh()->score)->toBe(100.0);
    expect($guestPlayer->fresh()->score)->toBe(100.0);
});

test('GradeTurn derives correct grade from score', function () {
    expect(GradeTurn::gradeFromScore(95.5))->toBe('A');
    expect(GradeTurn::gradeFromScore(90.0))->toBe('A');
    expect(GradeTurn::gradeFromScore(85.0))->toBe('B');
    expect(GradeTurn::gradeFromScore(80.0))->toBe('B');
    expect(GradeTurn::gradeFromScore(75.0))->toBe('C');
    expect(GradeTurn::gradeFromScore(70.0))->toBe('C');
    expect(GradeTurn::gradeFromScore(65.0))->toBe('D');
    expect(GradeTurn::gradeFromScore(60.0))->toBe('D');
    expect(GradeTurn::gradeFromScore(59.9))->toBe('F');
    expect(GradeTurn::gradeFromScore(0.0))->toBe('F');
});
