<?php

namespace Tests\Feature\Sales;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WhatsAppMessageType;
use App\Livewire\Sales\ReceiptDelivery;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentPaymentService;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_result_offers_print_pdf_and_whatsapp_delivery(): void
    {
        [$actor, $sale] = $this->paidSale();
        $this->actingAs($actor);

        Livewire::test(ReceiptDelivery::class)
            ->dispatch('sale-paid', saleId: $sale->id)
            ->assertSet('show', true)
            ->assertSet('saleId', $sale->id)
            ->assertSee('Pago completado')
            ->assertSee($sale->folio)
            ->assertSee('Imprimir ahora')
            ->assertSee('Enviar por WhatsApp')
            ->assertSee('Descargar PDF');
    }

    public function test_whatsapp_action_reuses_existing_ticket_message_instead_of_duplicating_it(): void
    {
        [$actor, $sale] = $this->paidSale();
        $this->actingAs($actor);
        $before = $sale->whatsappMessages()
            ->where('type', WhatsAppMessageType::Ticket->value)
            ->count();

        Livewire::test(ReceiptDelivery::class)
            ->call('open', $sale->id)
            ->call('sendWhatsApp')
            ->assertSet('deliveryMessage', 'El ticket ya fue enviado por WhatsApp.')
            ->assertSee('El ticket ya fue enviado por WhatsApp.');

        $this->assertSame($before, $sale->whatsappMessages()
            ->where('type', WhatsAppMessageType::Ticket->value)
            ->count());
    }

    public function test_print_action_can_open_the_browser_print_dialog_automatically(): void
    {
        [$actor, $sale] = $this->paidSale();

        $this->actingAs($actor)
            ->get(route('sales.ticket.print', $sale).'?autoprint=1')
            ->assertOk()
            ->assertSee('window.print()', false);
    }

    /** @return array{User, Sale} */
    private function paidSale(): array
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

        return [$actor, $sale];
    }
}
