<?php

namespace App\Observers;

use App\Models\Student;

/**
 * Mantém as linhas do diário de classe do aluno alinhadas com as avaliações da turma em que ele está.
 */
class StudentObserver
{
    public function created(Student $student): void
    {
        $student->createMissingGradesForClassroomExams();
    }

    public function updated(Student $student): void
    {
        if (! $student->wasChanged('classroom_id')) {
            return;
        }

        $student->grades()->blank()->whereDoesntHave('exam', function ($query) use ($student) {
            $query->where('classroom_id', $student->classroom_id);
        })->delete();

        $student->createMissingGradesForClassroomExams();
    }
}
