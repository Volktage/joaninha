<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'classroom_id' => fn (array $attributes): int => Student::query()->findOrFail($attributes['student_id'])->classroom_id,
            'date' => today()->subDays(fake()->numberBetween(1, 30)),
            'status' => AttendanceStatus::Present,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (): array => ['status' => AttendanceStatus::Absent]);
    }
}
