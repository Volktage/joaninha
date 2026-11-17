<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Student;
use App\Models\User;

/**
 * Alunos: professores consultam os das próprias turmas; matrícula e cadastro ficam com
 * Direção, Coordenação e Secretaria; excluir, só Direção e Secretaria.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Student $student): bool
    {
        return $user->seesAllClassrooms() || $student->classroom->isTaughtBy($user->classroomStaffProfile());
    }

    public function create(User $user): bool
    {
        return $user->canManageEnrollment();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->canManageEnrollment();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasFullAccess() || $user->hasRole(StaffRole::Secretary);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasFullAccess() || $user->hasRole(StaffRole::Secretary);
    }
}
