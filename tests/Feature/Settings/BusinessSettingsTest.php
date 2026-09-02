<?php

namespace Tests\Feature\Settings;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Settings\Business;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\AppointmentScheduler;
use App\Services\AppointmentWorkflow;
use App\Services\CashRegisterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_access_business_settings(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($administrator)->get(route('settings.business'))->assertOk();
        $this->actingAs($receptionist)->get(route('settings.business'))->assertForbidden();
    }

    public function test_administrator_updates_identity_financial_schedule_and_booking_rules(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);

        Livewire::test(Business::class)
            ->set('business_name', 'Barbería Central')
            ->set('legal_name', 'Barbería Central MX, S.A. de C.V.')
            ->set('tax_id', 'BCM260718AB1')
            ->set('address', 'Av. Principal 123, Monterrey')
            ->set('phones', ['8111111111', '8122222222'])
            ->set('general_schedule.monday.enabled', true)
            ->set('general_schedule.monday.start', '09:00')
            ->set('general_schedule.monday.end', '19:00')
            ->set('currency_code', 'USD')
            ->set('currency_symbol', 'US$')
            ->set('tax_name', 'IVA')
            ->set('tax_rate', '16')
            ->set('prices_include_tax', true)
            ->set('ticket_header', 'Cortes y estilo')
            ->set('ticket_footer', 'Gracias por elegirnos')
            ->set('timezone', 'America/Monterrey')
            ->set('default_appointment_duration_minutes', 45)
            ->set('arrival_tolerance_minutes', 15)
            ->set('cancellation_notice_hours', 4)
            ->set('minimum_booking_notice_minutes', 60)
            ->set('maximum_booking_advance_days', 90)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Configuración de la barbería actualizada.');

        $settings = BusinessSetting::current();
        $this->assertSame('Barbería Central', $settings->business_name);
        $this->assertSame(['8111111111', '8122222222'], $settings->phones);
        $this->assertSame('09:00', $settings->general_schedule['monday']['start']);
        $this->assertSame('USD', $settings->currency_code);
        $this->assertSame('16.00', $settings->tax_rate);
        $this->assertSame(45, $settings->default_appointment_duration_minutes);
        $this->assertSame(15, $settings->arrival_tolerance_minutes);
        $this->assertSame($administrator->id, $settings->updated_by);
    }

    public function test_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($administrator);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        Livewire::test(Business::class)
            ->set('logo', UploadedFile::fake()->createWithContent('logo.png', $png))
            ->call('save')
            ->assertHasNoErrors();

        $settings = BusinessSetting::current();
        $this->assertNotNull($settings->logo_path);
        $this->assertStringStartsWith('/branding/logo?v=', $settings->logoUrl());
        Storage::disk('public')->assertExists($settings->logo_path);
        $this->get($settings->logoUrl())
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="h-16 w-16 object-contain"', false)
            ->assertSee($settings->logoUrl(), false);

        Livewire::test(Business::class)->call('removeLogo');
        Storage::disk('public')->assertMissing($settings->logo_path);
        $this->assertNull(BusinessSetting::current()->logo_path);
    }

    public function test_business_hours_and_minimum_notice_are_schedule_warnings_with_admin_override(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 08:00:00');
        $settings = BusinessSetting::current();
        $schedule = $settings->general_schedule;
        $schedule['monday'] = ['enabled' => true, 'start' => '10:00', 'end' => '17:00'];
        $settings->update([
            'general_schedule' => $schedule,
            'minimum_booking_notice_minutes' => 180,
        ]);
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $client = Client::factory()->create();
        $barber->services()->attach($service);
        $data = [
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => '2026-07-20 09:30',
            'price' => $service->price,
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ];

        try {
            app(AppointmentScheduler::class)->create($data, $receptionist);
            $this->fail('El horario general y la anticipación debieron impedir la cita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('schedule.business_hours', $exception->errors());
            $this->assertArrayHasKey('schedule.minimum_notice', $exception->errors());
        }

        $appointment = app(AppointmentScheduler::class)->create($data, $administrator, true);
        $this->assertSame('2026-07-20 09:30', $appointment->starts_at->format('Y-m-d H:i'));
    }

    public function test_ticket_uses_configured_business_currency_tax_and_contact_data(): void
    {
        Storage::fake('public');
        $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        Storage::disk('public')->put('business-logos/report.png', $logo);
        $settings = BusinessSetting::current();
        $settings->update([
            'business_name' => 'Navaja Norte',
            'address' => 'Calle Barbería 45',
            'phones' => ['8112345678'],
            'currency_code' => 'USD',
            'currency_symbol' => 'US$',
            'tax_name' => 'IVA',
            'tax_rate' => 16,
            'prices_include_tax' => true,
            'ticket_footer' => 'Vuelve pronto',
            'logo_path' => 'business-logos/report.png',
        ]);
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        app(CashRegisterService::class)->open(0, null, $actor);
        $appointment = Appointment::factory()->create([
            'price' => 116,
            'status' => AppointmentStatus::PendingPayment,
        ]);
        $sale = app(AppointmentPaymentService::class)->register($appointment, [
            'payment_method' => PaymentMethod::Cash->value,
        ], $actor);

        $this->actingAs($actor)
            ->get(route('sales.ticket.print', $sale))
            ->assertOk()
            ->assertSee('NAVAJA NORTE')
            ->assertSee('Calle Barbería 45')
            ->assertSee('8112345678')
            ->assertSee('US$116.00 USD')
            ->assertSee('IVA 16.00% incluido')
            ->assertSee('Vuelve pronto')
            ->assertSee('/branding/logo?v=', false);

        $pdf = $this->actingAs($actor)->get(route('sales.ticket.pdf', $sale));
        $this->assertStringContainsString('NAVAJA NORTE', $pdf->getContent());
        $this->assertStringContainsString('US$116.00 USD', $pdf->getContent());
        $this->assertStringContainsString('/Logo Do', $pdf->getContent());
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_late_cancellation_requires_administrator_according_to_configured_notice(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        BusinessSetting::current()->update(['cancellation_notice_hours' => 4]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $appointment = Appointment::factory()->create([
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(2)->addMinutes(30),
        ]);

        try {
            app(AppointmentWorkflow::class)->transition(
                $appointment,
                AppointmentStatus::Cancelled,
                $receptionist,
            );
            $this->fail('La cancelación tardía debió requerir autorización administrativa.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('4 horas', $exception->errors()['status'][0]);
        }

        $cancelled = app(AppointmentWorkflow::class)->transition(
            $appointment,
            AppointmentStatus::Cancelled,
            $administrator,
        );
        $this->assertSame(AppointmentStatus::Cancelled, $cancelled->status);
    }
}
