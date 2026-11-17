<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O diário de classe (linhas em `grades`) deve acompanhar automaticamente alunos e avaliações da turma.
 */
class GradeSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_exam_adds_a_blank_grade_for_every_student_in_the_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $students = Student::factory()->count(3)->for($classroom)->create();
        Student::factory()->create();

        $exam = Exam::factory()->for($classroom)->create();

        $this->assertEqualsCanonicalizing($students->modelKeys(), $exam->grades()->pluck('student_id')->all());
        $this->assertSame(3, $exam->grades()->blank()->count());
    }

    public function test_a_new_student_joins_only_the_diary_of_exams_from_today_onwards(): void
    {
        $classroom = Classroom::factory()->create();
        $pastExam = Exam::factory()->for($classroom)->create(['applied_at' => today()->subWeek()]);
        $todayExam = Exam::factory()->for($classroom)->create(['applied_at' => today()]);
        $futureExam = Exam::factory()->for($classroom)->scheduled()->create();

        $student = Student::factory()->for($classroom)->create();

        $this->assertEqualsCanonicalizing(
            [$todayExam->id, $futureExam->id],
            $student->grades()->pluck('exam_id')->all(),
        );
        $this->assertSame(0, $pastExam->grades()->count());
    }

    public function test_moving_a_student_keeps_filled_grades_and_replaces_blank_ones(): void
    {
        [$oldClassroom, $newClassroom] = Classroom::factory()->count(2)->create();
        $student = Student::factory()->for($oldClassroom)->create();
        $gradedExam = Exam::factory()->for($oldClassroom)->create();
        $blankExam = Exam::factory()->for($oldClassroom)->scheduled()->create();
        $newClassroomExam = Exam::factory()->for($newClassroom)->scheduled()->create();
        $gradedExam->grades()->where('student_id', $student->id)->update(['score' => 8.5]);

        $student->update(['classroom_id' => $newClassroom->id]);

        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'exam_id' => $gradedExam->id, 'score' => 8.5]);
        $this->assertDatabaseMissing('grades', ['student_id' => $student->id, 'exam_id' => $blankExam->id]);
        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'exam_id' => $newClassroomExam->id, 'score' => null]);
    }

    public function test_moving_an_exam_to_another_classroom_rebuilds_its_diary(): void
    {
        [$oldClassroom, $newClassroom] = Classroom::factory()->count(2)->create();
        $oldStudent = Student::factory()->for($oldClassroom)->create();
        $newStudents = Student::factory()->count(2)->for($newClassroom)->create();
        $exam = Exam::factory()->for($oldClassroom)->create();

        $exam->update(['classroom_id' => $newClassroom->id]);

        $this->assertEqualsCanonicalizing($newStudents->modelKeys(), $exam->grades()->pluck('student_id')->all());
        $this->assertDatabaseMissing('grades', ['student_id' => $oldStudent->id, 'exam_id' => $exam->id]);
    }

    public function test_a_student_cannot_have_two_grades_for_the_same_exam(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->for($classroom)->create();
        $exam = Exam::factory()->for($classroom)->create();

        $this->expectException(QueryException::class);

        Grade::query()->create(['student_id' => $student->id, 'exam_id' => $exam->id, 'score' => 7]);
    }
}
