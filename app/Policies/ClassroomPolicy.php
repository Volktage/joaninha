<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

/**
 * Turmas: todos consultam (professores, só as próprias); Direção, Coordenação e Secretaria cadastram;
 * apenas a Direção exclui, pois a exclusão leva junto alunos, avaliações e notas.
 */
class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $user->seesAllClassrooms() || $classroom->isTaughtBy($user->classroomStaffProfile());
    }

    public function create(User $user): bool
    {
        return $user->canManageEnrollment();
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->canManageEnrollment();
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->hasFullAccess();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasFullAccess();
    }
}
