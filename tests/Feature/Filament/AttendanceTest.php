<?php

namespace Tests\Feature\Filament;

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Widgets\LowAttendanceTable;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\SchoolEvent;
use App\Models\StaffMember;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private StaffMember $teacher;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 10:00:00');
        $this->teacher = StaffMember::factory()->withLogin()->create();
        $this->classroom = Classroom::factory()->create(['homeroom_teacher_id' => $this->teacher->id]);
        Student::factory()->count(3)->for($this->classroom)->create(['created_at' => '2026-05-11 08:00:00']);
        $this->actingAs($this->teacher->user);
    }

    public function test_teacher_takes_attendance_for_own_classroom(): void
    {
        [$first, $second, $third] = $this->classroom->students()->orderBy('id')->get()->all();

        Livewire::test(TakeAttendance::class)
            ->assertSet('classroomId', $this->classroom->id)
            ->assertSet('date', '2026-11-11')
            ->call('setStatus', $second->id, AttendanceStatus::Absent->value)
            ->set("notes.{$second->id}", 'Avisou que estava doente.')
            ->call('setStatus', $third->id, AttendanceStatus::Late->value)
            ->call('save')
            ->assertNotified('Chamada salva!');

        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseHas('attendances', ['student_id' => $first->id, 'status' => 'present', 'recorded_by' => $this->teacher->user_id]);
        $this->assertDatabaseHas('attendances', ['student_id' => $second->id, 'status' => 'absent', 'notes' => 'Avisou que estava doente.']);
        $this->assertDatabaseHas('attendances', ['student_id' => $third->id, 'status' => 'late']);
    }

    public function test_saving_again_updates_the_same_day_instead_of_duplicating(): void
    {
        $student = $this->classroom->students()->firstOrFail();

        Livewire::test(TakeAttendance::class)->call('save');
        Livewire::test(TakeAttendance::class)
            ->assertSet("statuses.{$student->id}", 'present')
            ->call('setStatus', $student->id, AttendanceStatus::Justified->value)
            ->call('save');

        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'status' => 'justified']);
    }

    public function test_attendance_is_blocked_on_weekends_and_days_without_classes(): void
    {
        SchoolEvent::factory()->holiday()->create(['title' => 'Finados', 'starts_on' => '2026-11-02']);

        Livewire::test(TakeAttendance::class)
            ->set('date', '2026-11-02')
            ->assertSee('Não há aula nesta data')
            ->assertSee('Feriado: Finados')
            ->call('save')
            ->set('date', '2026-11-08')
            ->assertSee('Domingo');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_future_dates_fall_back_to_today(): void
    {
        Livewire::test(TakeAttendance::class)
            ->set('date', '2026-12-01')
            ->assertSet('date', '2026-11-11');
    }

    public function test_teacher_cannot_take_attendance_for_another_classroom(): void
    {
        $other = Classroom::factory()->create();
        Student::factory()->for($other)->create();

        Livewire::test(TakeAttendance::class)
            ->set('classroomId', $other->id)
            ->assertSee('Nenhuma turma disponível')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_secretary_can_register_attendance_in_any_classroom(): void
    {
        $this->actingAs(StaffMember::factory()->role(StaffRole::Secretary)->withLogin()->create()->user);

        Livewire::test(TakeAttendance::class, ['classroomId' => $this->classroom->id])
            ->call('save')
            ->assertNotified('Chamada salva!');

        $this->assertDatabaseCount('attendances', 3);
    }

    public function test_attendance_rate_and_low_attendance_alert(): void
    {
        [$regular, $missing] = $this->classroom->students()->orderBy('id')->take(2)->get()->all();

        foreach (range(1, 10) as $day) {
            $date = '2026-10-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            Attendance::factory()->for($regular)->create(['classroom_id' => $this->classroom->id, 'date' => $date, 'status' => $day === 1 ? AttendanceStatus::Late : AttendanceStatus::Present]);
            Attendance::factory()->for($missing)->create(['classroom_id' => $this->classroom->id, 'date' => $date, 'status' => $day <= 3 ? AttendanceStatus::Absent : AttendanceStatus::Present]);
        }

        $this->assertSame(100.0, $regular->attendanceRate());
        $this->assertSame(70.0, $missing->attendanceRate());
        $this->assertSame('danger', Attendance::colorForRate($missing->attendanceRate()));

        Livewire::test(LowAttendanceTable::class)
            ->assertCanSeeTableRecords([$missing])
            ->assertCanNotSeeTableRecords([$regular]);
    }
}
