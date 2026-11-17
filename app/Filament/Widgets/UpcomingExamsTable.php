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

class UpcomingExamsTable extends TableWidget
{
    use HasSkeletonPlaceholder;

    protected static ?string $heading = 'Próximas avaliações';

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Exam::query()->visibleTo(auth()->user())->scheduled()->with('classroom'))
            ->description('Agenda das avaliações e relatórios ainda por aplicar.')
            ->defaultSort('applied_at')
            ->paginated([5])
            ->columns([
                TextColumn::make('title')
                    ->label('Avaliação')
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                TextColumn::make('classroom.name')
                    ->label('Turma')
                    ->badge()
                    ->color(fn (Exam $record): string => $record->classroom->stage?->getColor() ?? 'gray'),
                TextColumn::make('applied_at')
                    ->label('Data')
                    ->date('d/m')
                    ->description(fn (Exam $record): string => $record->appliedAtRelativeLabel()),
            ])
            ->recordUrl(fn (Exam $record): string => ExamResource::getUrl('edit', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('Nenhuma avaliação agendada')
            ->emptyStateDescription('Cadastre as próximas avaliações para acompanhar a agenda aqui.');
    }
}
