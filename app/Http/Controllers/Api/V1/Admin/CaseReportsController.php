<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\User;
use App\Services\AdminCaseReports;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CaseReportsController extends Controller
{
    public function __invoke(Request $request, AdminCaseReports $reports): JsonResponse
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'province_id' => ['nullable', 'integer', 'exists:places,id'],
            'district_id' => ['nullable', 'integer', 'exists:places,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:places,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'dermatologist_id' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $locations = $reports->resolveLocations($filters);

        if (isset($filters['dermatologist_id']) && ! User::query()
            ->whereKey($filters['dermatologist_id'])->where('user_type', User::ROLE_DERMATOLOGIST)->exists()) {
            throw ValidationException::withMessages(['dermatologist_id' => 'Choose a dermatologist account.']);
        }

        $casesQuery = $reports->casesQuery($month, $locations)
            ->with(['patient', 'assignedDoctor'])
            ->when(isset($filters['dermatologist_id']), fn (Builder $query) => $query->where('cases.assigned_to', $filters['dermatologist_id']))
            ->when(($filters['q'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['q']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('cases.id', ctype_digit($search) ? (int) $search : 0)
                        ->orWhereHas('patient', fn (Builder $patient) => $patient
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('patient_code', 'like', '%'.$search.'%'));
                });
            })
            ->latest('cases.created_at');

        $page = $casesQuery->paginate($filters['per_page'] ?? 15)->withQueryString();

        return response()->json(['data' => [
            'month' => $month,
            'summary' => $reports->summary($month, $locations),
            'locations' => $reports->locationBreakdown($month, $locations),
            'dermatologists' => $reports->dermatologistBreakdown($month, $locations),
            'cases' => [
                'data' => $page->getCollection()->map(fn (PatientCase $case): array => [
                    'id' => $case->id,
                    'case_number' => $case->id,
                    'status' => $case->status,
                    'created_at' => $case->created_at,
                    'patient' => [
                        'name' => $case->patient?->name,
                        'patient_code' => $case->patient?->patient_code,
                        'province' => $case->patient?->province,
                        'district' => $case->patient?->district,
                        'municipality' => $case->patient?->municipality,
                    ],
                    'dermatologist' => $case->assignedDoctor ? [
                        'id' => $case->assignedDoctor->id,
                        'name' => $case->assignedDoctor->full_name,
                    ] : null,
                ])->values(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]]);
    }
}
