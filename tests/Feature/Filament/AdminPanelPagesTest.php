<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Resources\Classrooms\RelationManagers\ExamsRelationManager;
use App\Filament\Resources\Classrooms\RelationManagers\StudentsRelationManager;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\RelationManagers\GradesRelationManager as StudentGradesRelationManager;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Widgets\AverageByClassroomChart;
use App\Filament\Widgets\GradeDistributionChart;
use App\Filament\Widgets\PendingGradesTable;
use App\Filament\Widgets\PerformanceTrendChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UpcomingExamsTable;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelPagesTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;

    private Student $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->classroom = Classroom::factory()->create();
        $this->student = Student::factory()->for($this->classroom)->create();
        $this->exam = Exam::factory()->for($this->classroom)->create(['weight' => 2]);
        $this->exam->grades()->update(['score' => 8.5]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        auth()->logout();

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function staticPanelUrls(): array
    {
        return [
            'dashboard' => ['/admin'],
            'classrooms list' => ['/admin/classrooms'],
            'classroom create' => ['/admin/classrooms/create'],
            'students list' => ['/admin/students'],
            'student create' => ['/admin/students/create'],
            'exams list' => ['/admin/exams'],
            'exam create' => ['/admin/exams/create'],
        ];
    }

    #[DataProvider('staticPanelUrls')]
    public function test_panel_pages_render(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_edit_pages_render(): void
    {
        $this->get(ClassroomResource::getUrl('edit', ['record' => $this->classroom]))->assertOk()->assertSee('Fundamental I');
        $this->get(StudentResource::getUrl('edit', ['record' => $this->student]))->assertOk()->assertSee('Média ponderada 8,5');
        $this->get(ExamResource::getUrl('edit', ['record' => $this->exam, 'relation' => '0']))->assertOk()->assertSee('Notas (Diário de Classe)');
    }

    public function test_classroom_relation_managers_list_students_and_exams(): void
    {
        Livewire::test(StudentsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => EditClassroom::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->student])
            ->assertTableColumnStateSet('weighted_average', 8.5, $this->student);

        Livewire::test(ExamsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => EditClassroom::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->exam]);
    }

    public function test_student_report_card_renders_sorted_by_exam_date(): void
    {
        $upcomingExam = Exam::factory()->for($this->classroom)->create(['applied_at' => today()->addWeek()]);
        $expectedOrder = collect([$upcomingExam, $this->exam])
            ->map(fn (Exam $exam) => $this->student->grades()->where('exam_id', $exam->id)->firstOrFail());

        Livewire::test(StudentGradesRelationManager::class, ['ownerRecord' => $this->student, 'pageClass' => EditStudent::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($expectedOrder, inOrder: true);
    }

    public function test_dashboard_widgets_render(): void
    {
        foreach ([StatsOverview::class, AverageByClassroomChart::class, GradeDistributionChart::class, PerformanceTrendChart::class, PendingGradesTable::class, UpcomingExamsTable::class] as $widget) {
            Livewire::test($widget)->assertSuccessful();
        }
    }
}
