<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Concerns\OpensReadOnlyForViewers;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Students\Widgets\StudentOverview;
use App\Filament\Resources\Students\Widgets\StudentScoresChart;
use App\Models\Grade;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EditStudent extends EditRecord
{
    use OpensReadOnlyForViewers;

    protected static string $resource = StudentResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        /** @var Student $student */
        $student = $this->getRecord();

        return $schema
            ->components([
                Callout::make('Atenção à saúde')
                    ->description(fn (): string => collect([
                        $student->allergies ? "Alergia: {$student->allergies}." : null,
                        $student->health_notes,
                    ])->filter()->implode(' '))
                    ->icon(Heroicon::OutlinedHeart)
                    ->danger()
                    ->visible(fn (): bool => $student->hasHealthAlert()),
                Callout::make('Educação Especial (AEE)')
                    ->description(fn (): string => collect([
                        $student->special_education?->getLabel().'.',
                        $student->special_education_notes,
                    ])->filter()->implode(' '))
                    ->icon(Heroicon::OutlinedLifebuoy)
                    ->info()
                    ->visible(fn (): bool => $student->isSpecialEducation()),
                Callout::make('Responsável')
                    ->description(fn (): string => collect([
                        $student->guardian_name.($student->guardian_relationship ? " ({$student->guardian_relationship->getLabel()})" : ''),
                        $student->guardian_phone,
                        $student->guardian_email,
                    ])->filter()->implode(' · '))
                    ->icon(Heroicon::OutlinedPhone)
                    ->info()
                    ->visible(fn (): bool => filled($student->guardian_name)),
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
        /** @var Student $student */
        $student = $this->getRecord();

        return $student->classroom->isGraded()
            ? [StudentOverview::class, StudentScoresChart::class]
            : [StudentOverview::class];
    }

    public function getSubheading(): Htmlable
    {
        /** @var Student $student */
        $student = $this->getRecord();
        $average = $student->weightedAverage();

        $summary = collect([
            $student->classroom->name,
            "Matrícula {$student->registration_number}",
            $student->birth_date ? $student->age().' anos (nasc. '.$student->birth_date->format('d/m/Y').')' : null,
            $student->classroom->isGraded() && $average !== null ? 'Média ponderada '.Grade::formatScore($average) : null,
        ])->filter()->implode(' · ');

        $badges = array_filter([
            $student->hasHealthAlert() ? ['danger', Heroicon::Heart, $student->allergies ? "Alergia: {$student->allergies}" : 'Observação de saúde'] : null,
            $student->isSpecialEducation() ? ['info', Heroicon::Lifebuoy, 'AEE: '.$student->special_education->getLabel()] : null,
        ]);

        if ($badges === []) {
            return new HtmlString(e($summary));
        }

        return new HtmlString(e($summary).Blade::render(
            '<div class="mt-2 flex flex-wrap gap-2">@foreach ($badges as [$color, $icon, $label])<x-filament::badge :color="$color" :icon="$icon">{{ $label }}</x-filament::badge>@endforeach</div>',
            ['badges' => $badges],
        ));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReportCard')
                ->label('Imprimir boletim')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('filament.admin.report-cards.student', ['student' => $this->getRecord()]))
                ->openUrlInNewTab(),
            DeleteAction::make()
                ->modalDescription('As notas e relatórios deste aluno também serão excluídos.'),
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
        return 'Dados do aluno atualizados!';
    }
}
