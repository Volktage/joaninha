<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Exam;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingGradesTable extends TableWidget
{
    use HasSkeletonPlaceholder;

    protected static ?string $heading = 'Lançamentos pendentes';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Exam::query()->visibleTo(auth()->user())->awaitingGrades()->with('classroom')->withGradingProgress())
            ->description('Avaliações já aplicadas com alunos ainda sem nota ou relatório.')
            ->defaultSort('applied_at')
            ->paginated([5])
            ->columns([
                TextColumn::make('title')
                    ->label('Avaliação')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Exam $record): string => $record->classroom->name)
                    ->wrap(),
                TextColumn::make('applied_at')
                    ->label('Aplicada')
                    ->date('d/m')
                    ->description(fn (Exam $record): string => $record->appliedAtRelativeLabel()),
                TextColumn::make('blank_grades_count')
                    ->label('Faltam')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
            ])
            ->recordUrl(fn (Exam $record): string => ExamResource::getUrl('edit', ['record' => $record, 'relation' => '0']))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->emptyStateHeading('Tudo em dia!')
            ->emptyStateDescription('Nenhuma avaliação aplicada está com lançamentos pendentes.');
    }
}
