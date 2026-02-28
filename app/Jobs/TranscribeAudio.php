<?php

namespace App\Jobs;

use App\Models\ApiUsageLog;
use App\Models\PipelineLog;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class TranscribeAudio implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Turn $turn) {}

    public function handle(): void
    {
        $audioPath = $this->turn->audio_path;
        $pipelineLog = PipelineLog::where('turn_id', $this->turn->id)->first();

        if (! $audioPath || ! Storage::disk('local')->exists($audioPath)) {
            $this->markFailed('Audio file not found.', $pipelineLog);

            return;
        }

        $this->turn->load('game');
        $host = User::find($this->turn->game->host_user_id);

        if (! $host || $host->credits <= 0) {
            $this->markFailed('Host ran out of credits.', $pipelineLog);

            return;
        }

        try {
            $stream = Storage::disk('local')->readStream($audioPath);

            $pipelineLog?->update([
                'status' => 'whisper_sending',
                'whisper_sent_at' => now(),
            ]);

            $response = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $stream,
            ]);

            $transcript = trim($response->text ?? '');

            $pipelineLog?->update([
                'whisper_response_at' => now(),
                'whisper_transcript' => $transcript,
            ]);

            if ($transcript === '') {
                $this->markFailed('Whisper returned an empty transcript.', $pipelineLog, 'Whisper returned an empty transcript.');

                return;
            }

            $pipelineLog?->update(['status' => 'whisper_complete']);

            $this->turn->update(['transcript' => $transcript]);

            $host->decrement('credits');

            ApiUsageLog::create([
                'game_id' => $this->turn->game->id,
                'user_id' => $host->id,
                'type' => 'whisper',
                'tokens_used' => null,
                'cost_credits' => 1,
            ]);

            dispatch(new GradeTurn($this->turn));
        } catch (Throwable $e) {
            Log::error('TranscribeAudio failed', ['turn_id' => $this->turn->id, 'error' => $e->getMessage()]);
            $this->markFailed('Transcription error: '.$e->getMessage(), $pipelineLog, $e->getMessage());
        }
    }

    private function markFailed(string $message, ?PipelineLog $pipelineLog, ?string $whisperError = null): void
    {
        $this->turn->update([
            'status' => 'grading_failed',
            'transcript' => $message,
        ]);

        $pipelineLog?->update([
            'status' => 'whisper_failed',
            'whisper_error' => $whisperError ?? $message,
            'whisper_response_at' => $pipelineLog->whisper_sent_at ? now() : null,
            'error_message' => $message,
        ]);
    }
}
