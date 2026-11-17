<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

/**
 * Lançamentos do diário seguem a avaliação: quem pode editar a avaliação pode lançar notas e relatórios.
 * As linhas do diário são criadas e removidas automaticamente, nunca à mão.
 */
class GradePolicy
{
    public function __construct(private ExamPolicy $examPolicy) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Grade $grade): bool
    {
        return $this->examPolicy->view($user, $grade->exam);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Grade $grade): bool
    {
        return $this->examPolicy->update($user, $grade->exam);
    }

    public function delete(User $user, Grade $grade): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
