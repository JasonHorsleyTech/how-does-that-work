<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineLog extends Model
{
    protected $fillable = [
        'turn_id',
        'status',
        'audio_received_at',
        'audio_file_path',
        'audio_file_size_bytes',
        'whisper_sent_at',
        'whisper_response_at',
        'whisper_transcript',
        'whisper_error',
        'gpt_sent_at',
        'gpt_response_at',
        'gpt_response_raw',
        'gpt_error',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'audio_received_at' => 'datetime',
            'whisper_sent_at' => 'datetime',
            'whisper_response_at' => 'datetime',
            'gpt_sent_at' => 'datetime',
            'gpt_response_at' => 'datetime',
            'audio_file_size_bytes' => 'integer',
        ];
    }

    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }
}
