<?php

namespace App\Filament\Resources\StaffMembers\Tables;

use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Filament\Resources\StaffMembers\StaffMemberResource;
use App\Filament\Tables\Columns\InitialsAvatarColumn;
use App\Models\StaffMember;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): string => StaffMemberResource::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['homeroomClassrooms', 'classrooms', 'assistedClassrooms'])
                ->withCount('exams'))
            ->columns([
                InitialsAvatarColumn::make(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->weight(FontWeight::Medium)
                    ->description(fn (StaffMember $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Cargo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('specialty')
                    ->label('Área')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('classrooms_list')
                    ->label('Turmas')
                    ->state(fn (StaffMember $record): array => $record->isTeacher() || $record->isAssistant() ? $record->allClassrooms()->pluck('name')->all() : [])
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->limitList(3)
                    ->expandableLimitedList(),
                IconColumn::make('user_id')
                    ->label('Acesso')
                    ->state(fn (StaffMember $record): bool => $record->hasSystemAccess())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedKey)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (StaffMember $record): string => $record->hasSystemAccess() ? 'Pode entrar no painel' : 'Sem acesso ao painel')
                    ->alignCenter(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->icon(Heroicon::OutlinedPhone)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exams_count')
                    ->label('Avaliações')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hired_at')
                    ->label('Na escola desde')
                    ->date('m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("FIELD(role, '".implode("','", array_map(fn (StaffRole $role): string => $role->value, StaffRole::cases()))."')")
                ->orderBy('name'))
            ->filters([
                SelectFilter::make('specialty')
                    ->label('Área')
                    ->options(TeacherSpecialty::class),
                TernaryFilter::make('system_access')
                    ->label('Acesso ao sistema')
                    ->trueLabel('Com acesso')
                    ->falseLabel('Sem acesso')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('user_id'),
                        false: fn (Builder $query) => $query->whereNull('user_id'),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize('view')
                    ->label('Ficha')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->iconButton(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('Ninguém nesta categoria')
            ->emptyStateDescription('Adicione direção, coordenação, secretaria e professores à equipe.');
    }
}
