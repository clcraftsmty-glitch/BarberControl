<?php

namespace App\Services;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\URL;

class WhatsAppNotificationService
{
    public function __construct(private WhatsAppPhoneNormalizer $phones) {}

    public function confirmation(Appointment $appointment, ?User $actor = null): WhatsAppMessage
    {
        $appointment->loadMissing(['client', 'barber', 'service']);

        return $this->queue(
            WhatsAppMessageType::Confirmation,
            'appointment:'.$appointment->id.':confirmation',
            $appointment->client,
            config('whatsapp.templates.confirmation'),
            $this->appointmentParameters($appointment),
            $appointment,
            null,
            $actor,
        );
    }

    public function reminder(Appointment $appointment, WhatsAppMessageType $type): WhatsAppMessage
    {
        if (! in_array($type, [WhatsAppMessageType::Reminder24Hours, WhatsAppMessageType::Reminder2Hours], true)) {
            throw new \InvalidArgumentException('Tipo de recordatorio no válido.');
        }

        $appointment->loadMissing(['client', 'barber', 'service']);
        $template = $type === WhatsAppMessageType::Reminder24Hours
            ? config('whatsapp.templates.reminder_24h')
            : config('whatsapp.templates.reminder_2h');

        return $this->queue(
            $type,
            'appointment:'.$appointment->id.':'.$type->value.':'.$appointment->starts_at->format('YmdHi'),
            $appointment->client,
            $template,
            $this->appointmentParameters($appointment),
            $appointment,
        );
    }

    public function cancellation(Appointment $appointment, ?User $actor = null): WhatsAppMessage
    {
        $appointment->loadMissing(['client', 'barber', 'service']);

        return $this->queue(
            WhatsAppMessageType::Cancellation,
            'appointment:'.$appointment->id.':cancellation',
            $appointment->client,
            config('whatsapp.templates.cancellation'),
            $this->appointmentParameters($appointment),
            $appointment,
            null,
            $actor,
        );
    }

    public function rescheduled(Appointment $appointment, ?User $actor = null): WhatsAppMessage
    {
        $appointment->loadMissing(['client', 'barber', 'service']);

        return $this->queue(
            WhatsAppMessageType::Rescheduled,
            'appointment:'.$appointment->id.':rescheduled:'.$appointment->starts_at->format('YmdHi'),
            $appointment->client,
            config('whatsapp.templates.rescheduled'),
            $this->appointmentParameters($appointment),
            $appointment,
            null,
            $actor,
        );
    }

    public function ticket(Sale $sale, ?User $actor = null): WhatsAppMessage
    {
        $sale->loadMissing(['client', 'appointment']);
        $url = $this->ticketUrl($sale);
        $parameters = [
            $sale->client_name_snapshot,
            $sale->folio,
            '$'.number_format((float) $sale->total, 2),
        ];

        return $this->queue(
            WhatsAppMessageType::Ticket,
            'sale:'.$sale->id.':ticket',
            $sale->client,
            config('whatsapp.templates.ticket'),
            $parameters,
            $sale->appointment,
            $sale,
            $actor,
            [[
                'type' => 'header',
                'parameters' => [[
                    'type' => 'document',
                    'document' => [
                        'link' => $url,
                        'filename' => 'ticket-'.$sale->folio.'.pdf',
                    ],
                ]],
            ]],
        );
    }

    public function retry(WhatsAppMessage $message, User $actor): WhatsAppMessage
    {
        $message->loadMissing(['client', 'sale']);
        $recipient = $this->phones->normalize($message->client->phone);

        if (! $message->client->whatsapp_opt_in || ! $recipient) {
            $message->update([
                'status' => WhatsAppMessageStatus::Skipped,
                'last_error' => ! $message->client->whatsapp_opt_in
                    ? 'El cliente no autorizó notificaciones por WhatsApp.'
                    : 'El teléfono no tiene un formato internacional válido.',
                'initiated_by' => $actor->id,
            ]);

            return $message->refresh();
        }

        $payload = $message->payload;
        $payload['to'] = $recipient;

        if ($message->type === WhatsAppMessageType::Ticket && $message->sale) {
            data_set(
                $payload,
                'template.components.0.parameters.0.document.link',
                $this->ticketUrl($message->sale),
            );
        }

        $message->update([
            'status' => WhatsAppMessageStatus::Pending,
            'recipient' => $recipient,
            'payload' => $payload,
            'failed_at' => null,
            'last_error' => null,
            'initiated_by' => $actor->id,
        ]);
        SendWhatsAppMessage::dispatch($message->id)->afterCommit();

        return $message->refresh();
    }

    /** @return array<int, string> */
    private function appointmentParameters(Appointment $appointment): array
    {
        return [
            $appointment->client->full_name,
            $appointment->service->name,
            $appointment->starts_at->format('d/m/Y'),
            $appointment->starts_at->format('H:i'),
            $appointment->barber->display_name,
        ];
    }

    private function ticketUrl(Sale $sale): string
    {
        return URL::temporarySignedRoute(
            'sales.ticket.whatsapp',
            now()->addMinutes(config('whatsapp.ticket_url_minutes', 60)),
            ['sale' => $sale],
        );
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<int, array<string, mixed>>  $extraComponents
     */
    private function queue(
        WhatsAppMessageType $type,
        string $deduplicationKey,
        Client $client,
        string $template,
        array $parameters,
        ?Appointment $appointment = null,
        ?Sale $sale = null,
        ?User $actor = null,
        array $extraComponents = [],
    ): WhatsAppMessage {
        $recipient = $this->phones->normalize($client->phone);
        $body = [
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                $parameters,
            ),
        ];
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => config('whatsapp.language', 'es_MX')],
                'components' => [...$extraComponents, $body],
            ],
        ];

        $message = WhatsAppMessage::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            [
                'client_id' => $client->id,
                'appointment_id' => $appointment?->id,
                'sale_id' => $sale?->id,
                'type' => $type,
                'template_name' => $template,
                'recipient' => $recipient ?? preg_replace('/\D+/', '', $client->phone),
                'payload' => $payload,
                'status' => $client->whatsapp_opt_in && $recipient
                    ? WhatsAppMessageStatus::Pending
                    : WhatsAppMessageStatus::Skipped,
                'scheduled_at' => now(),
                'last_error' => ! $client->whatsapp_opt_in
                    ? 'El cliente no autorizó notificaciones por WhatsApp.'
                    : ($recipient ? null : 'El teléfono no tiene un formato internacional válido.'),
                'initiated_by' => $actor?->id,
            ],
        );

        if ($message->wasRecentlyCreated && $message->status === WhatsAppMessageStatus::Pending) {
            SendWhatsAppMessage::dispatch($message->id)->afterCommit();
        }

        return $message;
    }
}
