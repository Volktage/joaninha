<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Grade;
use Filament\Widgets\ChartWidget;

class GradeDistributionChart extends ChartWidget
{
    use HasSkeletonPlaceholder;

    protected ?string $heading = 'Distribuição das notas';

    protected ?string $description = 'Todas as notas lançadas no Fundamental I.';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $bands = [
            'Abaixo de 5' => [0, Grade::LOW_SCORE, '#ef4444'],
            '5 a 6,9' => [Grade::LOW_SCORE, Grade::GOOD_SCORE, '#f59e0b'],
            '7 a 8,9' => [Grade::GOOD_SCORE, 9.0, '#34d399'],
            '9 a 10' => [9.0, 10.01, '#059669'],
        ];

        $counts = collect($bands)->map(fn (array $band): int => Grade::query()->visibleTo(auth()->user())
            ->where('score', '>=', $band[0])
            ->where('score', '<', $band[1])
            ->count());

        return [
            'datasets' => [
                [
                    'label' => 'Notas',
                    'data' => $counts->values()->all(),
                    'backgroundColor' => collect($bands)->pluck(2)->all(),
                    'borderWidth' => 0,
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => array_keys($bands),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle'],
                ],
            ],
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
