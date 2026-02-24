<?php

namespace App\Jobs;

use App\Models\ApiUsageLog;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class GradeTurn implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly Turn $turn) {}

    public function handle(): void
    {
        $turn = $this->turn->fresh();

        if (! $turn) {
            return;
        }

        $turn->load('player', 'topic', 'game');

        $host = User::find($turn->game->host_user_id);

        if (! $host || $host->credits <= 0) {
            $turn->update(['status' => 'grading_failed']);
            $turn->game->update([
                'status' => 'grading_complete',
                'state_updated_at' => now(),
            ]);

            return;
        }

        $topicText = $turn->topic?->text ?? 'Unknown topic';
        $transcript = $turn->transcript ?? '';

        $prompt = <<<PROMPT
You are grading a player's spoken explanation of a topic in a party game called "How Does That Work?".

Topic: {$topicText}

Player's explanation (transcribed from audio):
"{$transcript}"

Grade the explanation and return ONLY a JSON object with these exact keys:
- "score": integer 0-100 (how well they explained the topic)
- "grade": string, one of "A", "B", "C", "D", or "F"
- "feedback": string, 2-4 sentences describing what they got right and wrong
- "actual_explanation": string, 3-5 sentences with an accurate explanation of the topic

Be fair but honest. A score of 100 means a perfect, accurate explanation. A score of 0 means completely wrong or no attempt.
PROMPT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '';
        $data = json_decode($content, true);

        if (
            ! is_array($data) ||
            ! isset($data['score'], $data['grade'], $data['feedback'], $data['actual_explanation'])
        ) {
            throw new RuntimeException('GPT returned malformed JSON: '.$content);
        }

        $score = max(0, min(100, (int) $data['score']));

        $turn->update([
            'score' => $score,
            'grade' => (string) $data['grade'],
            'feedback' => (string) $data['feedback'],
            'actual_explanation' => (string) $data['actual_explanation'],
            'status' => 'complete',
        ]);

        $turn->player->increment('score', $score);

        $host->decrement('credits');

        ApiUsageLog::create([
            'game_id' => $turn->game->id,
            'user_id' => $host->id,
            'type' => 'gpt',
            'tokens_used' => $response->usage?->totalTokens ?? null,
            'cost_credits' => 1,
        ]);

        $turn->game->update([
            'status' => 'grading_complete',
            'state_updated_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GradeTurn failed', ['turn_id' => $this->turn->id, 'error' => $e->getMessage()]);
        $this->turn->update(['status' => 'grading_failed']);
    }
}
