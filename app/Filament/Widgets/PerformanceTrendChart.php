<?php

namespace App\Filament\Widgets;

use App\Enums\EducationStage;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Classroom;
use App\Models\Grade;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class PerformanceTrendChart extends ChartWidget
{
    use HasSkeletonPlaceholder;

    protected ?string $heading = 'Evolução das médias';

    protected ?string $description = 'Média mensal das notas de cada turma do Fundamental I.';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @var list<string> */
    private const array LINE_COLORS = ['#e11d48', '#f59e0b', '#0ea5e9', '#10b981', '#8b5cf6', '#78716c'];

    protected function getData(): array
    {
        $months = collect(range(6, 0))->map(fn (int $monthsAgo): CarbonImmutable => CarbonImmutable::today()->startOfMonth()->subMonths($monthsAgo));

        $classrooms = Classroom::query()->visibleTo(auth()->user())
            ->where('stage', EducationStage::Elementary)
            ->where('year', (string) now()->year)
            ->orderBy('id')
            ->get();

        $scores = Grade::query()->visibleTo(auth()->user())
            ->join('exams', 'exams.id', '=', 'grades.exam_id')
            ->whereIn('exams.classroom_id', $classrooms->modelKeys())
            ->whereNotNull('grades.score')
            ->where('exams.applied_at', '>=', $months->first())
            ->get(['exams.classroom_id', 'exams.applied_at', 'grades.score'])
            ->groupBy(fn (Grade $grade): string => $grade->classroom_id.'|'.CarbonImmutable::parse($grade->applied_at)->format('Y-m'));

        $datasets = $classrooms->values()->map(function (Classroom $classroom, int $index) use ($months, $scores): array {
            $color = self::LINE_COLORS[$index % count(self::LINE_COLORS)];

            return [
                'label' => $classroom->name,
                'data' => $months->map(function (CarbonImmutable $month) use ($classroom, $scores): ?float {
                    $monthScores = $scores->get($classroom->id.'|'.$month->format('Y-m'));

                    return $monthScores ? round($monthScores->avg('score'), 1) : null;
                })->all(),
                'borderColor' => $color,
                'backgroundColor' => $color.'22',
                'pointBackgroundColor' => $color,
                'pointRadius' => 4,
                'pointHoverRadius' => 7,
                'tension' => 0.35,
                'spanGaps' => true,
            ];
        });

        return [
            'datasets' => $datasets->all(),
            'labels' => $months->map(fn (CarbonImmutable $month): string => ucfirst($month->locale('pt_BR')->translatedFormat('M/y')))->all(),
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
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'pointStyle' => 'circle'],
                ],
            ],
            'scales' => [
                'y' => ['min' => 0, 'max' => 10, 'ticks' => ['stepSize' => 2]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
