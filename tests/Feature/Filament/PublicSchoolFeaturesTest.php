<?php

namespace Tests\Feature\Filament;

use App\Enums\SpecialEducationNeed;
use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Elementos de escola pública: vice-direção, auxiliares de classe e Educação Especial (AEE).
 */
class PublicSchoolFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vice_direction_has_full_access_including_staff_management(): void
    {
        $vice = StaffMember::factory()->role(StaffRole::ViceDirection)->withLogin()->create()->user;

        $this->assertTrue($vice->hasFullAccess());
        $this->assertTrue($vice->canManageStaff());
        $this->assertSame('Vice-direção', $vice->roleLabel());
    }

    public function test_assistant_sees_only_assisted_classroom_and_can_take_attendance_but_not_grade(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $assistant = StaffMember::factory()->role(StaffRole::Assistant)->withLogin()->create();
        $classroom = Classroom::factory()->earlyChildhood()->create();
        $other = Classroom::factory()->create();
        $classroom->assistants()->attach($assistant);
        $student = Student::factory()->for($classroom)->create(['allergies' => 'Lactose']);
        $exam = Exam::factory()->for($classroom)->create();
        $this->actingAs($assistant->user);

        Livewire::test(ListClassrooms::class)
            ->assertCanSeeTableRecords([$classroom])
            ->assertCanNotSeeTableRecords([$other]);

        $this->get("/admin/students/{$student->id}/edit")->assertOk()->assertSee('Alergia: Lactose.');
        $this->assertFalse($assistant->user->can('update', $exam));
        $this->assertFalse($assistant->user->can('create', Exam::class));

        Livewire::test(TakeAttendance::class, ['classroomId' => $classroom->id, 'date' => '2026-11-11'])
            ->call('save')
            ->assertNotified('Chamada salva!');

        $this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'recorded_by' => $assistant->user_id]);
    }

    public function test_classroom_form_saves_assistants_without_touching_specialists(): void
    {
        $this->actingAs(User::factory()->create());
        $assistant = StaffMember::factory()->role(StaffRole::Assistant)->create();
        $english = StaffMember::factory()->specialist(TeacherSpecialty::English)->create();

        Livewire::test(CreateClassroom::class)
            ->fillForm([
                'name' => 'Maternal I C',
                'stage' => 'early_childhood',
                'year' => (string) now()->year,
                'period' => 'morning',
                'teachers' => [$english->id],
                'assistants' => [$assistant->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $classroom = Classroom::query()->where('name', 'Maternal I C')->firstOrFail();
        $this->assertSame([$assistant->id], $classroom->assistants()->pluck('staff_members.id')->all());
        $this->assertSame([$english->id], $classroom->teachers()->pluck('staff_members.id')->all());
        $this->assertSame([$classroom->id], $assistant->fresh()->allClassrooms()->modelKeys());
    }

    public function test_special_education_student_is_registered_highlighted_and_filterable(): void
    {
        $this->actingAs(User::factory()->create());
        $classroom = Classroom::factory()->create();

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'name' => 'Davi Cardoso',
                'registration_number' => '20260950',
                'classroom_id' => $classroom->id,
                'special_education' => SpecialEducationNeed::Autism->value,
                'special_education_notes' => 'Antecipar mudanças na rotina com apoio visual.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('registration_number', '20260950')->firstOrFail();
        $other = Student::factory()->for($classroom)->create();

        $this->assertTrue($student->isSpecialEducation());
        $this->get("/admin/students/{$student->id}/edit")
            ->assertOk()
            ->assertSee('Educação Especial (AEE)')
            ->assertSee('TEA (Transtorno do Espectro Autista)');

        Livewire::test(ListStudents::class)
            ->assertSee('AEE: TEA')
            ->filterTable('special_education', SpecialEducationNeed::Autism->value)
            ->assertCanSeeTableRecords([$student])
            ->assertCanNotSeeTableRecords([$other]);

        $this->get(route('filament.admin.report-cards.student', $student))->assertSee('Educação Especial (AEE)');
    }

    public function test_aee_teacher_sees_the_classrooms_she_attends(): void
    {
        $aee = StaffMember::factory()->specialist(TeacherSpecialty::SpecialEducation)->withLogin()->create();
        $attended = Classroom::factory()->create();
        $notAttended = Classroom::factory()->create();
        $attended->teachers()->attach($aee);
        $this->actingAs($aee->user);

        $this->assertTrue($aee->isSpecialist());
        Livewire::test(ListClassrooms::class)
            ->assertCanSeeTableRecords([$attended])
            ->assertCanNotSeeTableRecords([$notAttended]);
    }
}
