<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'user_id',
        'name',
        'is_host',
        'has_submitted',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'has_submitted' => 'boolean',
            'score' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'submitted_by_player_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }
}
