<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CasePhoto;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PatientCaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scope = $this->scope($request->user());
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = mb_substr(trim((string) $request->query('status', '')), 0, 32);
        $perPage = min(25, max(5, (int) $request->query('per_page', 10)));
        $cases = PatientCase::query()
            ->with(['patient', 'assignedDoctor', 'creator'])
            ->withCount('photos')
            ->when($scope, fn (Builder $query) => $query->where($scope[0], $scope[1]))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('id', ctype_digit($search) ? (int) $search : 0)
                    ->orWhereHas('patient', fn (Builder $patient) => $patient
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('patient_code', 'like', '%'.$search.'%'));
            }))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $cases->getCollection()->map(fn (PatientCase $case) => $this->caseSummary($case))->values(),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'available_statuses' => PatientCase::query()
                    ->when($scope, fn (Builder $query) => $query->where($scope[0], $scope[1]))
                    ->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
            ],
        ]);
    }

    public function show(Request $request, PatientCase $patientCase): JsonResponse
    {
        $this->ensureAccess($request, $patientCase);
        $patientCase->load([
            'patient', 'creator', 'assignedDoctor', 'followUpOf', 'photos',
            'messages.author', 'messages.photos', 'treatments.doctor',
        ]);
        $scope = $this->scope($request->user());
        $previousCases = $patientCase->patient->cases()
            ->where('id', '<>', $patientCase->id)
            ->when($scope, fn (Builder $query) => $query->where($scope[0], $scope[1]))
            ->with(['assignedDoctor', 'treatments.doctor'])
            ->latest('created_at')->get();

        return response()->json([
            'data' => [
                'case' => $this->caseSummary($patientCase),
                'patient' => $this->patientPayload($patientCase->patient),
                'health_worker' => $this->userSummary($patientCase->creator),
                'dermatologist' => $this->userSummary($patientCase->assignedDoctor),
                'photos' => $patientCase->photos->map(fn (CasePhoto $photo) => $this->photoPayload($patientCase, $photo))->values(),
                'messages' => $patientCase->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'detail' => $message->detail,
                    'created_at' => $message->created_at,
                    'author' => $this->userSummary($message->author),
                    'photos' => $message->photos->map(fn (CasePhoto $photo) => $this->photoPayload($patientCase, $photo))->values(),
                ])->values(),
                'treatments' => $patientCase->treatments->map(fn ($treatment) => [
                    'id' => $treatment->id,
                    'diagnosis' => $treatment->diagnosis,
                    'treatment' => $treatment->treatment,
                    'prevention' => $treatment->prevention,
                    'referral' => $treatment->referral,
                    'follow_up_at' => $treatment->follow_up_at,
                    'doctor' => $this->userSummary($treatment->doctor),
                    'created_at' => $treatment->created_at,
                ])->values(),
                'previous_cases' => $previousCases->map(fn (PatientCase $case) => $this->caseSummary($case, true))->values(),
                'permissions' => [
                    'can_chat' => $this->isParticipant($request->user(), $patientCase),
                    'can_prescribe' => $request->user()->clinicalRole() === User::ROLE_DERMATOLOGIST && (int) $patientCase->assigned_to === (int) $request->user()->id,
                ],
            ],
        ]);
    }

    public function lookupPatient(Request $request): JsonResponse
    {
        abort_unless($request->user()->clinicalRole() === User::ROLE_MEDICAL_OFFICER, 403);
        $data = $request->validate(['patient_code' => ['required', 'string', 'max:40']]);
        $patient = Patient::query()
            ->where('patient_code', Str::upper(trim($data['patient_code'])))
            ->where('created_by', $request->user()->id)
            ->whereHas('cases', fn (Builder $cases) => $cases->where('created_by', $request->user()->id))
            ->first();
        if (! $patient) return response()->json(['message' => 'Patient not found in your case history.'], 404);

        $cases = $patient->cases()->where('created_by', $request->user()->id)
            ->with(['assignedDoctor', 'treatments.doctor', 'photos'])
            ->latest('created_at')->get();

        return response()->json([
            'data' => [
                'patient' => $this->patientPayload($patient),
                'previous_cases' => $cases->map(fn (PatientCase $case) => $this->caseSummary($case, true))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->clinicalRole() === User::ROLE_MEDICAL_OFFICER, 403);
        $returning = $request->filled('patient_id');
        $requiredNewPatient = Rule::requiredIf(! $returning);
        $data = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'follow_up_of' => ['nullable', 'integer', 'exists:cases,id'],
            'patient_name' => [$requiredNewPatient, 'string', 'max:100'],
            'gender' => [$requiredNewPatient, 'in:Male,Female'],
            'age' => [$requiredNewPatient, 'integer', 'min:0', 'max:130'],
            'phc' => [$requiredNewPatient, 'string', 'max:50'],
            'province_id' => [$requiredNewPatient, 'integer', 'exists:places,id'],
            'district_id' => [$requiredNewPatient, 'integer', 'exists:places,id'],
            'municipality_id' => [$requiredNewPatient, 'integer', 'exists:places,id'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:100'],
            'weight' => ['nullable', 'numeric', 'gt:0', 'lt:500'],
            'finding' => ['required', 'string', 'max:10000'],
            'history' => ['required', 'string', 'max:10000'],
            'symptoms' => ['required', 'string', 'max:10000'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'consent' => ['accepted'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $errors = [];
        $patient = null;
        $province = $district = $municipality = null;
        if ($returning) {
            $patient = Patient::query()->whereKey($data['patient_id'])->where('created_by', $request->user()->id)
                ->whereHas('cases', fn (Builder $cases) => $cases->where('created_by', $request->user()->id))->first();
            if (! $patient) $errors['patient_id'] = 'Choose a patient from your case history.';
        } else {
            $province = Place::query()->whereKey($data['province_id'])->where('type', 'Province')->first();
            $district = Place::query()->whereKey($data['district_id'])->where('type', 'District')->where('parent', $data['province_id'])->first();
            $municipality = Place::query()->whereKey($data['municipality_id'])
                ->whereIn('type', ['Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'])
                ->where('parent', $data['district_id'])->first();
            if (! $province) $errors['province_id'] = 'Choose a valid province.';
            if (! $district) $errors['district_id'] = 'Choose a district in the selected province.';
            if (! $municipality) $errors['municipality_id'] = 'Choose a local level in the selected district.';
        }
        $doctor = User::query()->whereKey($data['assigned_to'])->where('user_type', User::ROLE_DERMATOLOGIST)->first();
        if (! $doctor) $errors['assigned_to'] = 'Choose an active dermatologist.';
        if ($request->filled('follow_up_of') && ! PatientCase::query()->whereKey($data['follow_up_of'])
            ->where('patient_id', $patient?->id)->where('created_by', $request->user()->id)->exists()) {
            $errors['follow_up_of'] = 'Choose a previous case for this patient.';
        }
        if ($errors) throw ValidationException::withMessages($errors);

        $storedDirectory = null;
        try {
            $case = DB::transaction(function () use ($data, $request, $patient, $province, $district, $municipality, $doctor, &$storedDirectory): PatientCase {
                $patientRecord = $patient;
                if (! $patientRecord) {
                    $patientRecord = Patient::query()->create([
                        'name' => trim($data['patient_name']), 'gender' => $data['gender'], 'age' => $data['age'],
                        'phc' => trim($data['phc']), 'province' => $province->name, 'district' => $district->name,
                        'municipality' => $municipality->name, 'phone' => isset($data['phone']) ? trim($data['phone']) : null,
                        'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
                        'weight' => $data['weight'] ?? null, 'created_by' => $request->user()->id,
                        'consent_recorded_at' => now(),
                    ]);
                    $patientRecord->forceFill(['patient_code' => $this->makePatientCode($province->name, $district->name, $municipality->name, $patientRecord->id)])->save();
                }

                $case = PatientCase::query()->create([
                    'phc' => $patientRecord->phc, 'finding' => trim($data['finding']), 'history' => trim($data['history']),
                    'symptoms' => trim($data['symptoms']), 'patient_id' => $patientRecord->id,
                    'follow_up_of' => $data['follow_up_of'] ?? null, 'created_by' => $request->user()->id,
                    'assigned_to' => $doctor->id, 'status' => 'awaiting_review', 'priority' => 'normal',
                    'consent_confirmed_at' => now(),
                ]);
                $storedDirectory = 'case-photos/'.$case->id;
                foreach ($request->file('photos', []) as $index => $photo) {
                    $filename = Str::uuid()->toString().'.'.strtolower($photo->extension() ?: 'jpg');
                    $path = $photo->storeAs($storedDirectory, $filename, 'local');
                    if (! $path) throw new \RuntimeException('A case photo could not be stored.');
                    CasePhoto::query()->create([
                        'filename' => $filename, 'case_id' => $case->id, 'user_id' => $request->user()->id,
                        'created_at' => now(), 'storage_path' => $path, 'storage_disk' => 'local',
                        'mime_type' => $photo->getMimeType(), 'size_bytes' => $photo->getSize(), 'sort_order' => $index,
                        'sha256' => hash_file('sha256', $photo->getRealPath()),
                    ]);
                }
                return $case;
            });
        } catch (Throwable $exception) {
            if ($storedDirectory) Storage::disk('local')->deleteDirectory($storedDirectory);
            throw $exception;
        }

        $case->load(['patient', 'assignedDoctor']);
        return response()->json(['message' => 'Case created and sent to the dermatologist.', 'data' => $this->caseSummary($case)], 201);
    }

    public function photo(Request $request, PatientCase $patientCase, CasePhoto $casePhoto): StreamedResponse
    {
        $this->ensureAccess($request, $patientCase);
        abort_unless((int) $casePhoto->case_id === (int) $patientCase->id, 404);
        $disk = Storage::disk($casePhoto->storage_disk ?: 'local');
        abort_unless($casePhoto->storage_path && $disk->exists($casePhoto->storage_path), 404);
        return $disk->response($casePhoto->storage_path, $casePhoto->filename, [
            'Content-Type' => $casePhoto->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensureAccess(Request $request, PatientCase $case): void
    {
        $scope = $this->scope($request->user());
        abort_unless($request->user()->isAdministrator() || ($scope && (int) $case->{$scope[0]} === (int) $scope[1]), 404);
    }

    private function scope(User $user): ?array
    {
        if ($user->isAdministrator()) {
            return null;
        }

        return match ($user->clinicalRole()) {
            User::ROLE_DERMATOLOGIST => ['assigned_to', $user->id],
            User::ROLE_MEDICAL_OFFICER => ['created_by', $user->id],
            default => ['created_by', $user->id],
        };
    }

    private function isParticipant(User $user, PatientCase $case): bool
    {
        return match ($user->clinicalRole()) {
            User::ROLE_DERMATOLOGIST => (int) $case->assigned_to === (int) $user->id,
            User::ROLE_MEDICAL_OFFICER => (int) $case->created_by === (int) $user->id,
            default => false,
        };
    }

    private function caseSummary(PatientCase $case, bool $withHistory = false): array
    {
        $result = [
            'id' => $case->id,
            'case_number' => $case->id,
            'patient_id' => $case->patient_id,
            'follow_up_of' => $case->follow_up_of,
            'status' => $case->status,
            'priority' => $case->priority,
            'symptoms' => $case->symptoms,
            'finding' => $case->finding,
            'history' => $case->history,
            'phc' => $case->phc,
            'created_at' => $case->created_at,
            'closed_at' => $case->closed_at,
            'patient' => $case->relationLoaded('patient') ? $this->patientPayload($case->patient) : null,
            'assigned_doctor' => $this->userSummary($case->assignedDoctor),
            'creator' => $this->userSummary($case->creator),
            'photo_count' => $case->photos_count ?? ($case->relationLoaded('photos') ? $case->photos->count() : null),
        ];
        if ($withHistory) {
            $result['treatments'] = $case->treatments->map(fn ($treatment) => [
                'id' => $treatment->id, 'diagnosis' => $treatment->diagnosis, 'treatment' => $treatment->treatment,
                'prevention' => $treatment->prevention, 'referral' => $treatment->referral, 'created_at' => $treatment->created_at,
                'doctor' => $this->userSummary($treatment->doctor),
            ])->values();
            $result['photos'] = $case->photos->map(fn (CasePhoto $photo) => $this->photoPayload($case, $photo))->values();
        }
        return $result;
    }

    private function patientPayload(Patient $patient): array
    {
        return [
            'id' => $patient->id, 'patient_code' => $patient->patient_code, 'name' => $patient->name,
            'age' => $patient->age, 'gender' => $patient->gender, 'phc' => $patient->phc,
            'province' => $patient->province, 'district' => $patient->district, 'municipality' => $patient->municipality,
            'phone' => $patient->phone, 'email' => $patient->email, 'weight' => $patient->weight,
        ];
    }

    private function userSummary(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id, 'name' => $user->full_name, 'role' => $user->role(),
            'user_type' => $user->user_type, 'is_admin' => $user->isAdministrator(),
            'roles' => $user->roles(), 'role_label' => $user->roleLabel(),
            'facility_name' => $user->facility_name, 'specialty' => $user->specialty,
        ] : null;
    }

    private function photoPayload(PatientCase $case, CasePhoto $photo): array
    {
        return [
            'id' => $photo->id, 'filename' => $photo->filename, 'mime_type' => $photo->mime_type,
            'size_bytes' => $photo->size_bytes, 'sort_order' => $photo->sort_order,
            'source' => $photo->query_id ? 'chat' : 'case',
            'url' => route('api.v1.cases.photos.show', [$case, $photo]),
        ];
    }

    private function makePatientCode(string $province, string $district, string $municipality, int $id): string
    {
        $initials = collect([$province, $district, $municipality])->map(function (string $place): string {
            preg_match('/[A-Za-z0-9]/', Str::ascii(trim($place)), $match);
            return Str::upper($match[0] ?? 'X');
        });
        return $initials->implode('-').'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
