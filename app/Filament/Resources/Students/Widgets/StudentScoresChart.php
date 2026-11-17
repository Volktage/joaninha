<?php

namespace App\Filament\Resources\Students\Widgets;

use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Grade;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

/**
 * Notas do aluno ao longo do ano, comparadas com a média da turma em cada avaliação.
 */
class StudentScoresChart extends ChartWidget
{
    use HasSkeletonPlaceholder;

    public ?Student $record = null;

    protected ?string $heading = 'Evolução das notas';

    protected ?string $description = 'Nota do aluno em cada avaliação, comparada à média da turma.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return true;
    }

    protected function getData(): array
    {
        $grades = $this->record->grades()
            ->whereNotNull('score')
            ->with(['exam' => fn ($query) => $query->withAvg('grades', 'score')])
            ->get()
            ->sortBy('exam.applied_at')
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Nota do aluno',
                    'data' => $grades->pluck('score')->all(),
                    'borderColor' => '#e11d48',
                    'backgroundColor' => '#e11d4833',
                    'pointBackgroundColor' => $grades->map(fn (Grade $grade): string => match (Grade::colorForScore($grade->score)) {
                        'success' => '#10b981',
                        'warning' => '#f59e0b',
                        default => '#ef4444',
                    })->all(),
                    'pointRadius' => 5,
                    'pointHoverRadius' => 8,
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Média da turma',
                    'data' => $grades->map(fn (Grade $grade): float => round((float) $grade->exam->grades_avg_score, 1))->all(),
                    'borderColor' => '#a8a29e',
                    'borderDash' => [6, 6],
                    'pointRadius' => 0,
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $grades->map(fn (Grade $grade): string => $grade->exam->applied_at->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle']],
            ],
            'scales' => [
                'y' => ['min' => 0, 'max' => 10, 'ticks' => ['stepSize' => 2]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
