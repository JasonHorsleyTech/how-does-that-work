<?php

namespace App\Jobs;

use App\Models\Turn;
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

        if (! $audioPath || ! Storage::disk('local')->exists($audioPath)) {
            $this->markFailed('Audio file not found.');

            return;
        }

        try {
            $stream = Storage::disk('local')->readStream($audioPath);

            $response = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $stream,
            ]);

            $transcript = trim($response->text ?? '');

            if ($transcript === '') {
                $this->markFailed('Whisper returned an empty transcript.');

                return;
            }

            $this->turn->update(['transcript' => $transcript]);

            dispatch(new GradeTurn($this->turn));
        } catch (Throwable $e) {
            Log::error('TranscribeAudio failed', ['turn_id' => $this->turn->id, 'error' => $e->getMessage()]);
            $this->markFailed('Transcription error: '.$e->getMessage());
        }
    }

    private function markFailed(string $message): void
    {
        $this->turn->update([
            'status' => 'grading_failed',
            'transcript' => $message,
        ]);
    }
}
