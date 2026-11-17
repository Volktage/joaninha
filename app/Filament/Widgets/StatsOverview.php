<?php

namespace App\Filament\Widgets;

use App\Enums\EducationStage;
use App\Enums\ExamStatus;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class StatsOverview extends BaseWidget
{
    use HasSkeletonPlaceholder;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        return [
            $this->studentsStat(),
            $this->averageStat(),
            $this->pendingGradesStat(),
            $this->upcomingExamsStat(),
        ];
    }

    private function studentsStat(): Stat
    {
        $classroomsByStage = Classroom::query()->visibleTo(auth()->user())
            ->where('year', (string) now()->year)
            ->get(['stage'])
            ->countBy(fn (Classroom $classroom): string => $classroom->stage->value);

        $months = $this->lastMonths();
        $enrollmentsPerMonth = $months->map(
            fn (CarbonImmutable $month): int => Student::query()->visibleTo(auth()->user())->where('created_at', '<', $month->endOfMonth())->count()
        );
        $newStudents = Student::query()->visibleTo(auth()->user())->where('created_at', '>=', now()->subDays(30))->count();

        return Stat::make('Alunos matriculados', Student::query()->visibleTo(auth()->user())->count())
            ->description(sprintf(
                '%d turmas · %d Infantil · %d Fundamental',
                $classroomsByStage->sum(),
                $classroomsByStage[EducationStage::EarlyChildhood->value] ?? 0,
                $classroomsByStage[EducationStage::Elementary->value] ?? 0,
            ))
            ->descriptionIcon(Heroicon::OutlinedAcademicCap)
            ->chart($enrollmentsPerMonth->all())
            ->color('primary')
            ->extraAttributes(['title' => "{$newStudents} matrícula(s) nos últimos 30 dias"])
            ->url(StudentResource::getUrl());
    }

    private function averageStat(): Stat
    {
        $scores = Grade::query()->visibleTo(auth()->user())->whereNotNull('score');
        $average = (float) $scores->clone()->avg('score');
        $total = $scores->clone()->count();
        $goodShare = $total > 0 ? round($scores->clone()->where('score', '>=', Grade::GOOD_SCORE)->count() / $total * 100) : 0;

        $monthlyAverages = $this->lastMonths()->map(fn (CarbonImmutable $month): float => round((float) Grade::query()->visibleTo(auth()->user())
            ->whereNotNull('score')
            ->whereHas('exam', fn ($query) => $query->whereBetween('applied_at', [$month->startOfMonth(), $month->endOfMonth()]))
            ->avg('score'), 1));

        return Stat::make('Média geral (Fundamental)', Grade::formatScore($total > 0 ? $average : null))
            ->description("{$goodShare}% das notas iguais ou acima de 7")
            ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
            ->chart($monthlyAverages->filter()->values()->all())
            ->color(Grade::colorForScore($total > 0 ? $average : null));
    }

    private function pendingGradesStat(): Stat
    {
        $pendingGrades = Grade::query()->visibleTo(auth()->user())->pending()->count();
        $examsAwaiting = Exam::query()->visibleTo(auth()->user())->awaitingGrades()->count();

        return Stat::make('Lançamentos pendentes', $pendingGrades)
            ->description($pendingGrades > 0
                ? "{$examsAwaiting} avaliação(ões) já aplicada(s) aguardando notas"
                : 'Tudo em dia!')
            ->descriptionIcon($pendingGrades > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckBadge)
            ->color($pendingGrades > 0 ? 'warning' : 'success')
            ->url(ExamResource::getUrl('index', ['tab' => ExamStatus::AwaitingGrades->value]));
    }

    private function upcomingExamsStat(): Stat
    {
        $upcoming = Exam::query()->visibleTo(auth()->user())->whereBetween('applied_at', [today()->addDay(), today()->addDays(14)])->count();
        $next = Exam::query()->visibleTo(auth()->user())->scheduled()->with('classroom')->orderBy('applied_at')->first();

        return Stat::make('Avaliações nos próximos 14 dias', $upcoming)
            ->description($next
                ? "Próxima: {$next->classroom->name}, {$next->applied_at->format('d/m')}"
                : 'Nenhuma avaliação agendada')
            ->descriptionIcon(Heroicon::OutlinedCalendarDays)
            ->color('info')
            ->url(ExamResource::getUrl('index', ['tab' => ExamStatus::Scheduled->value]));
    }

    /**
     * Os últimos seis meses, do mais antigo ao atual.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function lastMonths(): Collection
    {
        return collect(range(5, 0))->map(fn (int $monthsAgo): CarbonImmutable => CarbonImmutable::today()->startOfMonth()->subMonths($monthsAgo));
    }
}
