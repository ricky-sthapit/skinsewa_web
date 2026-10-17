<?php

namespace App\Services;

use App\Models\CaseMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CaseChatWebhook
{
    public function messageCreated(CaseMessage $message): void
    {
        $url = (string) config('services.chat_webhook.url', '');
        $secret = (string) config('services.chat_webhook.secret', '');
        if ($url === '' || $secret === '') return;

        $message->loadMissing(['author', 'photos']);
        $eventId = (string) Str::uuid();
        $timestamp = (string) now()->timestamp;
        $payload = [
            'id' => $eventId,
            'event' => 'case.message.created',
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'case_id' => $message->case_id,
                'message_id' => $message->id,
                'author_id' => $message->user_id,
                'author_name' => $message->author?->full_name,
                'message' => $message->detail,
                'attachment_count' => $message->photos->count(),
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) return;

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        try {
            Http::timeout(max(1, (int) config('services.chat_webhook.timeout', 5)))
                ->withHeaders([
                    'X-SkinSewa-Event' => $payload['event'],
                    'X-SkinSewa-Event-Id' => $eventId,
                    'X-SkinSewa-Timestamp' => $timestamp,
                    'X-SkinSewa-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url)
                ->throw();
        } catch (Throwable $exception) {
            Log::warning('SkinSewa case chat webhook delivery failed.', [
                'event_id' => $eventId,
                'case_id' => $message->case_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
