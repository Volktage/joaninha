<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\SchoolEvent;
use App\Models\Student;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Chamada diária da turma: um toque por aluno, com "todos presentes" e alertas de dias sem aula.
 */
class TakeAttendance extends Page
{
    protected string $view = 'filament.pages.take-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::HandRaised;

    protected static ?string $navigationLabel = 'Chamada';

    protected static ?string $title = 'Chamada';

    protected static ?string $slug = 'attendance';

    protected static string|UnitEnum|null $navigationGroup = 'Pedagógico';

    protected static ?int $navigationSort = 2;

    #[Url(as: 'turma')]
    public ?int $classroomId = null;

    #[Url(as: 'data')]
    public ?string $date = null;

    /** @var array<int|string, string> Situação de cada aluno, indexada pelo id. */
    public array $statuses = [];

    /** @var array<int|string, string|null> */
    public array $notes = [];

    public function mount(): void
    {
        $this->classroomId ??= $this->defaultClassroomId();
        $this->date = $this->normalizeDate($this->date);

        $this->loadAttendance();
    }

    public function getSubheading(): string
    {
        return 'Toque na situação de cada aluno e salve. Atraso conta como presença; falta justificada continua sendo falta.';
    }

    public function updatedClassroomId(): void
    {
        $this->loadAttendance();
    }

    public function updatedDate(): void
    {
        $this->date = $this->normalizeDate($this->date);
        $this->loadAttendance();
    }

    public function shiftDate(int $days): void
    {
        $this->date = $this->selectedDate()->addDays($days)->toDateString();
        $this->loadAttendance();
    }

    public function goToToday(): void
    {
        $this->date = today()->toDateString();
        $this->loadAttendance();
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (AttendanceStatus::tryFrom($status) && $this->students->contains('id', $studentId)) {
            $this->statuses[$studentId] = $status;
        }
    }

    public function markEveryoneAs(string $status): void
    {
        if (! AttendanceStatus::tryFrom($status)) {
            return;
        }

        foreach ($this->students as $student) {
            $this->statuses[$student->id] = $status;
        }
    }

    public function save(): void
    {
        $classroom = $this->classroom;

        abort_unless($classroom && Gate::allows('takeFor', [Attendance::class, $classroom]), 403);

        if ($this->students->isEmpty()) {
            Notification::make()->title('Não há alunos matriculados nesta turma na data escolhida.')->warning()->send();

            return;
        }

        if ($this->noClassReason() !== null || $this->selectedDate()->isAfter(today())) {
            Notification::make()->title('Não é possível registrar chamada nesta data.')->warning()->send();

            return;
        }

        $now = now();

        DB::transaction(function () use ($classroom, $now): void {
            foreach ($this->students as $student) {
                $status = AttendanceStatus::tryFrom($this->statuses[$student->id] ?? '') ?? AttendanceStatus::Present;

                Attendance::query()->updateOrCreate(
                    ['student_id' => $student->id, 'date' => $this->selectedDate()->toDateString()],
                    [
                        'classroom_id' => $classroom->id,
                        'status' => $status,
                        'notes' => filled($this->notes[$student->id] ?? null) ? $this->notes[$student->id] : null,
                        'recorded_by' => auth()->id(),
                        'updated_at' => $now,
                    ],
                );
            }
        });

        unset($this->existingRecordInfo);

        $absences = collect($this->statuses)->filter(fn (string $status): bool => ! AttendanceStatus::from($status)->countsAsPresence())->count();

        Notification::make()
            ->title('Chamada salva!')
            ->body("{$classroom->name} · {$this->selectedDate()->format('d/m/Y')} · ".($absences === 0 ? 'todos presentes' : "{$absences} falta(s)"))
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, Classroom>
     */
    #[Computed]
    public function classrooms(): Collection
    {
        return Classroom::query()->visibleTo(auth()->user())->orderBy('id')->get();
    }

    #[Computed]
    public function classroom(): ?Classroom
    {
        $classroom = $this->classrooms->firstWhere('id', $this->classroomId);

        return $classroom && Gate::allows('takeFor', [Attendance::class, $classroom]) ? $classroom : null;
    }

    /**
     * Alunos já matriculados na data da chamada.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function students(): Collection
    {
        if (! $this->classroom) {
            return collect();
        }

        return $this->classroom->students()
            ->where('created_at', '<=', $this->selectedDate()->endOfDay())
            ->orderBy('name')
            ->get();
    }

    /**
     * Motivo de não haver aula na data (fim de semana, feriado, recesso ou dia pedagógico).
     */
    public function noClassReason(): ?string
    {
        $date = $this->selectedDate();

        if ($date->isWeekend()) {
            return $date->isSaturday() ? 'Sábado' : 'Domingo';
        }

        $event = SchoolEvent::query()
            ->overlapping($date, $date)
            ->where(fn ($query) => $query->whereNull('classroom_id')->orWhere('classroom_id', $this->classroomId))
            ->get()
            ->first(fn (SchoolEvent $event): bool => $event->type->suspendsClasses());

        return $event ? "{$event->type->getLabel()}: {$event->title}" : null;
    }

    /**
     * Quem registrou a chamada desta turma no dia e quando, se já existe.
     */
    #[Computed]
    public function existingRecordInfo(): ?string
    {
        $latest = Attendance::query()
            ->where('classroom_id', $this->classroomId)
            ->whereDate('date', $this->selectedDate())
            ->with('recordedBy')
            ->latest('updated_at')
            ->first();

        if (! $latest) {
            return null;
        }

        return 'Chamada registrada'
            .($latest->recordedBy ? " por {$latest->recordedBy->name}" : '')
            .' em '.$latest->updated_at->format('d/m \à\s H:i').'. Alterações substituem o registro anterior.';
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $counts = collect($this->statuses)->countBy();

        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    public function selectedDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date ?? today());
    }

    private function loadAttendance(): void
    {
        unset($this->classroom, $this->students, $this->existingRecordInfo);

        $saved = Attendance::query()
            ->where('classroom_id', $this->classroomId)
            ->whereDate('date', $this->selectedDate())
            ->get()
            ->keyBy('student_id');

        $this->statuses = [];
        $this->notes = [];

        foreach ($this->students as $student) {
            $this->statuses[$student->id] = $saved->get($student->id)?->status->value ?? AttendanceStatus::Present->value;
            $this->notes[$student->id] = $saved->get($student->id)?->notes;
        }
    }

    private function defaultClassroomId(): ?int
    {
        $teacher = auth()->user()->teacherProfile();

        return $teacher?->homeroomClassrooms()->orderBy('id')->value('id')
            ?? $this->classrooms->first()?->id;
    }

    /**
     * Datas futuras voltam para hoje; a chamada é sempre do dia ou de dias anteriores.
     */
    private function normalizeDate(?string $date): string
    {
        $parsed = rescue(fn (): CarbonImmutable => CarbonImmutable::parse($date ?? today()), CarbonImmutable::today(), report: false);

        return ($parsed->isAfter(today()) ? CarbonImmutable::today() : $parsed)->toDateString();
    }
}
