<?php

namespace Database\Factories;

use App\Enums\SchoolEventType;
use App\Models\SchoolEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolEvent>
 */
class SchoolEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Reunião de pais', 'Festa da Família', 'Mostra cultural']),
            'description' => null,
            'type' => SchoolEventType::ParentsMeeting,
            'starts_on' => today()->addDays(fake()->numberBetween(1, 30)),
            'ends_on' => null,
            'classroom_id' => null,
        ];
    }

    public function holiday(): static
    {
        return $this->state(fn (): array => ['title' => 'Feriado', 'type' => SchoolEventType::Holiday]);
    }
}
