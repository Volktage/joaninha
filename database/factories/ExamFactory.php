<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'title' => 'Avaliação de '.fake()->randomElement(['Português', 'Matemática', 'Ciências']),
            'weight' => 1.0,
            'applied_at' => today()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    /**
     * Avaliação ainda por aplicar.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'applied_at' => today()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
