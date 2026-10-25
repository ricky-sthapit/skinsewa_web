<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CaseMessage;
use App\Models\CasePhoto;
use App\Models\PatientCase;
use App\Models\Treatment;
use App\Models\User;
use App\Services\CaseChatWebhook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaseConversationController extends Controller
{
    public function index(Request $request, PatientCase $patientCase): JsonResponse
    {
        $this->ensureReadable($request, $patientCase);
        $afterId = max(0, (int) $request->query('after_id', 0));
        $messages = $patientCase->messages()->with(['author', 'photos'])
            ->where('id', '>', $afterId)->orderBy('id')->limit(100)->get();

        if ($request->user()->role() !== User::ROLE_ADMIN && $messages->isNotEmpty()) {
            $patientCase->messages()->whereIn('id', $messages->pluck('id'))
                ->where('user_id', '<>', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => $messages->map(fn (CaseMessage $message) => $this->payload($message, $request->user()->id))->values(),
            'meta' => ['last_id' => $messages->last()?->id ?? $afterId],
        ]);
    }

    public function store(Request $request, PatientCase $patientCase, CaseChatWebhook $webhook): JsonResponse
    {
        $this->ensureParticipant($request, $patientCase);
        $data = $request->validate([
            'detail' => ['nullable', 'string', 'max:5000', 'required_without:photos'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        if (trim((string) ($data['detail'] ?? '')) === '' && ! $request->hasFile('photos')) {
            throw ValidationException::withMessages(['detail' => 'Write a message or attach at least one photo.']);
        }

        $storedPaths = [];
        try {
            $message = DB::transaction(function () use ($data, $request, $patientCase, &$storedPaths): CaseMessage {
                $message = CaseMessage::query()->create([
                    'detail' => trim((string) ($data['detail'] ?? '')),
                    'case_id' => $patientCase->id, 'user_id' => $request->user()->id, 'created_at' => now(),
                ]);
                foreach ($request->file('photos', []) as $index => $photo) {
                    $filename = Str::uuid()->toString().'.'.strtolower($photo->extension() ?: 'jpg');
                    $path = $photo->storeAs('case-photos/'.$patientCase->id, $filename, 'local');
                    if (! $path) throw new \RuntimeException('A chat photo could not be stored.');
                    $storedPaths[] = $path;
                    CasePhoto::query()->create([
                        'filename' => $filename, 'case_id' => $patientCase->id, 'query_id' => $message->id,
                        'user_id' => $request->user()->id, 'created_at' => now(), 'storage_path' => $path,
                        'storage_disk' => 'local', 'mime_type' => $photo->getMimeType(), 'size_bytes' => $photo->getSize(),
                        'sort_order' => $index, 'caption' => 'Chat attachment',
                        'sha256' => hash_file('sha256', $photo->getRealPath()),
                    ]);
                }
                return $message;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        $message->load(['author', 'photos']);
        $webhook->messageCreated($message);
        return response()->json(['data' => $this->payload($message, $request->user()->id)], 201);
    }

    public function prescribe(Request $request, PatientCase $patientCase): JsonResponse
    {
        abort_unless($request->user()->role() === User::ROLE_DERMATOLOGIST && (int) $patientCase->assigned_to === (int) $request->user()->id, 404);
        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:1000'],
            'treatment' => ['required', 'string', 'max:1000'],
            'prevention' => ['nullable', 'string', 'max:1000'],
            'referral' => ['nullable', 'string', 'max:1000'],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        $treatment = DB::transaction(function () use ($request, $patientCase, $data): Treatment {
            $treatment = Treatment::query()->create([
                'diagnosis' => trim($data['diagnosis']), 'treatment' => trim($data['treatment']),
                'prevention' => trim((string) ($data['prevention'] ?? '')),
                'referral' => trim((string) ($data['referral'] ?? '')),
                'follow_up_at' => $data['follow_up_at'] ?? null,
                'treatment_by' => $request->user()->id, 'case_id' => $patientCase->id,
            ]);
            $patientCase->forceFill(['status' => 'completed', 'closed_at' => now()])->save();
            return $treatment;
        });

        return response()->json([
            'message' => 'Treatment saved and case marked completed.',
            'data' => [
                'id' => $treatment->id, 'diagnosis' => $treatment->diagnosis,
                'treatment' => $treatment->treatment, 'prevention' => $treatment->prevention,
                'referral' => $treatment->referral, 'follow_up_at' => $treatment->follow_up_at,
                'case_status' => 'completed', 'created_at' => $treatment->created_at,
            ],
        ], 201);
    }

    private function ensureReadable(Request $request, PatientCase $case): void
    {
        $user = $request->user();
        $allowed = match ($user->role()) {
            User::ROLE_ADMIN => true,
            User::ROLE_DERMATOLOGIST => (int) $case->assigned_to === (int) $user->id,
            User::ROLE_MEDICAL_OFFICER => (int) $case->created_by === (int) $user->id,
            default => false,
        };
        abort_unless($allowed, 404);
    }

    private function ensureParticipant(Request $request, PatientCase $case): void
    {
        $this->ensureReadable($request, $case);
        abort_unless($request->user()->role() !== User::ROLE_ADMIN, 403);
    }

    private function payload(CaseMessage $message, int $currentUserId): array
    {
        return [
            'id' => $message->id, 'detail' => $message->detail,
            'author' => ['id' => $message->author?->id, 'name' => $message->author?->full_name, 'role' => $message->author?->role()],
            'is_mine' => (int) $message->user_id === $currentUserId, 'created_at' => $message->created_at,
            'photos' => $message->photos->map(fn (CasePhoto $photo) => [
                'id' => $photo->id, 'filename' => $photo->filename,
                'url' => route('api.v1.cases.photos.show', [$message->case_id, $photo->id]),
            ])->values(),
        ];
    }
}
