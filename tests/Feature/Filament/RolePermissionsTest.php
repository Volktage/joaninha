<?php

namespace Tests\Feature\Filament;

use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Filament\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\Pages\ListExams;
use App\Filament\Resources\Exams\RelationManagers\GradesRelationManager;
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
 * Cada cargo enxerga e altera apenas o que lhe cabe.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private StaffMember $teacher;

    private Classroom $ownClassroom;

    private Classroom $otherClassroom;

    private Exam $ownExam;

    private Exam $otherExam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = StaffMember::factory()->withLogin()->create();
        $this->ownClassroom = Classroom::factory()->create(['homeroom_teacher_id' => $this->teacher->id]);
        $this->otherClassroom = Classroom::factory()->create();
        Student::factory()->count(2)->for($this->ownClassroom)->create();
        Student::factory()->count(2)->for($this->otherClassroom)->create();
        $this->ownExam = Exam::factory()->for($this->ownClassroom)->create(['teacher_id' => $this->teacher->id]);
        $this->otherExam = Exam::factory()->for($this->otherClassroom)->create();
    }

    private function loginAs(StaffRole $role): User
    {
        $user = $role === StaffRole::Teacher
            ? $this->teacher->user
            : StaffMember::factory()->role($role)->withLogin()->create()->user;

        $this->actingAs($user);

        return $user;
    }

    public function test_teacher_only_sees_own_classrooms_students_and_exams(): void
    {
        $this->loginAs(StaffRole::Teacher);

        Livewire::test(ListClassrooms::class)
            ->assertCanSeeTableRecords([$this->ownClassroom])
            ->assertCanNotSeeTableRecords([$this->otherClassroom]);

        Livewire::test(ListStudents::class)
            ->assertCanSeeTableRecords($this->ownClassroom->students)
            ->assertCanNotSeeTableRecords($this->otherClassroom->students);

        Livewire::test(ListExams::class)
            ->assertCanSeeTableRecords([$this->ownExam])
            ->assertCanNotSeeTableRecords([$this->otherExam]);
    }

    public function test_specialist_sees_classrooms_they_attend(): void
    {
        $english = StaffMember::factory()->specialist(TeacherSpecialty::English)->withLogin()->create();
        $this->otherClassroom->teachers()->attach($english);
        $this->actingAs($english->user);

        Livewire::test(ListClassrooms::class)
            ->assertCanSeeTableRecords([$this->otherClassroom])
            ->assertCanNotSeeTableRecords([$this->ownClassroom]);
    }

    public function test_teacher_opens_own_classroom_read_only_and_other_records_are_not_found(): void
    {
        $this->loginAs(StaffRole::Teacher);

        $this->get("/admin/classrooms/{$this->ownClassroom->id}/edit")->assertOk()->assertDontSee('Salvar Alterações');
        $this->get("/admin/classrooms/{$this->otherClassroom->id}/edit")->assertNotFound();
        $this->get("/admin/students/{$this->otherClassroom->students->first()->id}/edit")->assertNotFound();
        $this->get('/admin/classrooms/create')->assertForbidden();
        $this->get('/admin/students/create')->assertForbidden();
        $this->get('/admin/staff/create')->assertForbidden();
        $this->get('/admin/staff')->assertOk();
    }

    public function test_teacher_launches_grades_in_own_exam_but_not_in_others(): void
    {
        $this->loginAs(StaffRole::Teacher);
        $grade = $this->ownExam->grades()->firstOrFail();

        Livewire::test(GradesRelationManager::class, ['ownerRecord' => $this->ownExam, 'pageClass' => EditExam::class])
            ->call('updateTableColumnState', 'score', (string) $grade->id, '8.5');

        $this->assertSame(8.5, $grade->fresh()->score);
        $this->get("/admin/exams/{$this->otherExam->id}/edit")->assertNotFound();
    }

    public function test_teacher_can_create_exam_only_for_own_classroom(): void
    {
        $this->loginAs(StaffRole::Teacher);

        $this->get('/admin/exams/create')->assertOk();
        $this->assertTrue($this->teacher->user->can('create', Exam::class));
        $this->assertSame([$this->ownClassroom->id], Classroom::query()->visibleTo($this->teacher->user)->pluck('id')->all());
    }

    public function test_secretary_manages_enrollment_but_cannot_launch_grades(): void
    {
        $this->loginAs(StaffRole::Secretary);
        $grade = $this->otherExam->grades()->firstOrFail();

        $this->get('/admin/students/create')->assertOk();
        $this->get('/admin/classrooms/create')->assertOk();
        $this->get('/admin/exams/create')->assertForbidden();
        $this->get("/admin/exams/{$this->otherExam->id}/edit")->assertOk()->assertDontSee('Salvar Alterações');

        Livewire::test(GradesRelationManager::class, ['ownerRecord' => $this->otherExam, 'pageClass' => EditExam::class])
            ->call('updateTableColumnState', 'score', (string) $grade->id, '9');

        $this->assertNull($grade->fresh()->score);
    }

    public function test_coordination_manages_every_assessment_but_not_the_staff(): void
    {
        $user = $this->loginAs(StaffRole::Coordination);

        $this->assertTrue($user->can('update', $this->otherExam));
        $this->assertTrue($user->can('update', $this->otherExam->grades()->first()));
        $this->assertFalse($user->can('delete', $this->ownClassroom));
        $this->get('/admin/staff/create')->assertForbidden();
    }

    public function test_direction_and_system_administrator_have_full_access(): void
    {
        $director = $this->loginAs(StaffRole::Direction);
        $administrator = User::factory()->create();

        foreach ([$director, $administrator] as $user) {
            $this->assertTrue($user->hasFullAccess());
            $this->assertTrue($user->can('delete', $this->ownClassroom));
            $this->assertTrue($user->can('create', StaffMember::class));
        }

        $this->assertSame('Administração do sistema', $administrator->roleLabel());
        $this->assertSame('Direção', $director->roleLabel());
    }
}
