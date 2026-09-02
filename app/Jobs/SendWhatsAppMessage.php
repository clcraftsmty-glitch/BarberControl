<?php

namespace App\Jobs;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppCloudClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppCloudClient $client): void
    {
        $message = WhatsAppMessage::query()->with('client')->find($this->messageId);

        if (! $message || ! in_array($message->status, [WhatsAppMessageStatus::Pending, WhatsAppMessageStatus::Failed], true)) {
            return;
        }

        if (! $message->client->whatsapp_opt_in) {
            $message->update([
                'status' => WhatsAppMessageStatus::Skipped,
                'last_error' => 'El cliente retiró su autorización de WhatsApp.',
            ]);

            return;
        }

        $message->increment('attempts');

        try {
            $providerId = $client->send($message->fresh());
            $message->update([
                'status' => WhatsAppMessageStatus::Sent,
                'provider_message_id' => $providerId,
                'sent_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $message->update([
                'status' => WhatsAppMessageStatus::Failed,
                'failed_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }
}
