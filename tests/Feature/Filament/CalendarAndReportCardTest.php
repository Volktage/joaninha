<?php

namespace Tests\Feature\Filament;

use App\Enums\AttendanceStatus;
use App\Enums\SchoolEventType;
use App\Enums\StaffRole;
use App\Filament\Pages\SchoolCalendar;
use App\Filament\Resources\SchoolEvents\Pages\CreateSchoolEvent;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\SchoolEvent;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAndReportCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 10:00:00');
    }

    public function test_calendar_shows_month_events_and_scheduled_exams(): void
    {
        $this->actingAs(User::factory()->create());
        $classroom = Classroom::factory()->create(['name' => '3º Ano A']);
        SchoolEvent::factory()->create(['title' => 'Mostra Cultural', 'type' => SchoolEventType::Celebration, 'starts_on' => '2026-11-28']);
        SchoolEvent::factory()->create(['title' => 'Recesso de dezembro', 'type' => SchoolEventType::Recess, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-31']);
        Exam::factory()->for($classroom)->create(['title' => 'Avaliação de Inglês – 4º Bimestre', 'applied_at' => '2026-11-16']);

        Livewire::test(SchoolCalendar::class)
            ->assertSet('month', '2026-11')
            ->assertSee('Mostra Cultural')
            ->assertSee('1 avaliação')
            ->assertSee('Avaliação de Inglês – 4º Bimestre')
            ->assertDontSee('Recesso de dezembro')
            ->call('nextMonth')
            ->assertSet('month', '2026-12')
            ->assertSee('Recesso de dezembro')
            ->call('currentMonth')
            ->assertSet('month', '2026-11');
    }

    public function test_teachers_do_not_see_events_of_other_classrooms(): void
    {
        $teacher = StaffMember::factory()->withLogin()->create();
        $own = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $other = Classroom::factory()->create();
        SchoolEvent::factory()->create(['title' => 'Passeio da minha turma', 'classroom_id' => $own->id, 'starts_on' => '2026-11-20']);
        SchoolEvent::factory()->create(['title' => 'Passeio de outra turma', 'classroom_id' => $other->id, 'starts_on' => '2026-11-20']);
        $this->actingAs($teacher->user);

        Livewire::test(SchoolCalendar::class)
            ->assertSee('Passeio da minha turma')
            ->assertDontSee('Passeio de outra turma');

        $this->get('/admin/events/create')->assertForbidden();
    }

    public function test_secretary_creates_an_event(): void
    {
        $this->actingAs(StaffMember::factory()->role(StaffRole::Secretary)->withLogin()->create()->user);

        Livewire::test(CreateSchoolEvent::class)
            ->fillForm([
                'title' => 'Reunião de pais – entrega dos boletins finais',
                'type' => SchoolEventType::ParentsMeeting->value,
                'starts_on' => '2026-12-05',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(SchoolCalendar::getUrl(['mes' => '2026-12']));

        $this->assertDatabaseHas('school_events', ['title' => 'Reunião de pais – entrega dos boletins finais', 'classroom_id' => null]);
    }

    public function test_event_end_cannot_be_before_start(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateSchoolEvent::class)
            ->fillForm(['title' => 'Recesso', 'type' => SchoolEventType::Recess->value, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-01'])
            ->call('create')
            ->assertHasFormErrors(['ends_on']);
    }

    public function test_report_card_prints_grades_by_bimester_and_attendance(): void
    {
        $this->actingAs(User::factory()->create());
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->for($classroom)->create(['name' => 'Alice Lima Barbosa']);
        $exam = Exam::factory()->for($classroom)->create(['title' => 'Avaliação de Matemática – 3º Bimestre', 'applied_at' => '2026-09-15', 'weight' => 2]);
        $exam->grades()->update(['score' => 8.5]);
        Attendance::factory()->for($student)->create(['classroom_id' => $classroom->id, 'date' => '2026-09-15', 'status' => AttendanceStatus::Present]);
        Attendance::factory()->for($student)->create(['classroom_id' => $classroom->id, 'date' => '2026-09-16', 'status' => AttendanceStatus::Absent]);

        $this->get(route('filament.admin.report-cards.student', $student))
            ->assertOk()
            ->assertSee('Boletim Escolar')
            ->assertSee('Alice Lima Barbosa')
            ->assertSee('3º bimestre')
            ->assertSee('Avaliação de Matemática – 3º Bimestre')
            ->assertSee('8,5')
            ->assertSee('50,0%')
            ->assertSee('Frequência abaixo do mínimo de 75%');
    }

    public function test_early_childhood_report_card_shows_reports_instead_of_grades(): void
    {
        $this->actingAs(User::factory()->create());
        $classroom = Classroom::factory()->earlyChildhood()->create();
        $student = Student::factory()->for($classroom)->create();
        $exam = Exam::factory()->for($classroom)->create(['title' => 'Relatório de Desenvolvimento – 1º Semestre', 'applied_at' => '2026-06-22']);
        $exam->grades()->update(['comments' => 'Participa das rodas de conversa com entusiasmo.']);

        $this->get(route('filament.admin.report-cards.student', $student))
            ->assertOk()
            ->assertSee('Relatório de Desenvolvimento')
            ->assertSee('Participa das rodas de conversa com entusiasmo.')
            ->assertDontSee('Avaliações por bimestre');
    }

    public function test_classroom_report_cards_include_every_student_and_respect_permissions(): void
    {
        $teacher = StaffMember::factory()->withLogin()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $students = Student::factory()->count(3)->for($classroom)->create();
        $other = Classroom::factory()->create();
        $otherStudent = Student::factory()->for($other)->create();
        $this->actingAs($teacher->user);

        $response = $this->get(route('filament.admin.report-cards.classroom', $classroom))->assertOk();
        $students->each(fn (Student $student) => $response->assertSee($student->name));

        $this->get(route('filament.admin.report-cards.classroom', $other))->assertForbidden();
        $this->get(route('filament.admin.report-cards.student', $otherStudent))->assertForbidden();
    }
}
