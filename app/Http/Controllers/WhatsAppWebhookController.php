<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        abort_unless(
            $mode === 'subscribe'
            && filled(config('whatsapp.verify_token'))
            && hash_equals((string) config('whatsapp.verify_token'), (string) $token),
            403,
        );

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        $this->validateSignature($request);

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach (data_get($change, 'value.statuses', []) as $statusData) {
                    $this->updateStatus($statusData);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function validateSignature(Request $request): void
    {
        $secret = config('whatsapp.app_secret');

        if (! $secret) {
            return;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256')), 403);
    }

    /** @param array<string, mixed> $data */
    private function updateStatus(array $data): void
    {
        $message = WhatsAppMessage::query()
            ->where('provider_message_id', $data['id'] ?? null)
            ->first();

        if (! $message) {
            return;
        }

        $status = $data['status'] ?? null;
        $at = isset($data['timestamp'])
            ? Carbon::createFromTimestamp((int) $data['timestamp'])
            : now();

        if ($status === 'sent') {
            $message->sent_at ??= $at;
            if (! in_array($message->status, [WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::Read], true)) {
                $message->status = WhatsAppMessageStatus::Sent;
            }
        } elseif ($status === 'delivered') {
            $message->delivered_at ??= $at;
            if ($message->status !== WhatsAppMessageStatus::Read) {
                $message->status = WhatsAppMessageStatus::Delivered;
            }
        } elseif ($status === 'read') {
            $message->read_at ??= $at;
            $message->status = WhatsAppMessageStatus::Read;
        } elseif ($status === 'failed' && ! in_array($message->status, [WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::Read], true)) {
            $message->status = WhatsAppMessageStatus::Failed;
            $message->failed_at = $at;
            $message->last_error = data_get($data, 'errors.0.title')
                ?? data_get($data, 'errors.0.message')
                ?? 'Meta informó que el mensaje falló.';
        }

        $message->save();
    }
}
