<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => null,
            'name' => fake()->firstName(),
            'is_host' => false,
            'has_submitted' => false,
            'score' => 0,
        ];
    }
}
