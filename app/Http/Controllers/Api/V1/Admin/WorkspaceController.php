<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EducationalResource;
use App\Models\PatientCase;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class WorkspaceController extends Controller
{
    private const PAGE_SIZES = [5, 10, 15, 20, 25];

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => [
            'users' => User::query()->count(),
            'medical_officers' => User::query()->where('user_type', User::ROLE_MEDICAL_OFFICER)->count(),
            'dermatologists' => User::query()->where('user_type', User::ROLE_DERMATOLOGIST)->count(),
            'cases' => PatientCase::query()->count(),
            'resources' => EducationalResource::query()->count(),
            'invitations' => Registration::query()->where('is_registered', false)->whereNull('revoked_at')->count(),
            'cases_by_status' => PatientCase::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderBy('status')->get(),
            'recent_cases' => PatientCase::query()->with(['patient', 'assignedDoctor'])->latest()->limit(10)
                ->get()->map(fn (PatientCase $case) => [
                    'case_number' => $case->id, 'status' => $case->status, 'created_at' => $case->created_at,
                    'patient_name' => $case->patient?->name, 'patient_code' => $case->patient?->patient_code,
                    'dermatologist' => $case->assignedDoctor?->full_name,
                ]),
        ]]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $role = (string) $request->query('user_type', '');
        $role = in_array($role, User::ROLES, true) ? $role : '';
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 10;
        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('email', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            }))
            ->when($role === User::ROLE_ADMIN, fn ($query) => $query->where(fn ($query) => $query->where('user_type', User::ROLE_ADMIN)->orWhere('is_admin', true)))
            ->when($role !== '' && $role !== User::ROLE_ADMIN, fn ($query) => $query->where('user_type', $role))
            ->orderBy('last_name')->orderBy('first_name')->paginate($perPage)->withQueryString();
        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user) => $this->userPayload($user))->values(),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()],
        ]);
    }

    public function showUser(User $user): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($user)]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'user_type' => ['required', Rule::in(User::ROLES)],
            'is_admin' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $willBeAdmin = array_key_exists('is_admin', $data)
            ? (bool) $data['is_admin']
            : ($user->isAdministrator() || $data['user_type'] === User::ROLE_ADMIN);
        if (! $willBeAdmin && $data['user_type'] === User::ROLE_ADMIN) {
            return response()->json(['message' => 'Choose a clinical user type before removing administrator access.'], 422);
        }
        if ($user->is($request->user()) && ! $willBeAdmin) return response()->json(['message' => 'You cannot remove your own administrator access.'], 422);
        if ($user->isAdministrator() && ! $willBeAdmin && $this->activeAdminCount() <= 1) return response()->json(['message' => 'The last active administrator cannot be demoted.'], 422);

        $user->fill([
            'first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
            'email' => mb_strtolower(trim($data['email'])), 'user_type' => $data['user_type'], 'is_admin' => $willBeAdmin,
            'phone' => $data['phone'] ?? null, 'facility_name' => $data['facility_name'] ?? null,
            'specialty' => $data['specialty'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->delete();
        }
        $user->save();
        return response()->json(['message' => 'User updated.', 'data' => $this->userPayload($user)]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        if ($user->isAdministrator() && $this->activeAdminCount() <= 1) return response()->json(['message' => 'The last active administrator cannot be deactivated.'], 422);
        $user->tokens()->delete();
        $user->delete();
        return response()->json(['message' => 'User deactivated.']);
    }

    public function invitations(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        abort_unless(in_array($status, ['', 'pending', 'accepted', 'expired', 'revoked'], true), 422);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $perPage = min(25, max(5, (int) $request->query('per_page', 10)));
        $rows = Registration::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->when($status === 'pending', fn ($query) => $query->where('is_registered', false)->whereNull('revoked_at')->where('otp_expires_at', '>', now()))
            ->when($status === 'accepted', fn ($query) => $query->where('is_registered', true))
            ->when($status === 'revoked', fn ($query) => $query->where('is_registered', false)->whereNotNull('revoked_at'))
            ->when($status === 'expired', fn ($query) => $query->where('is_registered', false)->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('otp_expires_at')->orWhere('otp_expires_at', '<=', now())))
            ->orderByDesc('created_at')->paginate($perPage)->withQueryString();
        return response()->json([
            'data' => $rows->getCollection()->map(fn (Registration $row) => [
                'id' => $row->id, 'email' => $row->email, 'user_type' => $row->role,
                'status' => $row->invitation_status, 'expires_at' => $row->otp_expires_at, 'created_at' => $row->created_at,
            ])->values(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()],
        ]);
    }

    public function invite(Request $request, RegistrationService $registrations): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'user_type' => ['required', 'in:MEDICAL_OFFICER,DERMATOLOGIST'],
            'facility_name' => ['nullable', 'string', 'max:150'],
        ]);
        $invite = $registrations->invite($data['email'], $data['user_type'], $data['facility_name'] ?? null, (int) $request->user()->id);
        return response()->json(['message' => 'Invitation emailed.', 'data' => [
            'id' => $invite->id, 'email' => $invite->email, 'user_type' => $invite->role,
            'status' => $invite->invitation_status, 'expires_at' => $invite->otp_expires_at,
        ]], 201);
    }

    public function resendInvitation(Registration $registration, RegistrationService $registrations, Request $request): JsonResponse
    {
        if ($registration->is_registered) return response()->json(['message' => 'This invitation has already been accepted.'], 422);
        $invite = $registrations->invite($registration->email, $registration->role, $registration->facility_name, (int) $request->user()->id);
        return response()->json(['message' => 'A new invitation link and code were emailed.', 'data' => [
            'id' => $invite->id, 'email' => $invite->email, 'user_type' => $invite->role,
            'status' => $invite->invitation_status, 'expires_at' => $invite->otp_expires_at,
        ]]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'name' => $user->full_name, 'email' => $user->email, 'role' => $user->role(),
            'user_type' => $user->user_type, 'is_admin' => $user->isAdministrator(),
            'roles' => $user->roles(), 'role_label' => $user->roleLabel(),
            'phone' => $user->phone, 'facility_name' => $user->facility_name, 'specialty' => $user->specialty,
            'created_at' => $user->created_at, 'deleted_at' => $user->deleted_at,
        ];
    }

    private function activeAdminCount(): int
    {
        return User::query()->where(fn ($query) => $query->where('user_type', User::ROLE_ADMIN)->orWhere('is_admin', true))->count();
    }
}
