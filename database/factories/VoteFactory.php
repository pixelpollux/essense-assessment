<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => fake()->unique()->numberBetween(1, 100000),
            'game_name' => fake()->unique()->words(2, true),
        ];
    }
}
