<?php

namespace Database\Factories;

use App\Enums\ActionType;
use App\Models\DailyAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyAction>
 */
class DailyActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'local_date' => now()->toDateString(),
            'type' => ActionType::Vote,
            'game_id' => fake()->numberBetween(1, 100000),
            'game_name' => fake()->words(2, true),
        ];
    }
}
