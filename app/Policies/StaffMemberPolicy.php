<?php

namespace App\Policies;

use App\Models\StaffMember;
use App\Models\User;

/**
 * Equipe escolar: todos consultam (contatos e turmas); só a Direção cadastra, edita, exclui e concede acessos.
 */
class StaffMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StaffMember $staffMember): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageStaff();
    }

    public function update(User $user, StaffMember $staffMember): bool
    {
        return $user->canManageStaff();
    }

    public function delete(User $user, StaffMember $staffMember): bool
    {
        return $user->canManageStaff() && $staffMember->user_id !== $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageStaff();
    }

    public function attach(User $user, StaffMember $staffMember): bool
    {
        return $user->canManageEnrollment();
    }

    public function detach(User $user, StaffMember $staffMember): bool
    {
        return $user->canManageEnrollment();
    }
}
