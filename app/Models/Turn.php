<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Turn extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_id',
        'topic_id',
        'round_number',
        'turn_order',
        'status',
        'audio_path',
        'transcript',
        'score',
        'grade',
        'feedback',
        'actual_explanation',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'turn_order' => 'integer',
            'score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
