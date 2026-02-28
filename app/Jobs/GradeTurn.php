<?php

namespace App\Jobs;

use App\Models\ApiUsageLog;
use App\Models\PipelineLog;
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
        $pipelineLog = PipelineLog::where('turn_id', $turn->id)->first();

        $host = User::find($turn->game->host_user_id);

        if (! $host || $host->credits <= 0) {
            $turn->update(['status' => 'grading_failed']);
            $pipelineLog?->update([
                'status' => 'gpt_failed',
                'gpt_error' => 'Host ran out of credits.',
                'error_message' => 'Host ran out of credits.',
            ]);
            $turn->game->update([
                'status' => 'grading_complete',
                'state_updated_at' => now(),
            ]);

            return;
        }

        $topicText = $turn->topic?->text ?? 'Unknown topic';
        $transcript = $turn->transcript ?? '';

        $prompt = <<<PROMPT
You are the roast-master judge in a party game called "How Does That Work?" where players try to explain everyday things they definitely should understand but clearly don't.

Topic: {$topicText}

Player's explanation (transcribed from speech):
"{$transcript}"

## Your job

Grade this explanation and return ONLY a JSON object with these exact keys:

- "score": number 0-100 with up to one decimal place (e.g. 34.5, 72.8, 91.0)
- "feedback": string, 3-5 sentences in a dry roast tone (see below)
- "actual_explanation": string, 3-5 sentences — factual, helpful, no humor. This is the "learn something" moment.

## Scoring rules

Use the FULL 0-100 range. Do NOT cluster scores around 60-80.

- 0-15: Complete nonsense, gave up, or just made weird noises. Single-digit scores are encouraged here.
- 15-35: Wildly wrong but at least they tried. Confident incorrectness lives here.
- 35-55: Got the vibe but most details are wrong or missing.
- 55-70: Decent attempt — some right ideas mixed with some creative fiction.
- 70-85: Genuinely solid. Got the core mechanism right with minor gaps.
- 85-95: Impressive. Accurate, detailed, would pass a pop quiz.
- 95-100: Basically a professor. Reserve this for explanations that are genuinely excellent.

Most casual explanations should land between 20-60. An average rambling attempt is a 35, not a 65. Be harsh but fair.

## Feedback tone — dry roast

Write like a close friend who loves making fun of you. Dry, not aggressively sarcastic. Specific, not generic.

Rules:
- Quote at least one specific thing the player said and roast it. Use their actual words in quotes.
- Point out the funniest logical leaps or confident wrongness.
- If they nailed it, acknowledge it — you can still be funny, but respect the achievement.
- 3-5 sentences. No filler.
- Do NOT be mean-spirited. Think "your friend who happens to be a genius" energy.

## actual_explanation

- 3-5 sentences explaining how the topic ACTUALLY works.
- Factual, clear, genuinely educational. No jokes here.
- This is the payoff — the player should learn something real.

Return ONLY the JSON object, nothing else.
PROMPT;

        $pipelineLog?->update([
            'status' => 'gpt_sending',
            'gpt_sent_at' => now(),
        ]);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '';

        $pipelineLog?->update([
            'gpt_response_at' => now(),
            'gpt_response_raw' => $content,
        ]);

        $data = json_decode($content, true);

        if (
            ! is_array($data) ||
            ! isset($data['score'], $data['feedback'], $data['actual_explanation'])
        ) {
            $pipelineLog?->update([
                'gpt_error' => 'Malformed JSON: '.$content,
            ]);
            throw new RuntimeException('GPT returned malformed JSON: '.$content);
        }

        $score = round(max(0, min(100, (float) $data['score'])), 1);
        $grade = self::gradeFromScore($score);

        $turn->update([
            'score' => $score,
            'grade' => $grade,
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

        $pipelineLog?->update(['status' => 'complete']);

        $turn->game->update([
            'status' => 'grading_complete',
            'state_updated_at' => now(),
        ]);
    }

    public static function gradeFromScore(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    public function failed(Throwable $e): void
    {
        Log::error('GradeTurn failed', ['turn_id' => $this->turn->id, 'error' => $e->getMessage()]);
        $this->turn->update(['status' => 'grading_failed']);

        $pipelineLog = PipelineLog::where('turn_id', $this->turn->id)->first();
        $pipelineLog?->update([
            'status' => 'gpt_failed',
            'gpt_error' => $pipelineLog->gpt_error ?? $e->getMessage(),
            'error_message' => 'GradeTurn failed after '.$this->tries.' attempts: '.$e->getMessage(),
        ]);
    }
}
