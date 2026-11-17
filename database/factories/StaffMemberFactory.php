<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    /**
     * Por padrão, uma professora regente sem acesso ao sistema.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '(11) 9'.fake()->numerify('####-####'),
            'role' => StaffRole::Teacher,
            'specialty' => TeacherSpecialty::Pedagogy,
            'hired_at' => fake()->dateTimeBetween('-10 years', '-1 month'),
        ];
    }

    public function specialist(TeacherSpecialty $specialty = TeacherSpecialty::English): static
    {
        return $this->state(fn (): array => ['role' => StaffRole::Teacher, 'specialty' => $specialty]);
    }

    public function role(StaffRole $role): static
    {
        return $this->state(fn (): array => [
            'role' => $role,
            'specialty' => $role === StaffRole::Teacher ? TeacherSpecialty::Pedagogy : null,
        ]);
    }

    /**
     * Cria também o login do membro da equipe, com o mesmo e-mail.
     */
    public function withLogin(): static
    {
        return $this->afterCreating(function (StaffMember $staffMember): void {
            $user = User::factory()->create(['name' => $staffMember->name, 'email' => $staffMember->email]);
            $staffMember->user()->associate($user)->save();
        });
    }
}
