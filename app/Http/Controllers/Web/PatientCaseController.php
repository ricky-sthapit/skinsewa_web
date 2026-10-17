<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CasePhoto;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PatientCaseController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $user = $request->user();
        $caseScope = match ($user->role()) {
            User::ROLE_ADMIN => null,
            User::ROLE_DERMATOLOGIST => ['assigned_to', $user->id],
            default => ['created_by', $user->id],
        };
        $availableStatuses = PatientCase::query()
            ->when($caseScope, fn (Builder $query) => $query->where($caseScope[0], $caseScope[1]))
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->merge(['awaiting_review', 'completed'])
            ->filter()
            ->unique()
            ->values();
        $requestedStatus = mb_substr(trim((string) $request->query('status', '')), 0, 32);
        $status = $availableStatuses->contains($requestedStatus) ? $requestedStatus : '';
        $cases = PatientCase::query()
            ->with(['patient', 'assignedDoctor', 'creator'])
            ->withCount('photos')
            ->when($caseScope, fn (Builder $query) => $query->where($caseScope[0], $caseScope[1]))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('id', ctype_digit($search) ? (int) $search : 0)
                        ->orWhereHas('patient', fn (Builder $patient) => $patient
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('patient_code', 'like', '%'.$search.'%'));
                });
            })
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $caseCountQuery = PatientCase::query()->when($caseScope, fn (Builder $query) => $query->where($caseScope[0], $caseScope[1]));
        $caseCount = (clone $caseCountQuery)->count();
        $awaitingCount = (clone $caseCountQuery)->where('status', 'awaiting_review')->count();
        $isDermatologist = $user->role() === User::ROLE_DERMATOLOGIST;
        $isAdmin = $user->role() === User::ROLE_ADMIN;

        return view('cases.index', compact('cases', 'search', 'status', 'availableStatuses', 'caseCount', 'awaitingCount', 'isDermatologist', 'isAdmin'));
    }

    public function create(): View
    {
        $provinces = Place::query()->where('type', 'Province')->orderBy('name')->get(['id', 'name', 'name_ne']);
        $dermatologists = User::query()
            ->where('user_type', User::ROLE_DERMATOLOGIST)
            ->where('is_admin', false)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'specialty', 'facility_name']);

        return view('cases.create', compact('provinces', 'dermatologists'));
    }

    public function children(Place $place)
    {
        abort_unless(in_array($place->type, ['Province', 'District'], true), 404);

        return Place::query()
            ->where('parent', $place->id)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ne', 'type']);
    }

    public function lookupPatient(Request $request)
    {
        $validated = $request->validate(['patient_code' => ['required', 'string', 'max:40']]);
        $patient = Patient::query()
            ->where('patient_code', Str::upper(trim($validated['patient_code'])))
            ->where('created_by', $request->user()->id)
            ->whereHas('cases', fn (Builder $cases) => $cases->where('created_by', $request->user()->id))
            ->first();

        if (! $patient) {
            return response()->json(['message' => 'No patient with that ID is available in your case history.'], 404);
        }

        $cases = $patient->cases()
            ->where('created_by', $request->user()->id)
            ->with(['assignedDoctor', 'treatments.doctor'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'patient_code' => $patient->patient_code,
                'name' => $patient->name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'municipality' => $patient->municipality,
                'district' => $patient->district,
                'province' => $patient->province,
                'case_count' => $cases->count(),
            ],
            'cases' => $cases->map(fn (PatientCase $case): array => [
                'id' => $case->id,
                'created_at' => $case->created_at?->format('M j, Y'),
                'follow_up_of' => $case->follow_up_of,
                'symptoms' => $case->symptoms,
                'finding' => $case->finding,
                'history' => $case->history,
                'doctor' => $case->assignedDoctor?->full_name,
                'treatments' => $case->treatments->map(fn ($treatment): array => [
                    'diagnosis' => $treatment->diagnosis,
                    'treatment' => $treatment->treatment,
                    'created_at' => $treatment->created_at?->format('M j, Y'),
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isReturningPatient = $request->filled('patient_id');
        $patientRequired = Rule::requiredIf(! $isReturningPatient);
        $data = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'follow_up_of' => ['nullable', 'integer', 'exists:cases,id'],
            'patient_name' => [$patientRequired, 'string', 'max:100'],
            'gender' => [$patientRequired, 'in:Male,Female'],
            'age' => [$patientRequired, 'integer', 'min:0', 'max:130'],
            'phc' => [$patientRequired, 'string', 'max:50'],
            'province_id' => [$patientRequired, 'integer', 'exists:places,id'],
            'district_id' => [$patientRequired, 'integer', 'exists:places,id'],
            'municipality_id' => [$patientRequired, 'integer', 'exists:places,id'],
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
        ], [
            'consent.accepted' => 'Please confirm that the patient has consented to this teledermatology assessment.',
            'photos.max' => 'A case can include up to 10 photos.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, or WebP images.',
        ]);

        $province = $district = $municipality = null;
        $errors = [];
        if (! $isReturningPatient) {
            $province = Place::query()->whereKey($data['province_id'])->where('type', 'Province')->first();
            $district = Place::query()->whereKey($data['district_id'])->where('type', 'District')->where('parent', $data['province_id'])->first();
            $municipality = Place::query()
                ->whereKey($data['municipality_id'])
                ->whereIn('type', ['Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'])
                ->where('parent', $data['district_id'])
                ->first();

            if (! $province) $errors['province_id'] = 'Choose a valid province.';
            if (! $district) $errors['district_id'] = 'Choose a district in the selected province.';
            if (! $municipality) $errors['municipality_id'] = 'Choose a local level in the selected district.';
        }

        $doctor = User::query()
            ->whereKey($data['assigned_to'])
            ->where('user_type', User::ROLE_DERMATOLOGIST)
            ->where('is_admin', false)
            ->first();
        if (! $doctor) {
            $errors['assigned_to'] = 'Choose an active dermatologist.';
        }
        $returningPatient = null;
        if ($isReturningPatient) {
            $returningPatient = Patient::query()
                ->whereKey($data['patient_id'])
                ->where('created_by', $request->user()->id)
                ->whereHas('cases', fn (Builder $cases) => $cases->where('created_by', $request->user()->id))
                ->first();
            if (! $returningPatient) $errors['patient_id'] = 'Choose a patient from your case history.';
        }
        if ($request->filled('follow_up_of')) {
            $priorCase = PatientCase::query()
                ->whereKey($data['follow_up_of'])
                ->where('patient_id', $returningPatient?->id)
                ->where('created_by', $request->user()->id)
                ->first();
            if (! $priorCase) $errors['follow_up_of'] = 'Choose a previous case for this patient.';
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);

        $storedDirectory = null;
        try {
            $case = DB::transaction(function () use ($data, $request, $province, $district, $municipality, $doctor, $returningPatient, &$storedDirectory): PatientCase {
                $patient = $returningPatient;
                if (! $patient) {
                    $patient = Patient::query()->create([
                        'name' => trim($data['patient_name']),
                        'gender' => $data['gender'],
                        'age' => $data['age'],
                        'phc' => trim($data['phc']),
                        'province' => $province->name,
                        'district' => $district->name,
                        'municipality' => $municipality->name,
                        'phone' => isset($data['phone']) ? trim($data['phone']) : null,
                        'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
                        'weight' => $data['weight'] ?? null,
                        'created_by' => $request->user()->id,
                        'consent_recorded_at' => now(),
                    ]);
                    $patient->forceFill([
                        'patient_code' => $this->makePatientCode($province->name, $district->name, $municipality->name, $patient->id),
                    ])->save();
                }

                $case = PatientCase::query()->create([
                    'phc' => $patient->phc,
                    'finding' => trim($data['finding']),
                    'history' => trim($data['history']),
                    'symptoms' => trim($data['symptoms']),
                    'patient_id' => $patient->id,
                    'follow_up_of' => $data['follow_up_of'] ?? null,
                    'created_by' => $request->user()->id,
                    'assigned_to' => $doctor->id,
                    'status' => 'awaiting_review',
                    'priority' => 'normal',
                    'consent_confirmed_at' => now(),
                ]);

                $storedDirectory = 'case-photos/'.$case->id;
                foreach ($request->file('photos', []) as $index => $photo) {
                    $extension = strtolower($photo->extension() ?: 'jpg');
                    $filename = Str::uuid()->toString().'.'.$extension;
                    $path = $photo->storeAs($storedDirectory, $filename, 'local');
                    if ($path === false) throw new \RuntimeException('A case photo could not be stored.');

                    CasePhoto::query()->create([
                        'filename' => $filename,
                        'case_id' => $case->id,
                        'user_id' => $request->user()->id,
                        'created_at' => now(),
                        'storage_path' => $path,
                        'storage_disk' => 'local',
                        'mime_type' => $photo->getMimeType(),
                        'size_bytes' => $photo->getSize(),
                        'sort_order' => $index,
                        'sha256' => hash_file('sha256', $photo->getRealPath()),
                    ]);
                }

                return $case;
            });
        } catch (Throwable $exception) {
            if ($storedDirectory) Storage::disk('local')->deleteDirectory($storedDirectory);
            throw $exception;
        }

        return redirect()->route('cases.show', $case)->with(
            'status',
            'Case #'.$case->id.' created for patient '.$case->patient->patient_code.' and sent to the dermatologist.'
        );
    }

    public function show(Request $request, PatientCase $patientCase): View
    {
        $this->ensureCaseAccess($request, $patientCase);
        $patientCase->load([
            'patient', 'assignedDoctor', 'creator', 'followUpOf', 'photos.message',
            'messages.author', 'messages.photos', 'treatments.doctor',
        ]);
        $historyScope = match ($request->user()->role()) {
            User::ROLE_ADMIN => null,
            User::ROLE_DERMATOLOGIST => ['assigned_to', $request->user()->id],
            default => ['created_by', $request->user()->id],
        };
        $previousCases = $patientCase->patient->cases()
            ->where('id', '<>', $patientCase->id)
            ->when($historyScope, fn (Builder $query) => $query->where($historyScope[0], $historyScope[1]))
            ->with(['assignedDoctor', 'treatments.doctor'])
            ->latest('created_at')
            ->get();
        $isAdmin = $request->user()->role() === User::ROLE_ADMIN;
        $canCommunicate = ! $isAdmin && in_array($request->user()->role(), [User::ROLE_MEDICAL_OFFICER, User::ROLE_DERMATOLOGIST], true);
        $canPrescribe = $request->user()->role() === User::ROLE_DERMATOLOGIST
            && (int) $patientCase->assigned_to === (int) $request->user()->id;

        return view('cases.show', compact('patientCase', 'previousCases', 'isAdmin', 'canCommunicate', 'canPrescribe'));
    }

    public function photo(Request $request, PatientCase $patientCase, CasePhoto $casePhoto): StreamedResponse
    {
        $this->ensureCaseAccess($request, $patientCase);
        abort_unless($casePhoto->case_id === $patientCase->id, 404);
        $disk = Storage::disk($casePhoto->storage_disk ?: 'local');
        abort_unless($casePhoto->storage_path && $disk->exists($casePhoto->storage_path), 404);

        return $disk->response($casePhoto->storage_path, $casePhoto->filename, [
            'Content-Type' => $casePhoto->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensureCaseAccess(Request $request, PatientCase $patientCase): void
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

    private function makePatientCode(string $province, string $district, string $municipality, int $patientId): string
    {
        $initials = collect([$province, $district, $municipality])->map(function (string $place): string {
            preg_match('/[A-Za-z0-9]/', Str::ascii(trim($place)), $match);

            return Str::upper($match[0] ?? 'X');
        });

        return $initials->implode('-').'-'.str_pad((string) $patientId, 6, '0', STR_PAD_LEFT);
    }
}
