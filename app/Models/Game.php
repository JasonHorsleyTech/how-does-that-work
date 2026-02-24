<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_user_id',
        'code',
        'status',
        'current_round',
        'max_rounds',
        'state_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'state_updated_at' => 'datetime',
            'current_round' => 'integer',
            'max_rounds' => 'integer',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class);
    }
}
