<?php

namespace Tests\Feature\Filament;

use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Enums\GuardianRelationship;
use App\Enums\TeacherSpecialty;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Resources\Exams\Pages\CreateExam;
use App\Filament\Resources\StaffMembers\Pages\CreateStaffMember;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherAndStudentDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_teacher_can_be_registered(): void
    {
        Livewire::test(CreateStaffMember::class)
            ->fillForm([
                'name' => 'Rafael Monteiro Dias',
                'email' => 'rafael.dias@escolajoaninha.com.br',
                'phone' => '(11) 98765-4321',
                'specialty' => TeacherSpecialty::English->value,
                'hired_at' => '2020-02-03',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('staff_members', ['email' => 'rafael.dias@escolajoaninha.com.br', 'specialty' => 'english']);
    }

    public function test_teacher_email_is_required_unique_and_valid(): void
    {
        $existing = StaffMember::factory()->create();

        Livewire::test(CreateStaffMember::class)
            ->fillForm(['name' => 'Outra Pessoa', 'email' => $existing->email, 'specialty' => 'pedagogy'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);

        Livewire::test(CreateStaffMember::class)
            ->fillForm(['name' => 'Outra Pessoa', 'email' => 'nao-e-email', 'specialty' => 'pedagogy'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_classroom_saves_homeroom_teacher_and_specialists(): void
    {
        $homeroom = StaffMember::factory()->create();
        $english = StaffMember::factory()->specialist(TeacherSpecialty::English)->create();
        $arts = StaffMember::factory()->specialist(TeacherSpecialty::Arts)->create();

        Livewire::test(CreateClassroom::class)
            ->fillForm([
                'name' => '3º Ano C',
                'stage' => EducationStage::Elementary->value,
                'year' => (string) now()->year,
                'period' => ClassroomPeriod::Morning->value,
                'homeroom_teacher_id' => $homeroom->id,
                'teachers' => [$english->id, $arts->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $classroom = Classroom::query()->where('name', '3º Ano C')->firstOrFail();
        $this->assertTrue($classroom->homeroomTeacher->is($homeroom));
        $this->assertEqualsCanonicalizing([$english->id, $arts->id], $classroom->teachers()->pluck('staff_members.id')->all());
        $this->assertSame([$classroom->id], $homeroom->fresh()->allClassrooms()->modelKeys());
        $this->assertSame([$classroom->id], $english->fresh()->allClassrooms()->modelKeys());
    }

    public function test_exam_suggests_the_homeroom_teacher_and_only_offers_teachers_of_the_classroom(): void
    {
        $homeroom = StaffMember::factory()->create();
        $english = StaffMember::factory()->specialist(TeacherSpecialty::English)->create();
        $outsider = StaffMember::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $homeroom->id]);
        $classroom->teachers()->attach($english);

        $this->assertEqualsCanonicalizing(
            [$homeroom->id, $english->id],
            StaffMember::query()->teachingClassroom($classroom->id)->pluck('id')->all(),
        );

        Livewire::test(CreateExam::class)
            ->fillForm(['classroom_id' => $classroom->id])
            ->assertSchemaStateSet(['teacher_id' => $homeroom->id])
            ->fillForm([
                'title' => 'Avaliação de Inglês – 4º Bimestre',
                'teacher_id' => $english->id,
                'applied_at' => today()->toDateString(),
                'weight' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Exam::query()->firstOrFail()->teacher->is($english));
        $this->assertFalse(StaffMember::query()->teachingClassroom($classroom->id)->whereKey($outsider->id)->exists());
    }

    public function test_student_is_created_with_guardian_and_health_information(): void
    {
        $classroom = Classroom::factory()->create();

        Livewire::test(CreateStudent::class)
            ->fillForm([
                'name' => 'Alice Lima Barbosa',
                'registration_number' => '20260900',
                'birth_date' => '2019-08-10',
                'classroom_id' => $classroom->id,
                'guardian_name' => 'Juliana Barbosa',
                'guardian_relationship' => GuardianRelationship::Mother->value,
                'guardian_phone' => '(11) 99876-5432',
                'guardian_email' => 'juliana.barbosa@exemplo.com.br',
                'allergies' => 'Amendoim',
                'health_notes' => 'Asma leve.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $student = Student::query()->where('registration_number', '20260900')->firstOrFail();
        $this->assertSame(GuardianRelationship::Mother, $student->guardian_relationship);
        $this->assertTrue($student->hasHealthAlert());
        $this->assertSame(today()->diffInYears('2019-08-10', absolute: true) >= 7, $student->age() >= 7);

        $this->get("/admin/students/{$student->id}/edit")
            ->assertOk()
            ->assertSee('Atenção à saúde')
            ->assertSee('Alergia: Amendoim.')
            ->assertSee('Juliana Barbosa (Mãe)');
    }

    public function test_guardian_email_must_be_valid(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm(['name' => 'Teste', 'registration_number' => '20260901', 'classroom_id' => Classroom::factory()->create()->id, 'guardian_email' => 'invalido'])
            ->call('create')
            ->assertHasFormErrors(['guardian_email' => 'email']);
    }

    public function test_students_can_be_filtered_by_health_alert_and_searched_by_guardian(): void
    {
        $withAllergy = Student::factory()->create(['allergies' => 'Lactose', 'guardian_name' => 'Marcelo Rocha']);
        $healthy = Student::factory()->create(['allergies' => null, 'health_notes' => null]);

        Livewire::test(ListStudents::class)
            ->filterTable('health_alert', true)
            ->assertCanSeeTableRecords([$withAllergy])
            ->assertCanNotSeeTableRecords([$healthy])
            ->removeTableFilters()
            ->searchTable('Marcelo Rocha')
            ->assertCanSeeTableRecords([$withAllergy])
            ->assertCanNotSeeTableRecords([$healthy]);
    }

    public function test_classroom_card_menu_links_to_tabs_and_deletes(): void
    {
        $classroom = Classroom::factory()->create();

        Livewire::test(ListClassrooms::class)
            ->assertActionHasUrl(TestAction::make('students')->table($classroom), ClassroomResource::getUrl('edit', ['record' => $classroom, 'relation' => '0']))
            ->assertActionHasUrl(TestAction::make('exams')->table($classroom), ClassroomResource::getUrl('edit', ['record' => $classroom, 'relation' => '1']))
            ->callAction(TestAction::make('delete')->table($classroom));

        $this->assertModelMissing($classroom);
    }
}
