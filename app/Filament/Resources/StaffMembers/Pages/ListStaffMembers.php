<?php

namespace App\Filament\Resources\StaffMembers\Pages;

use App\Enums\StaffRole;
use App\Filament\Resources\StaffMembers\StaffMemberResource;
use App\Models\StaffMember;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListStaffMembers extends ListRecords
{
    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Adicionar à equipe')
                ->icon(Heroicon::OutlinedUserPlus),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = StaffMember::query()->toBase()->selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');

        $tabs = ['all' => Tab::make('Todos')->icon(Heroicon::OutlinedUserGroup)->badge($counts->sum())];

        $groups = [
            StaffRole::Direction->value => ['Direção', [StaffRole::Direction, StaffRole::ViceDirection]],
            StaffRole::Coordination->value => ['Coordenação', [StaffRole::Coordination]],
            StaffRole::Secretary->value => ['Secretaria', [StaffRole::Secretary]],
            StaffRole::Teacher->value => ['Professores', [StaffRole::Teacher]],
            StaffRole::Assistant->value => ['Auxiliares', [StaffRole::Assistant]],
        ];

        foreach ($groups as $key => [$label, $roles]) {
            $tabs[$key] = Tab::make($label)
                ->icon($roles[0]->getIcon())
                ->badge(collect($roles)->sum(fn (StaffRole $role): int => (int) ($counts[$role->value] ?? 0)))
                ->badgeColor($roles[0]->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', $roles));
        }

        return $tabs;
    }
}
