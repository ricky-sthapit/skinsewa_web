<?php

namespace App\Services;

use App\Models\PatientCase;
use App\Models\Place;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminCaseReports
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function monthRange(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);

        return [$start, $start->addMonth()];
    }

    /** @return array{province: ?Place, district: ?Place, municipality: ?Place} */
    public function resolveLocations(array $ids): array
    {
        $province = isset($ids['province_id'])
            ? Place::query()->where('type', 'Province')->find($ids['province_id'])
            : null;
        $district = isset($ids['district_id'])
            ? Place::query()->where('type', 'District')->find($ids['district_id'])
            : null;
        $municipality = isset($ids['municipality_id'])
            ? Place::query()->whereIn('type', ['Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'])->find($ids['municipality_id'])
            : null;

        if ((isset($ids['province_id']) && ! $province) || (isset($ids['district_id']) && ! $district) || (isset($ids['municipality_id']) && ! $municipality)) {
            throw ValidationException::withMessages(['location' => 'Choose valid locations from the place list.']);
        }

        if ($municipality) {
            $district = $district ?: Place::query()->whereKey($municipality->parent)->where('type', 'District')->first();
            if (! $district || (int) $municipality->parent !== (int) $district->id) {
                throw ValidationException::withMessages(['municipality_id' => 'Choose a municipality inside the selected district.']);
            }
        }

        if ($district) {
            $districtProvince = Place::query()->whereKey($district->parent)->where('type', 'Province')->first();
            if (! $districtProvince || ($province && (int) $districtProvince->id !== (int) $province->id)) {
                throw ValidationException::withMessages(['district_id' => 'Choose a district inside the selected province.']);
            }
            $province = $province ?: $districtProvince;
        }

        return compact('province', 'district', 'municipality');
    }

    /** @param array{province?: ?Place, district?: ?Place, municipality?: ?Place} $locations */
    public function casesQuery(string $month, array $locations = []): Builder
    {
        [$start, $end] = $this->monthRange($month);

        return $this->applyLocations(
            PatientCase::query()->where('cases.created_at', '>=', $start)->where('cases.created_at', '<', $end),
            $locations,
        );
    }

    /** @param array{province?: ?Place, district?: ?Place, municipality?: ?Place} $locations
     *  @return array{total_cases: int, completed_cases: int, districts_reported: int}
     */
    public function summary(string $month, array $locations = []): array
    {
        [$start, $end] = $this->monthRange($month);
        $created = $this->casesQuery($month, $locations);
        $closed = $this->applyLocations(
            PatientCase::query()
                ->where('cases.status', 'completed')
                ->where('cases.closed_at', '>=', $start)
                ->where('cases.closed_at', '<', $end),
            $locations,
        );

        $districts = (clone $created)
            ->join('patients as report_patients', 'report_patients.id', '=', 'cases.patient_id')
            ->whereNotNull('report_patients.district')
            ->where('report_patients.district', '<>', '')
            ->select('report_patients.province', 'report_patients.district')
            ->distinct()
            ->get();

        return [
            'total_cases' => $created->count(),
            'completed_cases' => $closed->count(),
            'districts_reported' => $districts->count(),
        ];
    }

    /** @param array{province?: ?Place, district?: ?Place, municipality?: ?Place} $locations
     *  @return list<array{id: ?int, province_id: ?int, district_id: ?int, municipality_id: ?int, name: string, province: ?string, district: ?string, total_cases: int}>
     */
    public function locationBreakdown(string $month, array $locations = []): array
    {
        if (($locations['municipality'] ?? null) !== null) {
            return [];
        }

        $municipalityLevel = ($locations['district'] ?? null) !== null;
        $placesByName = collect();
        $districtsByParentAndName = collect();
        $query = $this->casesQuery($month, $locations)
            ->join('patients as report_patients', 'report_patients.id', '=', 'cases.patient_id')
            ->select([]);

        if ($municipalityLevel) {
            $rows = $query->selectRaw('report_patients.province as province, report_patients.district as district, report_patients.municipality as name, COUNT(cases.id) as total_cases')
                ->whereNotNull('report_patients.municipality')->where('report_patients.municipality', '<>', '')
                ->groupBy('report_patients.province', 'report_patients.district', 'report_patients.municipality')
                ->orderBy('report_patients.municipality')->get();

            $places = Place::query()->where('parent', $locations['district']->id)
                ->whereIn('type', ['Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City'])
                ->get(['id', 'name']);
            $placesByName = $places->groupBy('name')->map(fn (Collection $matches) => $matches->first());
        } else {
            $rows = $query->selectRaw('report_patients.province as province, report_patients.district as name, COUNT(cases.id) as total_cases')
                ->whereNotNull('report_patients.district')->where('report_patients.district', '<>', '')
                ->groupBy('report_patients.province', 'report_patients.district')
                ->orderBy('report_patients.province')->orderBy('report_patients.district')->get();

            $districtPlaces = Place::query()->where('type', 'District')
                ->when(($locations['province'] ?? null) !== null, fn ($query) => $query->where('parent', $locations['province']->id))
                ->get(['id', 'name', 'parent']);
            $provinceNames = Place::query()->whereIn('id', $districtPlaces->pluck('parent')->unique())
                ->pluck('name', 'id');
            $districtsByParentAndName = $districtPlaces->mapWithKeys(fn (Place $place) => [
                ($provinceNames[$place->parent] ?? '').'|'.$place->name => $place,
            ]);
        }

        return $rows->map(function ($row) use ($municipalityLevel, $locations, $placesByName, $districtsByParentAndName): array {
            $name = (string) $row->name;
            $place = $municipalityLevel
                ? ($placesByName[$name] ?? null)
                : ($districtsByParentAndName[(string) $row->province.'|'.$name] ?? null);

            return [
                'id' => $place?->id,
                'province_id' => $municipalityLevel ? ($locations['province'] ?? null)?->id : $place?->parent,
                'district_id' => $municipalityLevel ? ($locations['district'] ?? null)?->id : $place?->id,
                'municipality_id' => $municipalityLevel ? $place?->id : null,
                'name' => $name,
                'province' => $row->province ?? ($locations['province'] ?? null)?->name,
                'district' => $row->district ?? ($locations['district'] ?? null)?->name,
                'total_cases' => (int) $row->total_cases,
            ];
        })->values()->all();
    }

    /** @param array{province?: ?Place, district?: ?Place, municipality?: ?Place} $locations
     *  @return list<array{id: int, name: string, total_cases: int}>
     */
    public function dermatologistBreakdown(string $month, array $locations = []): array
    {
        return $this->casesQuery($month, $locations)
            ->join('users as report_doctors', 'report_doctors.id', '=', 'cases.assigned_to')
            ->select([])
            ->where('report_doctors.user_type', User::ROLE_DERMATOLOGIST)
            ->selectRaw("report_doctors.id, report_doctors.first_name, report_doctors.last_name, COUNT(cases.id) as total_cases")
            ->groupBy('report_doctors.id', 'report_doctors.first_name', 'report_doctors.last_name')
            ->orderByDesc('total_cases')->orderBy('report_doctors.last_name')->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
                'total_cases' => (int) $row->total_cases,
            ])->values()->all();
    }

    /** @param array{province?: ?Place, district?: ?Place, municipality?: ?Place} $locations */
    public function applyLocations(Builder $query, array $locations): Builder
    {
        return $query->whereHas('patient', function (Builder $patient) use ($locations): void {
            foreach (['province', 'district', 'municipality'] as $level) {
                $place = $locations[$level] ?? null;
                if ($place !== null) {
                    $column = $level;
                    $patient->where($column, $place->name);
                }
            }
        });
    }
}
