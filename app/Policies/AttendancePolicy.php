<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;

/**
 * Chamada: Direção, Coordenação e Secretaria registram em qualquer turma (ex.: atestados);
 * professores, apenas nas próprias turmas.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->takeFor($user, $attendance->classroom);
    }

    /**
     * Pode fazer ou corrigir a chamada desta turma.
     */
    public function takeFor(User $user, Classroom $classroom): bool
    {
        return $user->seesAllClassrooms() || $classroom->isTaughtBy($user->classroomStaffProfile());
    }
}
