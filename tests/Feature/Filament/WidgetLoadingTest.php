<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\AverageByClassroomChart;
use App\Filament\Widgets\GradeDistributionChart;
use App\Filament\Widgets\LowAttendanceTable;
use App\Filament\Widgets\PerformanceTrendChart;
use App\Filament\Widgets\StatsOverview;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Enquanto os widgets carregam, o painel mostra um esqueleto com o título e o formato do conteúdo.
 */
class WidgetLoadingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Widget>, string, string|null}>
     */
    public static function widgets(): array
    {
        return [
            'bar chart' => [AverageByClassroomChart::class, 'joaninha-chart-area', 'Média ponderada por turma'],
            'doughnut chart' => [GradeDistributionChart::class, 'rounded-full', 'Distribuição das notas'],
            'line chart' => [PerformanceTrendChart::class, 'joaninha-chart-area-tall', 'Evolução das médias'],
            'stats' => [StatsOverview::class, 'xl:grid-cols-4', null],
            'table' => [LowAttendanceTable::class, 'h-9 w-9', 'Alunos com baixa frequência'],
        ];
    }

    /**
     * @param  class-string<Widget>  $widget
     */
    #[DataProvider('widgets')]
    public function test_lazy_widgets_render_a_skeleton_with_their_heading(string $widget, string $shape, ?string $heading): void
    {
        $this->actingAs(User::factory()->create());

        $html = app($widget)->placeholder()->render();

        $this->assertStringContainsString('joaninha-widget-skeleton', $html);
        $this->assertStringContainsString($shape, $html);
        $this->assertStringContainsString('Carregando', $html);

        if ($heading !== null) {
            $this->assertStringContainsString($heading, $html);
        }
    }

    public function test_panel_registers_the_chart_ready_plugin_before_charts_load(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('window.filamentChartJsGlobalPlugins', escape: false)
            ->assertSee("setAttribute('data-chart-ready', 'true')", escape: false);
    }

    public function test_charts_fill_a_fixed_height_area_instead_of_keeping_aspect_ratio(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([AverageByClassroomChart::class, GradeDistributionChart::class, PerformanceTrendChart::class] as $chart) {
            Livewire::test($chart)
                ->assertSuccessful()
                ->assertSeeHtml('maintainAspectRatio')
                ->assertDontSeeHtml('fi-wi-chart-canvas-ctn-no-aspect-ratio');
        }
    }
}
