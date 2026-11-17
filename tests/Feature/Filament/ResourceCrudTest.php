<?php

namespace Tests\Feature\Filament;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Filament\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Resources\Exams\Pages\CreateExam;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_classroom_can_be_created(): void
    {
        Livewire::test(CreateClassroom::class)
            ->fillForm([
                'name' => 'Pré II',
                'stage' => EducationStage::EarlyChildhood->value,
                'year' => (string) (now()->year + 1),
                'period' => ClassroomPeriod::Afternoon->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', [
            'name' => 'Pré II',
            'stage' => 'early_childhood',
            'year' => (string) (now()->year + 1),
            'period' => 'afternoon',
        ]);
    }

    public function test_classroom_requires_name_year_stage_and_period(): void
    {
        Livewire::test(CreateClassroom::class)
            ->fillForm(['name' => '', 'year' => null, 'stage' => null, 'period' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'year' => 'required', 'stage' => 'required', 'period' => 'required']);

        $this->assertDatabaseCount('classrooms', 0);
    }

    public function test_classroom_period_only_accepts_morning_or_afternoon(): void
    {
        Livewire::test(CreateClassroom::class)
            ->fillForm(['name' => '1º Ano', 'stage' => 'elementary', 'year' => (string) now()->year, 'period' => 'night'])
            ->call('create')
            ->assertHasFormErrors(['period']);
    }

    public function test_deleting_a_classroom_removes_its_students_exams_and_grades(): void
    {
        $classroom = Classroom::factory()->create();
        Student::factory()->count(2)->for($classroom)->create();
        Exam::factory()->for($classroom)->create();

        Livewire::test(EditClassroom::class, ['record' => $classroom->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertRedirect();

        $this->assertModelMissing($classroom);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('exams', 0);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_student_form_suggests_the_next_free_registration_number(): void
    {
        Student::factory()->create(['registration_number' => '20240137']);
        $expected = now()->year.'0138';

        $this->assertSame($expected, StudentForm::suggestRegistrationNumber());

        Livewire::test(CreateStudent::class)
            ->assertSchemaStateSet(['registration_number' => $expected]);
    }

    public function test_student_can_be_created_and_joins_upcoming_exams(): void
    {
        $classroom = Classroom::factory()->create();
        $upcoming = Exam::factory()->for($classroom)->scheduled()->create();
        $past = Exam::factory()->for($classroom)->create();

        Livewire::test(CreateStudent::class)
            ->fillForm(['name' => 'Helena Souza', 'registration_number' => '20260500', 'classroom_id' => $classroom->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('registration_number', '20260500')->firstOrFail();
        $this->assertSame([$upcoming->id], $student->grades()->pluck('exam_id')->all());
        $this->assertSame(0, $past->grades()->count());
    }

    public function test_student_registration_number_must_be_unique(): void
    {
        $existing = Student::factory()->create();

        Livewire::test(CreateStudent::class)
            ->fillForm(['name' => 'Outro Aluno', 'registration_number' => $existing->registration_number, 'classroom_id' => $existing->classroom_id])
            ->call('create')
            ->assertHasFormErrors(['registration_number' => 'unique']);
    }

    public function test_classroom_can_be_created_inline_from_the_student_form(): void
    {
        Livewire::test(CreateStudent::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('classroom_id'), data: [
                'name' => 'Maternal II',
                'year' => (string) now()->year,
                'stage' => EducationStage::EarlyChildhood->value,
                'period' => ClassroomPeriod::Morning->value,
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('classrooms', ['name' => 'Maternal II', 'stage' => 'early_childhood']);
    }

    public function test_moving_a_student_through_the_form_keeps_grades_already_entered(): void
    {
        [$oldClassroom, $newClassroom] = Classroom::factory()->count(2)->create();
        $student = Student::factory()->for($oldClassroom)->create();
        $gradedExam = Exam::factory()->for($oldClassroom)->create();
        $gradedExam->grades()->update(['score' => 9]);
        $newExam = Exam::factory()->for($newClassroom)->scheduled()->create();

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->fillForm(['classroom_id' => $newClassroom->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newClassroom->id, $student->fresh()->classroom_id);
        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'exam_id' => $gradedExam->id, 'score' => 9]);
        $this->assertDatabaseHas('grades', ['student_id' => $student->id, 'exam_id' => $newExam->id]);
    }

    public function test_students_can_be_bulk_deleted(): void
    {
        $students = Student::factory()->count(3)->create();

        Livewire::test(ListStudents::class)
            ->selectTableRecords($students->take(2)->modelKeys())
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertModelMissing($students[0]);
        $this->assertModelMissing($students[1]);
        $this->assertModelExists($students[2]);
    }

    public function test_exam_validation_rejects_missing_fields_and_invalid_weight(): void
    {
        $classroom = Classroom::factory()->create();

        Livewire::test(CreateExam::class)
            ->fillForm(['classroom_id' => null, 'title' => '', 'applied_at' => null, 'weight' => 50])
            ->call('create')
            ->assertHasFormErrors(['classroom_id' => 'required', 'title' => 'required', 'applied_at' => 'required', 'weight' => 'max']);

        Livewire::test(CreateExam::class)
            ->fillForm(['classroom_id' => $classroom->id, 'title' => 'Prova', 'applied_at' => today()->toDateString(), 'weight' => 0])
            ->call('create')
            ->assertHasFormErrors(['weight' => 'min']);

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_early_childhood_exam_hides_weight_and_uses_the_default(): void
    {
        $classroom = Classroom::factory()->earlyChildhood()->create();
        Student::factory()->for($classroom)->create();

        Livewire::test(CreateExam::class)
            ->fillForm(['classroom_id' => $classroom->id, 'title' => 'Relatório de Desenvolvimento – 2º Semestre', 'applied_at' => today()->addWeek()->toDateString()])
            ->assertFormFieldHidden('weight')
            ->call('create')
            ->assertHasNoFormErrors();

        $exam = Exam::query()->firstOrFail();
        $this->assertSame(1.0, $exam->weight);
        $this->assertSame(1, Grade::query()->where('exam_id', $exam->id)->count());
    }
}
