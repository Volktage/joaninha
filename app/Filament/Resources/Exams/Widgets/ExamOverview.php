<?php

namespace App\Filament\Resources\Exams\Widgets;

use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Exam;
use App\Models\Grade;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Resumo da avaliação exibido acima do diário de classe.
 */
class ExamOverview extends StatsOverviewWidget
{
    use HasSkeletonPlaceholder;

    public ?Exam $record = null;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['md' => 2, 'xl' => 4];

    /**
     * Mantém os números em dia quando o professor lança notas direto no diário.
     *
     * @var array<string, string>
     */
    protected $listeners = ['refresh-exam-overview' => '$refresh'];

    protected function getStats(): array
    {
        $exam = $this->record;
        $total = $exam->grades()->count();
        $filled = $total - $exam->grades()->blank()->count();
        $status = $exam->status();

        $progress = Stat::make('Lançamentos', "{$filled}/{$total}")
            ->description($status->getLabel())
            ->descriptionIcon($status->getIcon())
            ->color($status->getColor());

        if (! $exam->classroom->isGraded()) {
            return [
                $progress,
                Stat::make('Turma', $exam->classroom->name)
                    ->description('Relatório descritivo, sem nota')
                    ->descriptionIcon(Heroicon::OutlinedPuzzlePiece)
                    ->color('warning'),
            ];
        }

        $scores = $exam->grades()->whereNotNull('score')->pluck('score');
        $average = $scores->isEmpty() ? null : (float) $scores->avg();
        $goodShare = $scores->isEmpty() ? 0 : round($scores->filter(fn (float $score): bool => $score >= Grade::GOOD_SCORE)->count() / $scores->count() * 100);

        return [
            $progress,
            Stat::make('Média da turma', Grade::formatScore($average))
                ->description('Peso '.number_format($exam->weight, 1, ',', '.').' na média ponderada')
                ->descriptionIcon(Heroicon::OutlinedScale)
                ->color(Grade::colorForScore($average)),
            Stat::make('Maior / menor nota', Grade::formatScore($scores->max()).' / '.Grade::formatScore($scores->min()))
                ->description($scores->isEmpty() ? 'Nenhuma nota lançada' : "{$scores->count()} notas lançadas")
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('info'),
            Stat::make('Notas iguais ou acima de 7', $scores->isEmpty() ? '—' : "{$goodShare}%")
                ->description($scores->isEmpty()
                    ? 'Aguardando lançamentos'
                    : 'Abaixo de 5: '.$scores->filter(fn (float $score): bool => $score < Grade::LOW_SCORE)->count().' aluno(s)')
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->color(match (true) {
                    $scores->isEmpty() => 'gray',
                    $goodShare >= 50 => 'success',
                    default => 'warning',
                }),
        ];
    }
}
