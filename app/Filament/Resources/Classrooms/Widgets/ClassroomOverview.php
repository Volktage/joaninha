<?php

namespace App\Filament\Resources\Classrooms\Widgets;

use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Resumo da turma exibido no topo da página da turma.
 */
class ClassroomOverview extends StatsOverviewWidget
{
    use HasSkeletonPlaceholder;

    public ?Classroom $record = null;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $classroom = $this->record;
        $examCount = $classroom->exams()->count();
        $completed = $classroom->exams()->gradesCompleted()->count();
        $pending = Grade::query()->pending()->whereHas('exam', fn ($query) => $query->where('classroom_id', $classroom->id))->count();
        $nextExam = $classroom->exams()->scheduled()->orderBy('applied_at')->first();

        return [
            Stat::make('Alunos', $classroom->students()->count())
                ->description($classroom->period?->getLabel().' · '.$classroom->stage?->getLabel())
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),
            $classroom->isGraded()
                ? $this->averageStat($classroom)
                : Stat::make('Relatórios escritos', Grade::query()->whereNotNull('comments')->whereHas('exam', fn ($query) => $query->where('classroom_id', $classroom->id))->count())
                    ->description('Avaliação descritiva, sem nota')
                    ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                    ->color('warning'),
            Stat::make('Avaliações lançadas', "{$completed}/{$examCount}")
                ->description($nextExam ? "Próxima: {$nextExam->applied_at->format('d/m')} – {$nextExam->title}" : 'Nenhuma avaliação agendada')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('Lançamentos pendentes', $pending)
                ->description($pending > 0 ? 'Alunos sem nota ou relatório' : 'Tudo em dia!')
                ->descriptionIcon($pending > 0 ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckBadge)
                ->color($pending > 0 ? 'warning' : 'success'),
        ];
    }

    private function averageStat(Classroom $classroom): Stat
    {
        $averages = Student::query()
            ->where('classroom_id', $classroom->id)
            ->withWeightedAverage()
            ->get()
            ->pluck('weighted_average')
            ->filter(fn ($average): bool => $average !== null)
            ->map(fn ($average): float => (float) $average);

        $classAverage = $averages->isEmpty() ? null : round($averages->avg(), 2);
        $belowLow = $averages->filter(fn (float $average): bool => $average < Grade::LOW_SCORE)->count();

        $monthlyAverages = Exam::query()
            ->where('classroom_id', $classroom->id)
            ->whereDate('applied_at', '<=', today())
            ->orderBy('applied_at')
            ->withAvg('grades', 'score')
            ->get()
            ->pluck('grades_avg_score')
            ->filter()
            ->map(fn ($average): float => round((float) $average, 1))
            ->values();

        return Stat::make('Média ponderada da turma', Grade::formatScore($classAverage))
            ->description($belowLow > 0 ? "{$belowLow} aluno(s) com média abaixo de 5" : 'Nenhum aluno com média abaixo de 5')
            ->descriptionIcon($belowLow > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedArrowTrendingUp)
            ->chart($monthlyAverages->all())
            ->color(Grade::colorForScore($classAverage));
    }
}
