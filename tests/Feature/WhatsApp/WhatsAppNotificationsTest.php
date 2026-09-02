<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Livewire\Clients\Create as CreateClient;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\AppointmentPaymentService;
use App\Services\AppointmentScheduler;
use App\Services\AppointmentWorkflow;
use App\Services\CashRegisterService;
use App\Services\WhatsAppNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'whatsapp.driver' => 'log',
            'whatsapp.default_country_code' => '52',
        ]);
    }

    public function test_client_can_give_and_withdraw_whatsapp_consent(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $this->actingAs($actor);

        Livewire::test(CreateClient::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'Pérez')
            ->set('form.phone', '55 1234 5678')
            ->set('form.whatsapp_opt_in', true)
            ->call('save')
            ->assertHasNoErrors();

        $client = Client::query()->where('phone', '55 1234 5678')->firstOrFail();
        $this->assertTrue($client->whatsapp_opt_in);
        $this->assertNotNull($client->whatsapp_opt_in_at);
        $this->assertNull($client->whatsapp_opt_out_at);

        $client->update([
            'whatsapp_opt_in' => false,
            'whatsapp_opt_in_at' => null,
            'whatsapp_opt_out_at' => now(),
        ]);

        $this->assertFalse($client->refresh()->whatsapp_opt_in);
        $this->assertNotNull($client->whatsapp_opt_out_at);
    }

    public function test_creating_appointment_sends_confirmation_and_normalizes_mexican_phone(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->whatsappOptedIn()->create(['phone' => '55 1234 5678']);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30, 'price' => 250]);
        $barber->services()->attach($service);
        $startsAt = now()->next('monday')->setTime(10, 0)->seconds(0);

        $appointment = app(AppointmentScheduler::class)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'price' => '250.00',
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ], $actor);

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame($appointment->id, $message->appointment_id);
        $this->assertSame(WhatsAppMessageType::Confirmation, $message->type);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('525512345678', $message->recipient);
        $this->assertStringStartsWith('log-', $message->provider_message_id);
    }

    public function test_notification_without_consent_is_recorded_as_skipped(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->create(['phone' => '5512345678', 'whatsapp_opt_in' => false]);
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);

        app(AppointmentScheduler::class)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => now()->next('monday')->setTime(11, 0)->seconds(0)->toDateTimeString(),
            'price' => '200.00',
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ], $actor);

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame(WhatsAppMessageStatus::Skipped, $message->status);
        $this->assertStringContainsString('no autorizó', $message->last_error);
        $this->assertSame(0, $message->attempts);
    }

    public function test_command_sends_24_hour_and_2_hour_reminders_without_duplicates(): void
    {
        $now = CarbonImmutable::parse('2026-07-20 09:00:00');
        $this->travelTo($now);

        foreach ([24, 2] as $hours) {
            $client = Client::factory()->whatsappOptedIn()->create();
            $startsAt = $now->addHours($hours);
            Appointment::factory()->for($client)->create([
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes(30),
                'status' => AppointmentStatus::Confirmed,
            ]);
        }

        $this->artisan('whatsapp:queue-reminders')->assertSuccessful();
        $this->artisan('whatsapp:queue-reminders')->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertDatabaseHas('whatsapp_messages', ['type' => WhatsAppMessageType::Reminder24Hours->value]);
        $this->assertDatabaseHas('whatsapp_messages', ['type' => WhatsAppMessageType::Reminder2Hours->value]);
    }

    public function test_cancellation_sends_appointment_notice(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->whatsappOptedIn()->create();
        $appointment = Appointment::factory()->for($client)->create(['status' => AppointmentStatus::Confirmed]);

        app(AppointmentWorkflow::class)->transition($appointment, AppointmentStatus::Cancelled, $actor);

        $message = WhatsAppMessage::query()->sole();
        $this->assertSame(WhatsAppMessageType::Cancellation, $message->type);
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame($actor->id, $message->initiated_by);
    }

    public function test_moving_appointment_sends_rescheduling_notice(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->whatsappOptedIn()->create();
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);
        $startsAt = now()->next('monday')->setTime(10, 0)->seconds(0);

        $appointment = app(AppointmentScheduler::class)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'price' => '250.00',
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ], $actor);

        app(AppointmentScheduler::class)->move(
            $appointment,
            $startsAt->addHour()->toDateTimeString(),
            $actor,
        );

        $this->assertDatabaseCount('whatsapp_messages', 2);
        $rescheduled = WhatsAppMessage::query()
            ->where('type', WhatsAppMessageType::Rescheduled->value)
            ->sole();
        $this->assertSame(WhatsAppMessageStatus::Sent, $rescheduled->status);
        $this->assertStringContainsString('1100', $rescheduled->deduplication_key);
    }

    public function test_payment_sends_ticket_with_a_valid_signed_pdf_url(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->whatsappOptedIn()->create();
        $barber = Barber::factory()->create();
        $service = Service::factory()->create();
        app(CashRegisterService::class)->open(0, null, $actor);
        $appointment = Appointment::factory()
            ->for($client)
            ->for($barber)
            ->for($service)
            ->create(['status' => AppointmentStatus::PendingPayment]);

        $sale = app(AppointmentPaymentService::class)->register($appointment, [
            'payment_method' => PaymentMethod::Cash->value,
        ], $actor);

        $message = WhatsAppMessage::query()->where('type', WhatsAppMessageType::Ticket->value)->sole();
        $document = $message->payload['template']['components'][0]['parameters'][0]['document'];
        $this->assertSame('ticket-'.$sale->folio.'.pdf', $document['filename']);

        $parts = parse_url($document['link']);
        $response = $this->get(($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : ''));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_meta_webhook_updates_delivery_and_read_history(): void
    {
        $message = WhatsAppMessage::query()->create([
            'client_id' => Client::factory()->whatsappOptedIn()->create()->id,
            'type' => WhatsAppMessageType::Confirmation,
            'template_name' => 'confirmacion',
            'deduplication_key' => 'test:webhook:1',
            'recipient' => '525512345678',
            'payload' => [],
            'status' => WhatsAppMessageStatus::Sent,
            'provider_message_id' => 'wamid.TEST123',
            'sent_at' => now(),
        ]);

        $this->postJson('/webhooks/whatsapp', $this->webhookPayload('wamid.TEST123', 'delivered'))
            ->assertOk();
        $this->assertSame(WhatsAppMessageStatus::Delivered, $message->refresh()->status);
        $this->assertNotNull($message->delivered_at);

        $this->postJson('/webhooks/whatsapp', $this->webhookPayload('wamid.TEST123', 'read'))
            ->assertOk();
        $this->assertSame(WhatsAppMessageStatus::Read, $message->refresh()->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_failed_recipient_can_be_retried_after_phone_is_corrected(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Receptionist]);
        $client = Client::factory()->whatsappOptedIn()->create(['phone' => '123']);
        $appointment = Appointment::factory()->for($client)->create();
        $message = app(WhatsAppNotificationService::class)->confirmation($appointment, $actor);
        $this->assertSame(WhatsAppMessageStatus::Skipped, $message->status);

        $client->update(['phone' => '5512345678']);
        $message = app(WhatsAppNotificationService::class)->retry($message, $actor);

        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('525512345678', $message->recipient);
        $this->assertSame('525512345678', $message->payload['to']);
    }

    public function test_meta_driver_posts_template_to_cloud_api(): void
    {
        config([
            'whatsapp.driver' => 'meta',
            'whatsapp.phone_number_id' => '123456789',
            'whatsapp.access_token' => 'test-token',
            'whatsapp.graph_version' => 'v23.0',
        ]);
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/messages' => Http::response([
                'messages' => [['id' => 'wamid.CLOUD123']],
            ]),
        ]);

        $actor = User::factory()->create(['role' => UserRole::Administrator]);
        $client = Client::factory()->whatsappOptedIn()->create();
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 30]);
        $barber->services()->attach($service);

        app(AppointmentScheduler::class)->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => now()->next('monday')->setTime(12, 0)->seconds(0)->toDateTimeString(),
            'price' => '250.00',
            'status' => AppointmentStatus::Pending->value,
            'notes' => null,
        ], $actor);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
            && $request['type'] === 'template'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.CLOUD123',
            'status' => WhatsAppMessageStatus::Sent->value,
        ]);
    }

    public function test_only_administration_and_reception_can_view_history(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $barber = User::factory()->create(['role' => UserRole::Barber]);

        $this->actingAs($receptionist)->get('/whatsapp')->assertOk();
        $this->actingAs($barber)->get('/whatsapp')->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function webhookPayload(string $providerId, string $status): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => $providerId,
                            'status' => $status,
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
