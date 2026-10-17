<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaseMessage;
use App\Models\CasePhoto;
use App\Models\PatientCase;
use App\Models\Treatment;
use App\Models\User;
use App\Services\CaseChatWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $messages = $patientCase->messages()
            ->with(['author', 'photos'])
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($messages->isNotEmpty() && $request->user()->role() !== User::ROLE_ADMIN) {
            $patientCase->messages()
                ->whereIn('id', $messages->pluck('id'))
                ->where('user_id', '!=', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'messages' => $messages->map(fn (CaseMessage $message) => $this->formatMessage($message, $request->user()->id)),
            'last_id' => $messages->last()?->id ?? $afterId,
        ]);
    }

    public function store(Request $request, PatientCase $patientCase, CaseChatWebhook $webhook): JsonResponse
    {
        $this->ensureParticipant($request, $patientCase);
        $data = $request->validate([
            'detail' => ['nullable', 'string', 'max:5000', 'required_without:photos'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'detail.required_without' => 'Write a message or attach at least one photo.',
            'photos.max' => 'A message can include up to 10 photos.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, or WebP images.',
        ]);

        if (trim((string) ($data['detail'] ?? '')) === '' && $request->file('photos', []) === []) {
            throw ValidationException::withMessages(['detail' => 'Write a message or attach at least one photo.']);
        }

        $storedPaths = [];
        try {
            $message = DB::transaction(function () use ($data, $request, $patientCase, &$storedPaths): CaseMessage {
                $message = CaseMessage::query()->create([
                    'detail' => trim((string) ($data['detail'] ?? '')),
                    'case_id' => $patientCase->id,
                    'user_id' => $request->user()->id,
                    'created_at' => now(),
                ]);

                foreach ($request->file('photos', []) as $index => $photo) {
                    $extension = strtolower($photo->extension() ?: 'jpg');
                    $filename = Str::uuid()->toString().'.'.$extension;
                    $path = $photo->storeAs('case-photos/'.$patientCase->id, $filename, 'local');
                    if ($path === false) throw new \RuntimeException('A chat photo could not be stored.');
                    $storedPaths[] = $path;

                    CasePhoto::query()->create([
                        'filename' => $filename,
                        'case_id' => $patientCase->id,
                        'query_id' => $message->id,
                        'user_id' => $request->user()->id,
                        'created_at' => now(),
                        'storage_path' => $path,
                        'storage_disk' => 'local',
                        'mime_type' => $photo->getMimeType(),
                        'size_bytes' => $photo->getSize(),
                        'sort_order' => $index,
                        'caption' => 'Chat attachment',
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

        return response()->json(['message' => $this->formatMessage($message, $request->user()->id)], 201);
    }

    public function prescribe(Request $request, PatientCase $patientCase): RedirectResponse
    {
        abort_unless(
            $request->user()->role() === User::ROLE_DERMATOLOGIST
                && (int) $patientCase->assigned_to === (int) $request->user()->id,
            404
        );

        $data = $request->validate([
            'diagnosis' => ['required', 'string', 'max:1000'],
            'prevention' => ['nullable', 'string', 'max:1000'],
            'treatment' => ['required', 'string', 'max:1000'],
            'referral' => ['nullable', 'string', 'max:1000'],
        ]);

        Treatment::query()->create([
            'diagnosis' => trim($data['diagnosis']),
            'prevention' => trim((string) ($data['prevention'] ?? '')),
            'treatment' => trim($data['treatment']),
            'referral' => trim((string) ($data['referral'] ?? '')),
            'treatment_by' => $request->user()->id,
            'case_id' => $patientCase->id,
        ]);

        return redirect()->route('cases.show', $patientCase)->with('status', 'Clinical plan saved to the case.');
    }

    private function ensureReadable(Request $request, PatientCase $patientCase): void
    {
        $user = $request->user();
        $authorized = match ($user->role()) {
            User::ROLE_ADMIN => true,
            User::ROLE_DERMATOLOGIST => (int) $patientCase->assigned_to === (int) $user->id,
            User::ROLE_MEDICAL_OFFICER => (int) $patientCase->created_by === (int) $user->id,
            default => false,
        };

        abort_unless($authorized, 404);
    }

    private function ensureParticipant(Request $request, PatientCase $patientCase): void
    {
        $this->ensureReadable($request, $patientCase);
        abort_unless($request->user()->role() !== User::ROLE_ADMIN, 403);
    }

    private function formatMessage(CaseMessage $message, int $currentUserId): array
    {
        return [
            'id' => $message->id,
            'detail' => $message->detail,
            'author' => $message->author?->full_name ?? 'Care team member',
            'role' => $message->author?->role(),
            'is_mine' => (int) $message->user_id === $currentUserId,
            'created_at' => $message->created_at?->format('M j, Y · g:i A'),
            'photos' => $message->photos->map(fn (CasePhoto $photo) => [
                'id' => $photo->id,
                'url' => route('cases.photos.show', [$message->case_id, $photo->id]),
                'filename' => $photo->filename,
            ])->values(),
        ];
    }
}
