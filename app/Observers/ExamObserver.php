<?php

namespace App\Observers;

use App\Models\Exam;

/**
 * Mantém o diário de classe da avaliação sincronizado com os alunos da turma.
 */
class ExamObserver
{
    public function created(Exam $exam): void
    {
        $exam->createMissingGradesForClassroomStudents();
    }

    public function updated(Exam $exam): void
    {
        if (! $exam->wasChanged('classroom_id')) {
            return;
        }

        $exam->grades()->blank()->whereDoesntHave('student', function ($query) use ($exam) {
            $query->where('classroom_id', $exam->classroom_id);
        })->delete();

        $exam->createMissingGradesForClassroomStudents();
    }
}
