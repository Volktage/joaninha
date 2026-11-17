<?php

namespace App\Filament\Widgets;

use App\Enums\EducationStage;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Classroom;
use App\Models\Grade;
use Filament\Widgets\ChartWidget;

class AverageByClassroomChart extends ChartWidget
{
    use HasSkeletonPlaceholder;

    protected ?string $heading = 'Média ponderada por turma';

    protected ?string $description = 'Fundamental I, considerando o peso de cada avaliação.';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    /** @var array<string, string> */
    private const array BAR_COLORS = [
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#ef4444',
        'gray' => '#a8a29e',
    ];

    protected function getData(): array
    {
        $classrooms = Classroom::query()->visibleTo(auth()->user())
            ->where('stage', EducationStage::Elementary)
            ->where('year', (string) now()->year)
            ->orderBy('id')
            ->get();

        $averages = $classrooms->map(function (Classroom $classroom): ?float {
            $totals = Grade::query()->visibleTo(auth()->user())
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->where('exams.classroom_id', $classroom->id)
                ->whereNotNull('grades.score')
                ->selectRaw('SUM(grades.score * exams.weight) AS weighted_sum, SUM(exams.weight) AS weight_sum')
                ->first();

            return $totals?->weight_sum > 0 ? round($totals->weighted_sum / $totals->weight_sum, 2) : null;
        });

        $colors = $averages->map(fn (?float $average): string => self::BAR_COLORS[Grade::colorForScore($average)]);

        return [
            'datasets' => [
                [
                    'label' => 'Média ponderada',
                    'data' => $averages->all(),
                    'backgroundColor' => $colors->map(fn (string $color): string => $color.'cc')->all(),
                    'borderColor' => $colors->all(),
                    'borderWidth' => 2,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'maxBarThickness' => 56,
                ],
            ],
            'labels' => $classrooms->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 10,
                    'ticks' => ['stepSize' => 2],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
