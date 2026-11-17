<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingCalculationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_average_uses_exam_weights_and_ignores_missing_scores(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->for($classroom)->create();
        $exams = [
            [Exam::factory()->for($classroom)->create(['weight' => 2]), 8.0],
            [Exam::factory()->for($classroom)->create(['weight' => 1]), 5.0],
            [Exam::factory()->for($classroom)->create(['weight' => 3]), null],
        ];

        foreach ($exams as [$exam, $score]) {
            $exam->grades()->where('student_id', $student->id)->update(['score' => $score]);
        }

        $this->assertSame(7.0, $student->weightedAverage());
        $this->assertEquals(7.0, Student::query()->withWeightedAverage()->find($student->id)->weighted_average);
    }

    public function test_weighted_average_is_null_without_scores(): void
    {
        $student = Student::factory()->create();

        $this->assertNull($student->weightedAverage());
    }

    public function test_exam_status_follows_date_and_diary_completion(): void
    {
        $classroom = Classroom::factory()->create();
        $students = Student::factory()->count(2)->for($classroom)->create();

        $scheduled = Exam::factory()->for($classroom)->scheduled()->create();
        $awaiting = Exam::factory()->for($classroom)->create();
        $completed = Exam::factory()->for($classroom)->create();
        $completed->grades()->where('student_id', $students[0]->id)->update(['score' => 9]);
        $completed->grades()->where('student_id', $students[1]->id)->update(['comments' => 'Faltou – atestado médico apresentado.']);

        $this->assertSame(ExamStatus::Scheduled, $scheduled->status());
        $this->assertSame(ExamStatus::AwaitingGrades, $awaiting->status());
        $this->assertSame(ExamStatus::Completed, $completed->status());
        $this->assertSame([$scheduled->id], Exam::query()->scheduled()->pluck('id')->all());
        $this->assertSame([$awaiting->id], Exam::query()->awaitingGrades()->pluck('id')->all());
        $this->assertSame([$completed->id], Exam::query()->gradesCompleted()->pluck('id')->all());
        $this->assertSame('2/2', Exam::query()->withGradingProgress()->find($completed->id)->gradingProgressLabel());
    }

    public function test_early_childhood_reports_complete_the_diary_without_scores(): void
    {
        $classroom = Classroom::factory()->earlyChildhood()->create();
        $student = Student::factory()->for($classroom)->create();
        $report = Exam::factory()->for($classroom)->create();

        $report->grades()->where('student_id', $student->id)->update(['comments' => 'Participa das rodas de conversa com entusiasmo.']);

        $this->assertFalse($classroom->isGraded());
        $this->assertSame(ExamStatus::Completed, $report->status());
    }

    public function test_pending_grades_only_count_blank_rows_of_exams_already_applied(): void
    {
        $classroom = Classroom::factory()->create();
        Student::factory()->count(2)->for($classroom)->create();
        Exam::factory()->for($classroom)->create();
        Exam::factory()->for($classroom)->scheduled()->create();

        $this->assertSame(2, Grade::query()->pending()->count());
        $this->assertSame(4, Grade::query()->blank()->count());
    }

    public function test_score_colors_follow_the_school_thresholds(): void
    {
        $this->assertSame('gray', Grade::colorForScore(null));
        $this->assertSame('danger', Grade::colorForScore(4.9));
        $this->assertSame('warning', Grade::colorForScore(5.0));
        $this->assertSame('warning', Grade::colorForScore(6.9));
        $this->assertSame('success', Grade::colorForScore(7.0));
        $this->assertSame('7,5', Grade::formatScore(7.5));
        $this->assertSame('—', Grade::formatScore(null));
    }
}
