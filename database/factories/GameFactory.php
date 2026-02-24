<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_user_id' => User::factory(),
            'code' => strtoupper(Str::random(6)),
            'status' => 'lobby',
            'current_round' => 1,
            'max_rounds' => 1,
            'state_updated_at' => now(),
        ];
    }
}
