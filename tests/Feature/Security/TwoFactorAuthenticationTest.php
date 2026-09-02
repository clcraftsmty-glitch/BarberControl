<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Livewire\Security\TwoFactorChallenge;
use App\Livewire\Security\TwoFactorSetup;
use App\Models\User;
use App\Services\TotpService;
use App\Services\TwoFactorQrCode;
use App\Services\UserAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.two_factor.enabled' => true, 'security.two_factor.enforce_in_tests' => true]);
    }

    public function test_administrator_must_enroll_before_accessing_application(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator)->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));

        $component = Livewire::actingAs($administrator)->test(TwoFactorSetup::class);
        $secret = $component->get('secret');
        $component->set('password', 'password')
            ->set('code', app(TotpService::class)->code($secret))
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('completed', true)
            ->assertCount('recoveryCodes', 8);

        $administrator->refresh();
        $this->assertTrue($administrator->hasConfirmedTwoFactor());
        $this->assertAuthenticatedAs($administrator);
        $this->assertSame($administrator->id, session('two_factor_verified_for'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_enrollment_displays_a_locally_generated_qr_code(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);

        $component = Livewire::actingAs($administrator)->test(TwoFactorSetup::class)
            ->assertSee('Escanear código QR')
            ->assertSee('No puedo escanear el QR');

        $uri = app(TotpService::class)->provisioningUri($administrator, $component->get('secret'));
        $dataUri = app(TwoFactorQrCode::class)->dataUri($uri);
        $svg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $dataUri), true);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_confirmed_administrator_must_complete_challenge_and_can_use_recovery_code(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $administrator = User::factory()->create([
            'role' => UserRole::Administrator,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['AAAA1111-BBBB2222'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($administrator)->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));
        Livewire::actingAs($administrator)->test(TwoFactorChallenge::class)
            ->set('code', app(TotpService::class)->code($secret))
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame($administrator->id, session('two_factor_verified_for'));

        session()->forget('two_factor_verified_for');
        Livewire::actingAs($administrator)->test(TwoFactorChallenge::class)
            ->set('useRecoveryCode', true)
            ->set('recoveryCode', 'AAAA1111-BBBB2222')
            ->call('verify')
            ->assertHasNoErrors();
        $this->assertSame([], $administrator->refresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('user_access_logs', ['event' => 'codigo_recuperacion_usado']);
    }

    public function test_receptionist_does_not_require_second_factor(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $this->actingAs($receptionist)->get(route('dashboard'))->assertOk();
    }

    public function test_another_administrator_can_reset_lost_second_factor(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $target = User::factory()->create([
            'role' => UserRole::Administrator,
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
            'two_factor_recovery_codes' => ['AAAA1111-BBBB2222'],
            'two_factor_confirmed_at' => now(),
        ]);
        $this->actingAs($actor);

        app(UserAdministrationService::class)->resetTwoFactor($target, $actor);

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'two_factor_reset', 'actor_id' => $actor->id]);
    }
}
