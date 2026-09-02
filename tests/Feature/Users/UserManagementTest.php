<?php

namespace Tests\Feature\Users;

use App\Enums\SaleStatus;
use App\Enums\UserAccessEvent;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Livewire\Users\Index;
use App\Models\CashRegisterSession;
use App\Models\CommissionSettlement;
use App\Models\Sale;
use App\Models\User;
use App\Services\UserAccessLogger;
use App\Services\UserAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_receptionist_with_granular_permissions(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);

        Livewire::test(Index::class)
            ->call('openCreate')
            ->set('name', 'Recepción Principal')
            ->set('email', 'recepcion@barbercontrol.test')
            ->set('role', UserRole::Receptionist->value)
            ->set('permissions', [UserPermission::CancelSales->value])
            ->set('password', 'Temporal123!')
            ->set('password_confirmation', 'Temporal123!')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Recepción Principal');

        $user = User::query()->where('email', 'recepcion@barbercontrol.test')->firstOrFail();
        $this->assertSame(UserRole::Receptionist, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Temporal123!', $user->password));
        $this->assertContains(UserPermission::CancelSales->value, $user->permissions);
        $this->assertContains(UserPermission::ViewFinancialInformation->value, $user->permissions);
    }

    public function test_administrator_account_always_has_every_permission(): void
    {
        $administrator = User::factory()->create([
            'role' => UserRole::Administrator,
            'permissions' => [],
        ]);

        foreach (UserPermission::cases() as $permission) {
            $this->assertTrue($administrator->hasPermission($permission));
        }
    }

    public function test_receptionist_permissions_control_financial_sales_cash_and_commissions(): void
    {
        $receptionist = User::factory()->create([
            'role' => UserRole::Receptionist,
            'permissions' => [],
        ]);
        $sale = new Sale(['status' => SaleStatus::Completed]);
        $sale->created_by = User::factory()->create()->id;
        $cashSession = new CashRegisterSession;

        $this->assertFalse($receptionist->can('viewAny', Sale::class));
        $this->assertFalse($receptionist->can('cancel', $sale));
        $this->assertFalse($receptionist->can('viewAny', CashRegisterSession::class));
        $this->assertFalse($receptionist->can('adjust', $cashSession));
        $this->assertFalse($receptionist->can('create', CommissionSettlement::class));

        $receptionist->update(['permissions' => array_column(UserPermission::cases(), 'value')]);
        $receptionist->refresh();

        $this->assertTrue($receptionist->can('viewAny', Sale::class));
        $this->assertTrue($receptionist->can('cancel', $sale));
        $this->assertTrue($receptionist->can('viewAny', CashRegisterSession::class));
        $this->assertTrue($receptionist->can('adjust', $cashSession));
        $this->assertTrue($receptionist->can('create', CommissionSettlement::class));
    }

    public function test_suspension_closes_sessions_and_reactivation_restores_access(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        DB::table('sessions')->insert([
            'id' => 'active-session',
            'user_id' => $receptionist->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
        $service = app(UserAdministrationService::class);

        $service->suspend($receptionist, 'Baja temporal autorizada', $administrator);

        $this->assertFalse($receptionist->refresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'active-session']);
        $this->assertDatabaseHas('user_access_logs', [
            'user_id' => $receptionist->id,
            'actor_id' => $administrator->id,
            'event' => UserAccessEvent::Suspended->value,
        ]);

        $service->reactivate($receptionist, $administrator);
        $this->assertTrue($receptionist->refresh()->is_active);
        $this->assertNull($receptionist->suspended_at);
        $this->assertDatabaseHas('user_access_logs', [
            'user_id' => $receptionist->id,
            'event' => UserAccessEvent::Reactivated->value,
        ]);
    }

    public function test_administrator_resets_password_and_invalidates_sessions(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        DB::table('sessions')->insert([
            'id' => 'password-session',
            'user_id' => $receptionist->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        app(UserAdministrationService::class)->resetPassword($receptionist, 'NuevaClave123!', $administrator);

        $this->assertTrue(Hash::check('NuevaClave123!', $receptionist->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'password-session']);
        $this->assertDatabaseHas('user_access_logs', [
            'user_id' => $receptionist->id,
            'actor_id' => $administrator->id,
            'event' => UserAccessEvent::PasswordReset->value,
        ]);
    }

    public function test_administrator_cannot_suspend_own_account(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);

        try {
            app(UserAdministrationService::class)->suspend($administrator, 'Intento de autosuspensión', $administrator);
            $this->fail('La autosuspensión debió ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('suspension_reason', $exception->errors());
        }

        $this->assertTrue($administrator->refresh()->is_active);
    }

    public function test_access_history_can_be_searched_and_filtered(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create([
            'name' => 'Recepción Historial',
            'role' => UserRole::Receptionist,
        ]);
        app(UserAccessLogger::class)->record(UserAccessEvent::Login, $receptionist);
        app(UserAccessLogger::class)->record(UserAccessEvent::FailedLogin, email: 'fallido@barber.test');
        $this->actingAs($administrator);

        Livewire::test(Index::class)
            ->call('switchTab', 'access')
            ->assertSee('Recepción Historial')
            ->assertSee('Intento fallido')
            ->set('eventFilter', UserAccessEvent::Login->value)
            ->assertSee('Recepción Historial')
            ->assertDontSee('fallido@barber.test');
    }

    public function test_only_administrator_can_access_user_management(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get(route('users.index'))->assertForbidden();
    }
}
