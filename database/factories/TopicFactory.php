<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'submitted_by_player_id' => Player::factory(),
            'text' => fake()->sentence(6),
            'is_used' => false,
        ];
    }
}
