<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Turn>
 */
class TurnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'topic_id' => null,
            'round_number' => 1,
            'turn_order' => 1,
            'status' => 'pending',
            'audio_path' => null,
            'transcript' => null,
            'score' => null,
            'grade' => null,
            'feedback' => null,
            'actual_explanation' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
