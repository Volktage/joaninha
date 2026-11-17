<?php

namespace App\Filament\Resources\StaffMembers\RelationManagers;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Tables\ExamsTable;
use App\Models\Exam;
use App\Models\StaffMember;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Avaliações aplicadas pelo(a) professor(a), com o andamento do lançamento de cada uma.
 */
class ExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    protected static ?string $title = 'Avaliações aplicadas';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var StaffMember $ownerRecord */
        return $ownerRecord->isTeacher();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->exams()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('classroom')->withGradingProgress())
            ->columns(ExamsTable::columns(showTeacher: false))
            ->defaultSort('applied_at', 'desc')
            ->filters(ExamsTable::filters(showTeacher: false))
            ->recordActions([
                Action::make('diary')
                    ->label('Diário')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Exam $record): string => ExamResource::getUrl('edit', ['record' => $record, 'relation' => '0'])),
            ])
            ->emptyStateHeading('Nenhuma avaliação aplicada');
    }
}
