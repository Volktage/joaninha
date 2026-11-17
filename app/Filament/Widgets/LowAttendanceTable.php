<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Students\StudentResource;
use App\Filament\Tables\Columns\InitialsAvatarColumn;
use App\Filament\Widgets\Concerns\HasSkeletonPlaceholder;
use App\Models\Attendance;
use App\Models\Student;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alunos com frequência abaixo de 85%: quem está perto (ou abaixo) do mínimo legal de 75%.
 */
class LowAttendanceTable extends TableWidget
{
    use HasSkeletonPlaceholder;

    protected static ?string $heading = 'Alunos com baixa frequência';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Student::query()
                ->visibleTo(auth()->user())
                ->with('classroom')
                ->withAttendanceRate()
                ->withCount(['attendances as absences_count' => fn (Builder $query) => $query->whereIn('status', ['absent', 'justified'])])
                ->whereIn('students.id', Attendance::query()
                    ->select('student_id')
                    ->groupBy('student_id')
                    ->havingRaw("100 * SUM(status IN ('present', 'late')) / COUNT(*) < ?", [Attendance::WARNING_RATE])))
            ->description('Frequência abaixo de 85%. O mínimo para aprovação no Fundamental é 75% (LDB, art. 24, VI).')
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('attendance_rate'))
            ->paginated([5, 10])
            ->columns([
                InitialsAvatarColumn::make(),
                TextColumn::make('name')
                    ->label('Aluno')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Student $record): string => $record->classroom->name),
                TextColumn::make('attendance_rate')
                    ->label('Frequência')
                    ->badge()
                    ->color(fn ($state): string => Attendance::colorForRate((float) $state))
                    ->formatStateUsing(fn ($state): string => Attendance::formatRate((float) $state)),
                TextColumn::make('absences_count')
                    ->label('Faltas')
                    ->alignCenter(),
                TextColumn::make('guardian_name')
                    ->label('Responsável')
                    ->description(fn (Student $record): ?string => $record->guardian_phone)
                    ->icon(Heroicon::OutlinedPhone)
                    ->placeholder('—'),
            ])
            ->recordUrl(fn (Student $record): string => StudentResource::getUrl('edit', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->emptyStateHeading('Frequência em dia')
            ->emptyStateDescription('Nenhum aluno abaixo de 85% de presença.');
    }
}
