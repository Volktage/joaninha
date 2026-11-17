<?php

namespace Database\Factories;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['1º', '2º', '3º', '4º']).' Ano '.fake()->unique()->randomLetter(),
            'stage' => EducationStage::Elementary,
            'year' => (string) now()->year,
            'period' => fake()->randomElement(ClassroomPeriod::cases()),
        ];
    }

    /**
     * Turma da Educação Infantil, avaliada por relatório descritivo.
     */
    public function earlyChildhood(): static
    {
        return $this->state(fn (): array => [
            'name' => fake()->randomElement(['Maternal I', 'Maternal II', 'Pré I', 'Pré II']),
            'stage' => EducationStage::EarlyChildhood,
        ]);
    }
}
