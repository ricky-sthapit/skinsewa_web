@extends('layouts.workspace')

@section('title', 'Reports · SkinSewa')

@section('workspace-content')
<section class="page-heading-row">
    <div>
        <p class="eyebrow">ADMINISTRATION</p>
        <h1>Reports</h1>
        <p class="muted">Monthly case activity by location and dermatologist.</p>
    </div>
</section>

@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="panel report-filter-panel" aria-label="Report filters">
    <form class="user-filters report-filters" method="get" action="{{ route('reports.index') }}">
        <label>
            <span>Month</span>
            <input type="month" name="month" value="{{ $month }}">
        </label>
        <label>
            <span>Province</span>
            <select name="province_id" onchange="this.form.elements.district_id.value='';this.form.elements.municipality_id.value='';this.form.submit()">
                <option value="">All provinces</option>
                @foreach ($provinceOptions as $place)
                    <option value="{{ $place->id }}" @selected(($locations['province']?->id ?? null) === $place->id)>{{ $place->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>District</span>
            <select name="district_id" onchange="this.form.elements.municipality_id.value='';this.form.submit()">
                <option value="">All districts</option>
                @foreach ($districtOptions as $place)
                    <option value="{{ $place->id }}" @selected(($locations['district']?->id ?? null) === $place->id)>
                        {{ $place->name }}{{ ! $locations['province'] && isset($place->parent) ? ' · '.($provinceOptions->firstWhere('id', $place->parent)?->name ?? '') : '' }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Municipality / local level</span>
            <select name="municipality_id">
                <option value="">All local levels</option>
                @foreach ($municipalityOptions as $place)
                    <option value="{{ $place->id }}" @selected(($locations['municipality']?->id ?? null) === $place->id)>{{ $place->name }} · {{ str($place->type)->replace('_', ' ') }}</option>
                @endforeach
            </select>
        </label>
        <label class="filter-search">
            <span>Search cases</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Patient, patient ID, or case number">
        </label>
        @if ($selectedDoctor)
            <input type="hidden" name="dermatologist_id" value="{{ $selectedDoctor->id }}">
        @endif
        <button class="primary-button filter-button" type="submit">Apply filters</button>
        <a class="reset-filters" href="{{ route('reports.index', ['month' => $month]) }}">Clear filters</a>
    </form>
</section>

<nav class="report-tabs" aria-label="Report sections">
    <a href="#monthly-cases">Monthly cases</a>
    <a href="#location-breakdown">By location</a>
    <a href="#dermatologist-breakdown">By dermatologist</a>
</nav>

<section class="case-stat-row reports-stat-row" aria-label="Monthly case summary">
    <article class="case-stat-card"><span class="case-stat-icon">✚</span><div><span>Cases created in {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span><strong>{{ number_format($summary['total_cases']) }}</strong></div></article>
    <article class="case-stat-card"><span class="case-stat-icon case-stat-icon-pending">✓</span><div><span>Cases completed in {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span><strong>{{ number_format($summary['completed_cases']) }}</strong></div></article>
    <a class="case-stat-card report-stat-link" href="#location-breakdown"><span class="case-stat-icon">⌖</span><div><span>Districts reporting</span><strong>{{ number_format($summary['districts_reported']) }}</strong></div><span class="report-open-arrow" aria-hidden="true">→</span></a>
</section>

<div class="report-grid">
    <section class="panel report-panel" id="location-breakdown" aria-labelledby="location-title">
        <div class="panel-heading">
            <div>
                <h2 id="location-title">{{ $locations['district'] ? 'Cases by municipality / local level' : 'Cases by district' }}</h2>
                <p class="muted">Choose a location to filter the case list.</p>
            </div>
            <span class="result-count">{{ count($locationBreakdown) }} locations</span>
        </div>
        <div class="table-scroll">
            <table class="users-table reports-table">
                <thead><tr><th scope="col">Location</th><th scope="col">Province / district</th><th scope="col">Cases</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                    @forelse ($locationBreakdown as $location)
                        @php
                            $locationQuery = ['month' => $month, 'province_id' => $location['province_id'], 'district_id' => $location['district_id'], 'municipality_id' => $location['municipality_id']];
                        @endphp
                        <tr>
                            <td><strong>{{ $location['name'] }}</strong></td>
                            <td>{{ $location['district'] ? $location['district'].' · ' : '' }}{{ $location['province'] ?? '—' }}</td>
                            <td>{{ number_format($location['total_cases']) }}</td>
                            <td><a class="case-patient-link report-drill-link" href="{{ route('reports.index', $locationQuery) }}#monthly-cases">View cases →</a></td>
                        </tr>
                    @empty
                        <tr><td class="empty-state" colspan="4">No cases for this location and month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel report-panel" id="dermatologist-breakdown" aria-labelledby="dermatologist-title">
        <div class="panel-heading">
            <div>
                <h2 id="dermatologist-title">Cases by dermatologist</h2>
                <p class="muted">Counts include cases created in the selected month and grouped by current assignment.</p>
            </div>
        </div>
        <div class="table-scroll">
            <table class="users-table reports-table">
                <thead><tr><th scope="col">Dermatologist</th><th scope="col">Cases</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                <tbody>
                    @forelse ($dermatologists as $doctor)
                        <tr>
                            <td><strong>{{ $doctor['name'] }}</strong></td>
                            <td>{{ number_format($doctor['total_cases']) }}</td>
                            <td><a class="case-patient-link report-drill-link" href="{{ route('reports.index', array_merge(request()->except('page'), ['dermatologist_id' => $doctor['id']])) }}#monthly-cases">View cases →</a></td>
                        </tr>
                    @empty
                        <tr><td class="empty-state" colspan="3">No assigned dermatologist cases for this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel report-cases-panel" id="monthly-cases" aria-labelledby="monthly-cases-title">
    <div class="panel-heading">
        <div>
            <h2 id="monthly-cases-title">Monthly cases</h2>
            @if ($selectedDoctor)
                <p class="muted">Cases currently assigned to {{ $selectedDoctor->full_name }}. <a href="{{ route('reports.index', request()->except('dermatologist_id', 'page')) }}#monthly-cases">Clear dermatologist</a></p>
            @else
                <p class="muted">Cases created during {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}.</p>
            @endif
        </div>
        <span class="result-count">{{ number_format($cases->total()) }} cases</span>
    </div>
    <div class="table-scroll">
        <table class="users-table reports-table">
            <thead><tr><th scope="col">Case</th><th scope="col">Location</th><th scope="col">Dermatologist</th><th scope="col">Status</th><th scope="col">Created</th></tr></thead>
            <tbody>
                @forelse ($cases as $patientCase)
                    <tr>
                        <td><a class="case-patient-link" href="{{ route('cases.show', $patientCase) }}"><strong>Case #{{ $patientCase->id }} · {{ $patientCase->patient?->name ?? 'Patient unavailable' }}</strong><span>{{ $patientCase->patient?->patient_code ?? 'No patient ID' }}</span></a></td>
                        <td><span class="stacked-detail">{{ $patientCase->patient?->municipality ?? '—' }}</span><span class="table-subtext">{{ $patientCase->patient?->district ?? '—' }}, {{ $patientCase->patient?->province ?? '—' }}</span></td>
                        <td>{{ $patientCase->assignedDoctor?->full_name ?? 'Unassigned' }}</td>
                        <td><span class="case-status case-status-{{ str($patientCase->status)->slug() }}">{{ str($patientCase->status)->replace('_', ' ')->title() }}</span></td>
                        <td>{{ $patientCase->created_at?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="5">No cases match the selected month and filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <footer class="table-footer">
        <p>@if ($cases->total()) Showing <strong>{{ $cases->firstItem() }}–{{ $cases->lastItem() }}</strong> of <strong>{{ $cases->total() }}</strong> cases @else No cases to show @endif</p>
        @if ($cases->hasPages()) {{ $cases->links() }} @endif
    </footer>
</section>
<p class="medical-note">Location and dermatologist reports count cases by creation month; completed cases are counted by completion date. Case details remain available only to authorized administrators.</p>
@endsection
