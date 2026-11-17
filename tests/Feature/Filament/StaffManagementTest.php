<?php

namespace Tests\Feature\Filament;

use App\Enums\StaffRole;
use App\Filament\Resources\StaffMembers\Pages\CreateStaffMember;
use App\Filament\Resources\StaffMembers\Pages\EditStaffMember;
use App\Filament\Resources\StaffMembers\Pages\ListStaffMembers;
use App\Models\StaffMember;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Equipe Escolar: cadastro com cargo e concessão de acesso ao painel pela Direção.
 */
class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(StaffMember::factory()->role(StaffRole::Direction)->withLogin()->create()->user);
    }

    public function test_direction_registers_a_coordinator_with_system_access(): void
    {
        Livewire::test(CreateStaffMember::class)
            ->fillForm([
                'name' => 'Helena Prado Martins',
                'email' => 'helena@escolajoaninha.com.br',
                'role' => StaffRole::Coordination->value,
                'system_access' => true,
                'password' => 'SenhaSegura123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staffMember = StaffMember::query()->where('email', 'helena@escolajoaninha.com.br')->firstOrFail();
        $this->assertSame(StaffRole::Coordination, $staffMember->role);
        $this->assertNull($staffMember->specialty);
        $this->assertTrue($staffMember->hasSystemAccess());
        $this->assertTrue(Auth::validate(['email' => 'helena@escolajoaninha.com.br', 'password' => 'SenhaSegura123']));
        $this->assertSame(StaffRole::Coordination, $staffMember->user->role());
    }

    public function test_password_is_required_when_granting_access(): void
    {
        Livewire::test(CreateStaffMember::class)
            ->fillForm(['name' => 'Fulana', 'email' => 'fulana@escolajoaninha.com.br', 'role' => StaffRole::Secretary->value, 'system_access' => true])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);
    }

    public function test_email_cannot_collide_with_an_existing_login(): void
    {
        User::factory()->create(['email' => 'ocupado@escolajoaninha.com.br']);

        Livewire::test(CreateStaffMember::class)
            ->fillForm(['name' => 'Outra', 'email' => 'ocupado@escolajoaninha.com.br', 'role' => StaffRole::Secretary->value])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_teacher_specialty_is_cleared_when_role_changes(): void
    {
        $staffMember = StaffMember::factory()->create();

        Livewire::test(EditStaffMember::class, ['record' => $staffMember->getRouteKey()])
            ->fillForm(['role' => StaffRole::Secretary->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(StaffRole::Secretary, $staffMember->fresh()->role);
        $this->assertNull($staffMember->fresh()->specialty);
    }

    public function test_revoking_access_and_deleting_staff_remove_the_login(): void
    {
        $withAccess = StaffMember::factory()->withLogin()->create();
        $userId = $withAccess->user_id;

        Livewire::test(EditStaffMember::class, ['record' => $withAccess->getRouteKey()])
            ->assertSchemaStateSet(['system_access' => true])
            ->fillForm(['system_access' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($withAccess->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['id' => $userId]);

        $another = StaffMember::factory()->withLogin()->create();
        $anotherUserId = $another->user_id;

        Livewire::test(EditStaffMember::class, ['record' => $another->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($another);
        $this->assertDatabaseMissing('users', ['id' => $anotherUserId]);
    }

    public function test_staff_list_has_one_tab_per_role(): void
    {
        $teacher = StaffMember::factory()->create();
        $secretary = StaffMember::factory()->role(StaffRole::Secretary)->create();

        Livewire::test(ListStaffMembers::class)
            ->set('activeTab', StaffRole::Secretary->value)
            ->assertCanSeeTableRecords([$secretary])
            ->assertCanNotSeeTableRecords([$teacher])
            ->set('activeTab', StaffRole::Teacher->value)
            ->assertCanSeeTableRecords([$teacher])
            ->assertCanNotSeeTableRecords([$secretary]);
    }

    public function test_non_direction_staff_cannot_manage_the_team(): void
    {
        $this->actingAs(StaffMember::factory()->role(StaffRole::Coordination)->withLogin()->create()->user);
        $staffMember = StaffMember::factory()->create();

        $this->get('/admin/staff/create')->assertForbidden();
        $this->get("/admin/staff/{$staffMember->id}/edit")->assertOk()->assertDontSee('Salvar Alterações')->assertDontSee('Liberar acesso ao painel');
    }
}
