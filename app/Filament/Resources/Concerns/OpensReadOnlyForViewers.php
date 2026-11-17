<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

/**
 * Permite abrir a página de edição em modo somente leitura para quem pode ver o registro, mas não editá-lo
 * (ex.: professores na própria turma). O formulário fica desabilitado, os botões de salvar somem e o
 * salvamento continua exigindo a permissão de edição.
 *
 * @mixin EditRecord
 */
trait OpensReadOnlyForViewers
{
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function canEditRecord(): bool
    {
        return static::getResource()::canEdit($this->getRecord());
    }

    public function form(Schema $schema): Schema
    {
        return static::getResource()::form($schema)->disabled(! $this->canEditRecord());
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless($this->canEditRecord(), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    public function getContentTabLabel(): ?string
    {
        return $this->canEditRecord() ? 'Editar' : 'Dados';
    }

    /**
     * @param  array<Action>  $actions
     * @return array<Action>
     */
    protected function onlyWhenEditable(array $actions): array
    {
        return $this->canEditRecord() ? $actions : [];
    }
}
