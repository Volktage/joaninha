<?php

namespace App\Filament\Resources\StaffMembers\Schemas;

use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Models\StaffMember;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class StaffMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados pessoais')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->helperText('Também é o login, quando o acesso ao sistema está liberado.')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules([
                                fn (?StaffMember $record) => Rule::unique('users', 'email')->ignore($record?->user_id),
                            ])
                            ->validationMessages(['unique' => 'Este e-mail já está em uso por outra pessoa.'])
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedEnvelope),
                        TextInput::make('phone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->placeholder('(11) 99999-9999')
                            ->prefixIcon(Heroicon::OutlinedPhone),
                        DatePicker::make('hired_at')
                            ->label('Na escola desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(today()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Cargo')
                    ->description('O cargo define o que a pessoa pode ver e fazer no sistema.')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->schema([
                        ToggleButtons::make('role')
                            ->label('Cargo')
                            ->options(StaffRole::class)
                            ->default(StaffRole::Teacher)
                            ->helperText(fn (Get $get): ?string => static::role($get('role'))?->getDescription())
                            ->inline()
                            ->live()
                            ->required(),
                        ToggleButtons::make('specialty')
                            ->label('Área de atuação')
                            ->options(TeacherSpecialty::class)
                            ->default(TeacherSpecialty::Pedagogy)
                            ->helperText('Regentes são vinculadas às turmas no cadastro da turma; especialistas podem atender várias turmas.')
                            ->inline()
                            ->visible(fn (Get $get): bool => static::role($get('role')) === StaffRole::Teacher)
                            ->required(fn (Get $get): bool => static::role($get('role')) === StaffRole::Teacher),
                    ])
                    ->columnSpanFull(),
                Section::make('Acesso ao sistema')
                    ->description('Com o acesso liberado, a pessoa entra no painel com o e-mail acima e as permissões do cargo.')
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        Toggle::make('system_access')
                            ->label('Liberar acesso ao painel')
                            ->live(),
                        TextInput::make('password')
                            ->label(fn (?StaffMember $record): string => $record?->hasSystemAccess() ? 'Nova senha' : 'Senha inicial')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->helperText(fn (?StaffMember $record): string => $record?->hasSystemAccess()
                                ? 'Deixe em branco para manter a senha atual.'
                                : 'Mínimo de 8 caracteres. A pessoa pode trocá-la depois na página de Perfil.')
                            ->required(fn (Get $get, ?StaffMember $record): bool => (bool) $get('system_access') && ! $record?->hasSystemAccess())
                            ->visible(fn (Get $get): bool => (bool) $get('system_access')),
                    ])
                    ->columns(2)
                    ->visible(fn (): bool => (bool) auth()->user()?->canManageStaff())
                    ->columnSpanFull(),
            ]);
    }

    public static function role(mixed $state): ?StaffRole
    {
        return $state instanceof StaffRole ? $state : StaffRole::tryFrom((string) $state);
    }
}
