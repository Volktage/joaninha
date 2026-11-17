<?php

namespace App\Filament\Resources\Classrooms\Pages;

use App\Filament\Pages\TakeAttendance;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Classrooms\Tables\ClassroomsTable;
use App\Filament\Resources\Classrooms\Widgets\ClassroomOverview;
use App\Filament\Resources\Concerns\OpensReadOnlyForViewers;
use App\Models\Classroom;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditClassroom extends EditRecord
{
    use OpensReadOnlyForViewers;

    protected static string $resource = ClassroomResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getRelationManagersContentComponent(),
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    public function getHeading(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClassroomOverview::class,
        ];
    }

    public function getSubheading(): string
    {
        /** @var Classroom $classroom */
        $classroom = $this->getRecord();

        return collect([
            $classroom->stage?->getLabel(),
            $classroom->period?->getLabel(),
            "Ano letivo {$classroom->year}",
            $classroom->homeroomTeacher ? "Regente: {$classroom->homeroomTeacher->name}" : null,
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReportCards')
                ->label('Boletins da turma')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('filament.admin.report-cards.classroom', ['classroom' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Action::make('takeAttendance')
                ->label('Fazer chamada')
                ->icon(Heroicon::OutlinedHandRaised)
                ->url(fn (): string => TakeAttendance::getUrl(['turma' => $this->getRecord()->getKey()])),
            DeleteAction::make()
                ->modalDescription(ClassroomsTable::DELETE_WARNING),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->onlyWhenEditable([
            $this->getSaveFormAction()
                ->label('Salvar Alterações'),
            $this->getCancelFormAction()
                ->label('Cancelar'),
        ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Turma atualizada!';
    }
}
