<?php

namespace App\Services;

use App\Models\CaseMessage;
use App\Models\PatientCase;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CasePushNotifications
{
    private ?Client $httpClient = null;

    private ?string $accessToken = null;

    private ?string $projectId = null;

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
            foreach ($tokens as $device) {
                try {
                    $this->sendToFcm($device->token, $title, $body, $type, $caseId);
                } catch (RequestException $exception) {
                    $this->logFcmHttpError($exception, $user, $caseId, $type, $device->token);
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

    private function sendToFcm(string $deviceToken, string $title, string $body, string $type, int $caseId): void
    {
        $endpoint = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            rawurlencode($this->firebaseProjectId()),
        );

        $this->httpClient()->post($endpoint, [
            'headers' => ['Authorization' => 'Bearer '.$this->firebaseAccessToken()],
            'json' => [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => ['type' => $type, 'case_id' => (string) $caseId],
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => ['channel_id' => 'case_updates'],
                    ],
                ],
            ],
        ]);
    }

    private function logFcmHttpError(RequestException $exception, User $user, int $caseId, string $type, ?string $deviceToken): void
    {
        $response = $exception->getResponse();
        $payload = $response ? json_decode((string) $response->getBody(), true) : null;
        $error = is_array($payload) ? ($payload['error'] ?? []) : [];
        $details = is_array($error) ? ($error['details'] ?? []) : [];
        $firebaseError = null;

        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                $firebaseError = $detail['errorCode'] ?? null;
                break;
            }
        }

        if ($firebaseError === 'UNREGISTERED' && $deviceToken !== null) {
            $user->fcmDeviceTokens()->where('token', $deviceToken)->delete();
            Log::info('Removed an invalid SkinSewa FCM device token.', [
                'user_id' => $user->id,
                'case_id' => $caseId,
            ]);
            return;
        }

        $context = [
            'user_id' => $user->id,
            'case_id' => $caseId,
            'event' => $type,
            'http_status' => $response?->getStatusCode(),
            'firebase_error' => $firebaseError ?? (is_array($error) ? ($error['status'] ?? null) : null),
        ];

        if ($firebaseError === 'SENDER_ID_MISMATCH') {
            Log::error('SkinSewa FCM token belongs to a different Firebase project.', $context);
            return;
        }

        Log::warning('SkinSewa FCM HTTP v1 delivery failed.', $context);
    }

    private function firebaseAccessToken(): string
    {
        if ($this->accessToken !== null) return $this->accessToken;

        $credentials = (string) config('services.firebase.credentials', '');
        if ($credentials === '' || ! is_file($credentials) || ! is_readable($credentials)) {
            throw new RuntimeException('Firebase credentials are not configured or are not readable.');
        }

        $serviceAccount = json_decode((string) file_get_contents($credentials), true);
        if (! is_array($serviceAccount) || empty($serviceAccount['project_id'])) {
            throw new RuntimeException('Firebase service-account JSON is invalid or has no project_id.');
        }

        $auth = new ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging', $serviceAccount);
        $token = $auth->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google Auth did not return an FCM access token.');
        }

        $this->projectId = $serviceAccount['project_id'];
        return $this->accessToken = $accessToken;
    }

    private function firebaseProjectId(): string
    {
        if ($this->projectId !== null) return $this->projectId;

        $this->firebaseAccessToken();
        return $this->projectId ?? throw new RuntimeException('Firebase service-account project_id is missing.');
    }

    private function httpClient(): Client
    {
        return $this->httpClient ??= new Client(['connect_timeout' => 3, 'timeout' => 8]);
    }
}
