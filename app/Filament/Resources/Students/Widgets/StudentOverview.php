<?php

namespace App\Filament\Resources\Students\Widgets;

use App\Enums\AttendanceStatus;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Student;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Resumo do aluno exibido no topo da ficha.
 */
class StudentOverview extends StatsOverviewWidget
{
    use HasSkeletonPlaceholder;

    public ?Student $record = null;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    protected function getStats(): array
    {
        $student = $this->record;
        $attendance = $this->attendanceStat($student);
        $enrolledSince = $student->created_at->format('d/m/Y');

        if (! $student->classroom->isGraded()) {
            $reports = $student->grades()->whereNotNull('comments')->count();

            return [
                Stat::make('Turma', $student->classroom->name)
                    ->description('Educação Infantil · cadastrado em '.$enrolledSince)
                    ->descriptionIcon(Heroicon::OutlinedPuzzlePiece)
                    ->color('warning'),
                Stat::make('Relatórios', $reports)
                    ->description('Registros descritivos de desenvolvimento')
                    ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                    ->color('primary'),
                Stat::make('Pendentes', $student->grades()->pending()->count())
                    ->description('Relatórios ainda não escritos')
                    ->descriptionIcon(Heroicon::OutlinedClock)
                    ->color('gray'),
                $attendance,
            ];
        }

        $scores = $student->grades()->whereNotNull('score')->with('exam')->get()->sortBy('exam.applied_at');
        $average = $student->weightedAverage();
        $best = $scores->sortByDesc('score')->first();

        return [
            Stat::make('Média ponderada', Grade::formatScore($average))
                ->description($this->trendDescription($scores->pluck('score')->all()))
                ->descriptionIcon(str_contains($this->trendDescription($scores->pluck('score')->all()), 'queda') ? Heroicon::OutlinedArrowTrendingDown : Heroicon::OutlinedArrowTrendingUp)
                ->chart($scores->pluck('score')->all())
                ->color(Grade::colorForScore($average)),
            Stat::make('Melhor nota', Grade::formatScore($best?->score))
                ->description($best?->exam->title ?? 'Nenhuma nota lançada')
                ->descriptionIcon(Heroicon::OutlinedTrophy)
                ->color('success'),
            Stat::make('Avaliações realizadas', $scores->count())
                ->description("Turma {$student->classroom->name} · desde {$enrolledSince}")
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('info'),
            $attendance,
        ];
    }

    /**
     * Frequência na chamada diária, com alerta abaixo de 85% e de 75% (mínimo legal).
     */
    private function attendanceStat(Student $student): Stat
    {
        $rate = $student->attendanceRate();
        $absences = $student->attendances()->whereIn('status', [AttendanceStatus::Absent, AttendanceStatus::Justified])->count();
        $justified = $student->attendances()->where('status', AttendanceStatus::Justified)->count();

        return Stat::make('Frequência', Attendance::formatRate($rate))
            ->description(match (true) {
                $rate === null => 'Nenhuma chamada registrada',
                $rate < Attendance::MINIMUM_RATE => "{$absences} faltas · abaixo do mínimo de 75%",
                default => $absences === 0 ? 'Nenhuma falta' : "{$absences} faltas ({$justified} justificadas)",
            })
            ->descriptionIcon($rate !== null && $rate < Attendance::WARNING_RATE ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedHandRaised)
            ->color(Attendance::colorForRate($rate));
    }

    /**
     * Compara a média das últimas três notas com a das três anteriores.
     *
     * @param  list<float>  $scores
     */
    private function trendDescription(array $scores): string
    {
        if (count($scores) < 4) {
            return 'Poucas notas para indicar tendência';
        }

        $recent = array_sum(array_slice($scores, -3)) / 3;
        $previous = array_sum(array_slice($scores, -6, 3)) / count(array_slice($scores, -6, 3));
        $difference = round($recent - $previous, 1);

        return match (true) {
            $difference >= 0.5 => 'Em alta nas últimas avaliações (+'.Grade::formatScore($difference).')',
            $difference <= -0.5 => 'Em queda nas últimas avaliações ('.Grade::formatScore($difference).')',
            default => 'Estável nas últimas avaliações',
        };
    }
}
