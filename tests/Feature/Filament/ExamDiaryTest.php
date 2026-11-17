<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Pages\CreateExam;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\RelationManagers\GradesRelationManager;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExamDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_creating_an_exam_builds_the_diary_and_opens_it(): void
    {
        $classroom = Classroom::factory()->create();
        Student::factory()->count(3)->for($classroom)->create();

        Livewire::test(CreateExam::class)
            ->fillForm([
                'classroom_id' => $classroom->id,
                'title' => 'Avaliação de Matemática – 4º Bimestre',
                'applied_at' => today()->toDateString(),
                'weight' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ExamResource::getUrl('edit', ['record' => Exam::query()->latest('id')->first(), 'relation' => '0']));

        $exam = Exam::query()->latest('id')->first();
        $this->assertSame(2.0, $exam->weight);
        $this->assertSame(3, $exam->grades()->count());
    }

    public function test_teacher_can_type_a_score_directly_in_the_diary(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->for($classroom)->create();
        $exam = Exam::factory()->for($classroom)->create();
        $grade = $exam->grades()->where('student_id', $student->id)->firstOrFail();

        Livewire::test(GradesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
            ->assertSuccessful()
            ->assertTableColumnExists('score')
            ->call('updateTableColumnState', 'score', (string) $grade->id, '7.5');

        $this->assertSame(7.5, $grade->fresh()->score);
    }

    public function test_scores_above_ten_are_rejected(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->for($classroom)->create();
        $exam = Exam::factory()->for($classroom)->create();
        $grade = $exam->grades()->where('student_id', $student->id)->firstOrFail();

        Livewire::test(GradesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
            ->call('updateTableColumnState', 'score', (string) $grade->id, '11');

        $this->assertNull($grade->fresh()->score);
    }

    public function test_early_childhood_diary_has_reports_instead_of_scores(): void
    {
        $classroom = Classroom::factory()->earlyChildhood()->create();
        $student = Student::factory()->for($classroom)->create();
        $exam = Exam::factory()->for($classroom)->create();
        $grade = $exam->grades()->where('student_id', $student->id)->firstOrFail();

        Livewire::test(GradesRelationManager::class, ['ownerRecord' => $exam, 'pageClass' => EditExam::class])
            ->assertSuccessful()
            ->assertTableColumnDoesNotExist('score')
            ->assertTableColumnExists('comments')
            ->callTableAction('edit', $grade, data: ['comments' => 'Reconta histórias respeitando a sequência dos fatos.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Reconta histórias respeitando a sequência dos fatos.', $grade->fresh()->comments);
        $this->assertNull($grade->fresh()->score);
    }
}
