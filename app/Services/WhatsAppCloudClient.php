<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppCloudClient
{
    public function send(WhatsAppMessage $message): string
    {
        if (config('whatsapp.driver') === 'log') {
            Log::info('WhatsApp simulado', [
                'message_id' => $message->id,
                'recipient' => $message->recipient,
                'payload' => $message->payload,
            ]);

            return 'log-'.$message->id.'-'.now()->format('YmdHisv');
        }

        $phoneNumberId = config('whatsapp.phone_number_id');
        $accessToken = config('whatsapp.access_token');

        if (! $phoneNumberId || ! $accessToken) {
            throw new RuntimeException('Faltan WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_ACCESS_TOKEN.');
        }

        $endpoint = rtrim(config('whatsapp.graph_url'), '/')
            .'/'.trim(config('whatsapp.graph_version'), '/')
            .'/'.$phoneNumberId.'/messages';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300)
            ->post($endpoint, $message->payload);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Meta rechazó el mensaje de WhatsApp.');
        }

        $providerId = $response->json('messages.0.id');

        if (! $providerId) {
            throw new RuntimeException('Meta no devolvió el identificador del mensaje.');
        }

        return $providerId;
    }
}
