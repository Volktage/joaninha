<?php

namespace Tests\Feature\Filament;

use App\Enums\EducationStage;
use App\Filament\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Resources\Classrooms\RelationManagers\ExamsRelationManager;
use App\Filament\Resources\Classrooms\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\Exams\Pages\EditExam;
use App\Filament\Resources\Exams\Pages\ListExams;
use App\Filament\Resources\Exams\RelationManagers\GradesRelationManager as ExamGradesRelationManager;
use App\Filament\Resources\StaffMembers\Pages\EditStaffMember;
use App\Filament\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Filament\Resources\StaffMembers\RelationManagers\ClassroomsRelationManager as TeacherClassroomsRelationManager;
use App\Filament\Resources\StaffMembers\RelationManagers\ExamsRelationManager as TeacherExamsRelationManager;
use App\Filament\Resources\StaffMembers\RelationManagers\HomeroomClassroomsRelationManager;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager as StudentGradesRelationManager;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Livewire\GlobalSearch;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Percorre o painel inteiro com os dados realistas do seeder (6 meses de uso),
 * garantindo que nenhuma página, aba, ordenação, filtro ou busca gere erro.
 */
class SeededPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->travelTo('2026-11-14 12:00:00');
        $this->actingAs(User::query()->firstOrFail());
    }

    public function test_every_list_and_create_page_renders(): void
    {
        foreach ([
            '/admin', '/admin/classrooms', '/admin/classrooms/create', '/admin/students', '/admin/students/create',
            '/admin/exams', '/admin/exams/create', '/admin/exams?tab=awaiting_grades', '/admin/exams?tab=scheduled',
            '/admin/exams?tab=completed', '/admin/profile', '/admin/staff', '/admin/staff/create', '/admin/staff?tab=teacher', '/admin/staff?tab=direction',
            '/admin/attendance', '/admin/attendance?data=2026-11-02', '/admin/calendar', '/admin/calendar?mes=2026-07', '/admin/events', '/admin/events/create',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')->assertSee('Boa tarde, Admin!')->assertSee('2º semestre letivo');
    }

    public function test_seeded_staff_includes_claudia_as_homeroom_and_director_sonia_with_login(): void
    {
        $claudia = StaffMember::query()->where('name', 'Claudia Elaine Rossini')->firstOrFail();
        $preTwoB = Classroom::query()->where('name', 'Pré II B')->firstOrFail();
        $preTwoA = Classroom::query()->where('name', 'Pré II A')->firstOrFail();

        $this->assertTrue($preTwoB->homeroomTeacher->is($claudia));
        $this->assertSame('Fernanda Almeida Rocha', $preTwoA->homeroomTeacher->name);
        $this->assertSame($preTwoB->exams()->count(), $claudia->exams()->count());
        $this->assertSame(21, StaffMember::query()->teachers()->count());
        $this->assertSame(29, StaffMember::query()->count());

        auth()->logout();
        $this->assertTrue(auth()->attempt(['email' => 'sonia@escolajoaninha.com.br', 'password' => 'Joaninha@2026']));
        $this->get('/admin')->assertOk()->assertSee('Boa tarde, Sônia!');
    }

    public function test_login_page_renders_the_custom_heading(): void
    {
        auth()->logout();

        $this->get('/admin/login')->assertOk()->assertSee('Que bom ter você aqui!')->assertSee('Ano letivo 2026');
    }

    public function test_every_record_edit_page_renders_with_each_tab(): void
    {
        foreach (Classroom::all() as $classroom) {
            $this->get(route('filament.admin.report-cards.classroom', $classroom))->assertOk()->assertSee($classroom->name);
            $this->get("/admin/attendance?turma={$classroom->id}&data=2026-11-13")->assertOk()->assertSee('Chamada registrada');
        }

        foreach (Classroom::all() as $classroom) {
            foreach (['', '?relation=0', '?relation=1'] as $tab) {
                $this->get("/admin/classrooms/{$classroom->id}/edit{$tab}")->assertOk()->assertSee($classroom->name);
            }
        }

        foreach (Exam::all() as $exam) {
            $this->get("/admin/exams/{$exam->id}/edit?relation=0")->assertOk()->assertSee($exam->title);
        }

        foreach (Student::all() as $student) {
            $this->get("/admin/students/{$student->id}/edit?relation=0")->assertOk()->assertSee($student->name);
        }

        foreach (StaffMember::all() as $teacher) {
            $this->get("/admin/staff/{$teacher->id}/edit")->assertOk()->assertSee($teacher->name);
        }
    }

    public function test_unknown_records_return_not_found(): void
    {
        $this->get('/admin/students/999999/edit')->assertNotFound();
        $this->get('/admin/exams/999999/edit')->assertNotFound();
        $this->get('/admin/classrooms/999999/edit')->assertNotFound();
    }

    public function test_resource_tables_support_every_sort_filter_and_search(): void
    {
        $this->exerciseTable(Livewire::test(ListClassrooms::class), 'Ano');
        $this->exerciseTable(Livewire::test(ListStudents::class), 'Silva');
        $this->exerciseTable(Livewire::test(ListExams::class), 'Matemática');
        $this->exerciseTable(Livewire::test(ListStaffMembers::class), 'Silva');
    }

    public function test_exam_list_tabs_split_exams_by_status(): void
    {
        $awaiting = Exam::query()->awaitingGrades()->get();
        $scheduled = Exam::query()->scheduled()->get();

        $this->assertNotEmpty($awaiting);
        $this->assertNotEmpty($scheduled);

        Livewire::test(ListExams::class)
            ->set('activeTab', 'awaiting_grades')
            ->assertCanSeeTableRecords($awaiting)
            ->assertCountTableRecords($awaiting->count())
            ->set('activeTab', 'scheduled')
            ->assertCountTableRecords($scheduled->count())
            ->set('activeTab', 'completed')
            ->assertCountTableRecords(Exam::query()->gradesCompleted()->count());
    }

    public function test_every_relation_manager_renders_for_every_record(): void
    {
        foreach (Classroom::all() as $classroom) {
            $this->relationManager(StudentsRelationManager::class, $classroom, EditClassroom::class)
                ->assertCountTableRecords($classroom->students()->count());
            $this->relationManager(ExamsRelationManager::class, $classroom, EditClassroom::class)
                ->assertCountTableRecords($classroom->exams()->count());
        }

        foreach (Exam::all() as $exam) {
            $this->relationManager(ExamGradesRelationManager::class, $exam, EditExam::class)
                ->assertCountTableRecords($exam->grades()->count());
        }

        foreach (StaffMember::query()->teachers()->get() as $teacher) {
            $teacher->isSpecialist()
                ? $this->relationManager(TeacherClassroomsRelationManager::class, $teacher, EditStaffMember::class)->assertCountTableRecords($teacher->classrooms()->count())
                : $this->relationManager(HomeroomClassroomsRelationManager::class, $teacher, EditStaffMember::class)->assertCountTableRecords($teacher->homeroomClassrooms()->count());
            $this->exerciseTable($this->relationManager(TeacherExamsRelationManager::class, $teacher, EditStaffMember::class), 'Avaliação');
        }

        foreach (Student::all() as $student) {
            $this->relationManager(StudentGradesRelationManager::class, $student, EditStudent::class)
                ->assertCountTableRecords($student->grades()->count());
        }
    }

    public function test_relation_manager_tables_support_every_sort_filter_and_search_in_both_stages(): void
    {
        foreach (EducationStage::cases() as $stage) {
            $classroom = Classroom::query()->where('stage', $stage)->has('students')->firstOrFail();
            $exam = $classroom->exams()->has('grades')->firstOrFail();
            $student = $classroom->students()->firstOrFail();

            $this->exerciseTable($this->relationManager(StudentsRelationManager::class, $classroom, EditClassroom::class), 'a');
            $this->exerciseTable($this->relationManager(ExamsRelationManager::class, $classroom, EditClassroom::class), 'Relatório');
            $this->exerciseTable($this->relationManager(ExamGradesRelationManager::class, $exam, EditExam::class), 'a');
            $this->exerciseTable($this->relationManager(StudentGradesRelationManager::class, $student, EditStudent::class), 'Avaliação');
        }
    }

    public function test_global_search_finds_students_and_exams(): void
    {
        $student = Student::query()->firstOrFail();

        Livewire::test(GlobalSearch::class)
            ->set('search', $student->registration_number)
            ->assertSee($student->name)
            ->set('search', 'Matemática')
            ->assertSee('Avaliação de Matemática')
            ->set('search', 'Rafael Monteiro')
            ->assertSee('Rafael Monteiro Dias');
    }

    /**
     * @param  class-string  $relationManager
     */
    private function relationManager(string $relationManager, Model $ownerRecord, string $pageClass): Testable
    {
        return Livewire::test($relationManager, ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass])
            ->assertSuccessful();
    }

    /**
     * Ordena por todas as colunas ordenáveis nas duas direções, aplica cada opção de cada filtro e faz uma busca.
     */
    private function exerciseTable(Testable $component, string $search): void
    {
        $table = $component->instance()->getTable();

        foreach ($table->getColumns() as $column) {
            if (! $column->isSortable() || $column->isHidden()) {
                continue;
            }

            $component->sortTable($column->getName(), 'asc')->assertSuccessful();
            $component->sortTable($column->getName(), 'desc')->assertSuccessful();
        }

        foreach ($table->getFilters() as $filter) {
            if ($filter instanceof SelectFilter) {
                foreach (array_keys($filter->getOptions()) as $value) {
                    $component->filterTable($filter->getName(), $value)->assertSuccessful();
                }
            } elseif ($filter instanceof Filter) {
                $component->filterTable($filter->getName(), true)->assertSuccessful();
            }

            $component->removeTableFilters()->assertSuccessful();
        }

        $component->searchTable($search)->assertSuccessful()->searchTable(null)->assertSuccessful();
    }
}
