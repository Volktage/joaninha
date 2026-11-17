<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Concerns\OpensReadOnlyForViewers;
use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Widgets\ExamOverview;
use App\Models\Exam;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

class EditExam extends EditRecord
{
    use OpensReadOnlyForViewers;

    protected static string $resource = ExamResource::class;

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
        return $this->getRecord()->title;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExamOverview::class,
        ];
    }

    public function getSubheading(): string
    {
        /** @var Exam $exam */
        $exam = $this->getRecord();

        return collect([
            $exam->classroom->name,
            $exam->teacher ? "Prof. {$exam->teacher->name}" : null,
            $exam->applied_at->format('d/m/Y').' ('.mb_strtolower($exam->appliedAtRelativeLabel()).')',
            $exam->classroom->isGraded() ? 'Peso '.number_format($exam->weight, 1, ',', '.') : 'Relatório descritivo',
            $exam->status()->getLabel(),
        ])->filter()->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('As notas e relatórios lançados nesta avaliação também serão excluídos.'),
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
        return 'Avaliação atualizada!';
    }
}
