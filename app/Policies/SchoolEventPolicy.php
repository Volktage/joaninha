<?php

namespace App\Policies;

use App\Models\SchoolEvent;
use App\Models\User;

/**
 * Calendário: todos consultam; Direção, Coordenação e Secretaria cadastram e alteram eventos.
 */
class SchoolEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SchoolEvent $schoolEvent): bool
    {
        return $schoolEvent->classroom_id === null
            || $user->seesAllClassrooms()
            || $schoolEvent->classroom?->isTaughtBy($user->classroomStaffProfile());
    }

    public function create(User $user): bool
    {
        return $user->canManageCalendar();
    }

    public function update(User $user, SchoolEvent $schoolEvent): bool
    {
        return $user->canManageCalendar();
    }

    public function delete(User $user, SchoolEvent $schoolEvent): bool
    {
        return $user->canManageCalendar();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCalendar();
    }
}
