<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\User;
use App\Services\ErrorMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditAndErrorMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_model_changes_are_audited_without_secrets(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);
        $service = Service::factory()->create(['name' => 'Corte inicial']);
        $service->update(['name' => 'Corte auditado', 'price' => 350]);
        $administrator->update(['password' => 'nueva-clave-segura']);

        $audit = AuditLog::query()->where('auditable_type', Service::class)->where('auditable_id', $service->id)->where('action', 'updated')->latest()->firstOrFail();
        $this->assertSame('Corte inicial', $audit->before_values['name']);
        $this->assertSame('Corte auditado', $audit->after_values['name']);
        $userAudit = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $administrator->id)->where('action', 'updated')->latest()->firstOrFail();
        $this->assertArrayNotHasKey('password', $userAudit->after_values ?? []);
    }

    public function test_repeated_exceptions_are_grouped_for_monitoring(): void
    {
        $exception = new RuntimeException('Falla controlada de monitoreo');
        app(ErrorMonitor::class)->record($exception);
        app(ErrorMonitor::class)->record($exception);

        $this->assertDatabaseHas('system_errors', [
            'exception_class' => RuntimeException::class,
            'message' => 'Falla controlada de monitoreo',
            'occurrences' => 2,
        ]);
    }
}
