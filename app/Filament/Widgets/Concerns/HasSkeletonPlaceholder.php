<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * Enquanto o widget carrega (os widgets do Filament são carregados depois da página), mostra um
 * esqueleto com o título real e o formato do conteúdo (barras, rosca, linhas, cartões ou tabela),
 * no lugar de um espaço vazio.
 *
 * @mixin Widget
 */
trait HasSkeletonPlaceholder
{
    public function placeholder(): View
    {
        return view('filament.admin.widget-skeleton', [
            ...$this->getPlaceholderData(),
            'variant' => $this->skeletonVariant(),
            'heading' => $this->skeletonHeading(),
            'description' => $this->skeletonDescription(),
        ]);
    }

    protected function skeletonVariant(): string
    {
        return match (true) {
            $this instanceof ChartWidget => $this->getType(),
            $this instanceof StatsOverviewWidget => 'stats',
            $this instanceof TableWidget => 'table',
            default => 'table',
        };
    }

    protected function skeletonHeading(): ?string
    {
        return match (true) {
            $this instanceof ChartWidget => (string) $this->getHeading(),
            $this instanceof TableWidget => static::$heading,
            default => null,
        };
    }

    protected function skeletonDescription(): ?string
    {
        return $this instanceof ChartWidget ? (string) $this->getDescription() : null;
    }
}
