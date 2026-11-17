<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\Bimester;
use App\Enums\StaffRole;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\StaffMember;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Boletins prontos para impressão (ou para salvar em PDF pelo navegador).
 */
class ReportCardController extends Controller
{
    public function student(Student $student): View
    {
        Gate::authorize('view', $student);

        return view('report-cards.print', [
            'title' => "Boletim – {$student->name}",
            'reportCards' => [$this->reportCardFor($student)],
            'director' => $this->directorName(),
        ]);
    }

    public function classroom(Classroom $classroom): View
    {
        Gate::authorize('view', $classroom);

        $students = $classroom->students()->orderBy('name')->get();

        return view('report-cards.print', [
            'title' => "Boletins – {$classroom->name}",
            'reportCards' => $students->map(fn (Student $student): array => $this->reportCardFor($student))->all(),
            'director' => $this->directorName(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportCardFor(Student $student): array
    {
        $student->loadMissing('classroom.homeroomTeacher');

        $grades = $student->grades()
            ->with('exam.teacher')
            ->get()
            ->filter(fn (Grade $grade): bool => $grade->exam->applied_at->lte(today()))
            ->sortBy('exam.applied_at')
            ->values();

        return [
            'student' => $student,
            'isGraded' => $student->classroom->isGraded(),
            'bimesters' => $this->bimesters($grades),
            'average' => $student->weightedAverage(),
            'reports' => $grades->filter(fn (Grade $grade): bool => filled($grade->comments) && ! $student->classroom->isGraded())->values(),
            'attendance' => $this->attendanceSummary($student),
        ];
    }

    /**
     * Avaliações agrupadas por bimestre, com a média ponderada de cada um.
     *
     * @param  Collection<int, Grade>  $grades
     * @return Collection<int, array{bimester: Bimester, grades: Collection<int, Grade>, average: float|null}>
     */
    private function bimesters(Collection $grades): Collection
    {
        return $grades
            ->groupBy(fn (Grade $grade): int => Bimester::fromDate($grade->exam->applied_at)->value)
            ->sortKeys()
            ->map(function (Collection $bimesterGrades, int $bimester): array {
                $scored = $bimesterGrades->filter(fn (Grade $grade): bool => $grade->score !== null);
                $weights = $scored->sum(fn (Grade $grade): float => $grade->exam->weight);

                return [
                    'bimester' => Bimester::from($bimester),
                    'grades' => $bimesterGrades,
                    'average' => $weights > 0
                        ? round($scored->sum(fn (Grade $grade): float => $grade->score * $grade->exam->weight) / $weights, 2)
                        : null,
                ];
            })
            ->values();
    }

    /**
     * @return array{days: int, presences: int, absences: int, justified: int, late: int, rate: float|null}
     */
    private function attendanceSummary(Student $student): array
    {
        $counts = $student->attendances()
            ->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $days = (int) $counts->sum();
        $presences = (int) (($counts[AttendanceStatus::Present->value] ?? 0) + ($counts[AttendanceStatus::Late->value] ?? 0));

        return [
            'days' => $days,
            'presences' => $presences,
            'absences' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
            'justified' => (int) ($counts[AttendanceStatus::Justified->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0),
            'rate' => $days > 0 ? round($presences / $days * 100, 1) : null,
        ];
    }

    private function directorName(): ?string
    {
        return StaffMember::query()->where('role', StaffRole::Direction)->value('name');
    }

    /**
     * Mantém a referência à frequência mínima legal disponível para a view.
     */
    public static function minimumAttendanceRate(): float
    {
        return Attendance::MINIMUM_RATE;
    }
}
