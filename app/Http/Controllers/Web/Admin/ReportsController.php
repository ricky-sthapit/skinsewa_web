<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Place;
use App\Models\User;
use App\Services\AdminCaseReports;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, AdminCaseReports $reports): View
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'province_id' => ['nullable', 'integer', 'exists:places,id'],
            'district_id' => ['nullable', 'integer', 'exists:places,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:places,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'dermatologist_id' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'],
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
            ->latest('cases.created_at')
            ->paginate(15)
            ->withQueryString();

        $selectedDoctor = isset($filters['dermatologist_id'])
            ? User::query()->find($filters['dermatologist_id'])
            : null;
        $districtOptions = $locations['province']
            ? Place::query()->where('parent', $locations['province']->id)->where('type', 'District')->orderBy('name')->get(['id', 'name'])
            : Place::query()->where('type', 'District')->orderBy('name')->get(['id', 'name', 'parent']);
        $municipalityOptions = $locations['district']
            ? Place::query()->where('parent', $locations['district']->id)
                ->whereIn('type', ['Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'])
                ->orderBy('name')->get(['id', 'name', 'type'])
            : collect();

        return view('admin.reports.index', [
            'month' => $month,
            'filters' => $filters,
            'locations' => $locations,
            'summary' => $reports->summary($month, $locations),
            'locationBreakdown' => $reports->locationBreakdown($month, $locations),
            'dermatologists' => $reports->dermatologistBreakdown($month, $locations),
            'cases' => $casesQuery,
            'selectedDoctor' => $selectedDoctor,
            'provinceOptions' => Place::query()->where('type', 'Province')->orderBy('name')->get(['id', 'name']),
            'districtOptions' => $districtOptions,
            'municipalityOptions' => $municipalityOptions,
        ]);
    }
}
