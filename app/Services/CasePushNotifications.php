<?php

namespace App\Services;

use App\Models\CaseMessage;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Http\HttpClientOptions;
use Throwable;

class CasePushNotifications
{
    private ?Messaging $messaging = null;

    public function caseAssigned(PatientCase $case): void
    {
        $doctor = $case->assignedDoctor;
        if (! $doctor) return;

        $this->sendToUser(
            $doctor,
            'New case assigned',
            'A new patient case has been assigned to you.',
            'case_assigned',
            $case->id,
        );
    }

    public function chatMessageCreated(CaseMessage $message): void
    {
        $message->loadMissing(['patientCase', 'author']);
        $case = $message->patientCase;
        if (! $case) return;

        $recipientIds = collect([$case->created_by, $case->assigned_to])
            ->filter(fn ($id): bool => $id !== null && (int) $id !== (int) $message->user_id)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) return;

        User::query()->whereIn('id', $recipientIds)->get()->each(function (User $recipient) use ($case): void {
            $this->sendToUser(
                $recipient,
                'New case message',
                'You have a new message about a patient case.',
                'case_chat',
                $case->id,
            );
        });
    }

    private function sendToUser(User $user, string $title, string $body, string $type, int $caseId): void
    {
        $tokens = $user->fcmDeviceTokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
        if ($tokens->isEmpty()) return;

        try {
            $messaging = $this->messaging();
            if (! $messaging) return;

            foreach ($tokens as $device) {
                $message = CloudMessage::new()
                    ->withToken($device->token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData(['type' => $type, 'case_id' => (string) $caseId])
                    ->withAndroidConfig(AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => ['channel_id' => 'case_updates'],
                    ]));

                try {
                    $messaging->send($message);
                } catch (NotFound $exception) {
                    $device->delete();
                    Log::info('Removed an invalid SkinSewa FCM device token.', [
                        'user_id' => $user->id,
                        'case_id' => $caseId,
                    ]);
                } catch (SenderIdMismatch $exception) {
                    Log::error('SkinSewa FCM token belongs to a different Firebase project.', [
                        'user_id' => $user->id,
                        'case_id' => $caseId,
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('SkinSewa case push delivery failed.', [
                        'user_id' => $user->id,
                        'case_id' => $caseId,
                        'event' => $type,
                        'exception' => $exception::class,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('SkinSewa Firebase messaging is unavailable.', [
                'user_id' => $user->id,
                'case_id' => $caseId,
                'event' => $type,
                'exception' => $exception::class,
            ]);
        }
    }

    private function messaging(): ?Messaging
    {
        if ($this->messaging) return $this->messaging;

        $credentials = (string) config('services.firebase.credentials', '');
        if ($credentials === '' || ! is_file($credentials) || ! is_readable($credentials)) {
            Log::warning('Firebase credentials are not configured; case push notification was skipped.');
            return null;
        }

        $httpOptions = HttpClientOptions::default()->withConnectTimeout(3)->withTimeout(8);
        return $this->messaging = (new Factory())
            ->withServiceAccount($credentials)
            ->withHttpClientOptions($httpOptions)
            ->createMessaging();
    }
}
