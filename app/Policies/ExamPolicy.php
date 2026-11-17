<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

/**
 * Avaliações: Direção e Coordenação gerem todas; professores criam nas próprias turmas e editam
 * as que aplicaram (ou as da turma em que são regentes); a Secretaria apenas consulta.
 */
class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Exam $exam): bool
    {
        return $user->seesAllClassrooms() || $exam->classroom->isTaughtBy($user->classroomStaffProfile());
    }

    public function create(User $user): bool
    {
        return $user->canManageAllAssessments() || $user->teacherProfile() !== null;
    }

    public function update(User $user, Exam $exam): bool
    {
        if ($user->canManageAllAssessments()) {
            return true;
        }

        $teacher = $user->teacherProfile();

        return $teacher !== null
            && ($exam->teacher_id === $teacher->id || $exam->classroom->homeroom_teacher_id === $teacher->id);
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $this->update($user, $exam);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageAllAssessments();
    }
}
