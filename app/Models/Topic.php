<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'submitted_by_player_id',
        'text',
        'is_used',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'submitted_by_player_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }
}
