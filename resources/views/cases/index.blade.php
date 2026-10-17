@extends('layouts.workspace')

@section('title', 'Patient cases · SkinSewa')

@section('workspace-content')
<section class="page-heading-row">
    <div>
        <p class="eyebrow">CARE WORKSPACE</p>
        <h1>{{ $isAdmin ? 'All patient cases' : 'Patient cases' }}</h1>
        <p class="muted">{{ $isAdmin ? 'Review all cases across the SkinSewa care network.' : ($isDermatologist ? 'Review patient cases referred to you by health workers.' : 'Create a patient record and send a skin concern to a dermatologist.') }}</p>
    </div>
    @if (! $isDermatologist && ! $isAdmin)<a class="primary-button" href="{{ route('cases.create') }}">＋ Add patient case</a>@endif
</section>

@if (session('status'))
    <div class="success-alert" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="case-stat-row" aria-label="Case summary">
    <article class="case-stat-card"><span class="case-stat-icon">✚</span><div><span>{{ $isDermatologist ? 'Cases assigned' : 'Cases created' }}</span><strong>{{ number_format($caseCount) }}</strong></div></article>
    <article class="case-stat-card"><span class="case-stat-icon case-stat-icon-pending">◷</span><div><span>Awaiting review</span><strong>{{ number_format($awaitingCount) }}</strong></div></article>
</section>

<section class="panel case-list-panel" aria-labelledby="case-list-title">
    <div class="panel-heading">
        <div>
            <h2 id="case-list-title">{{ $isAdmin ? 'Case directory' : ($isDermatologist ? 'Cases assigned to you' : 'Your submitted cases') }}</h2>
            <p class="muted">{{ $isAdmin ? 'All cases currently recorded in SkinSewa.' : ($isDermatologist ? 'Only cases assigned to your dermatologist account are shown here.' : 'Only cases you created are shown here.') }}</p>
        </div>
        <span class="result-count">{{ $cases->total() }} {{ str('case')->plural($cases->total()) }}</span>
    </div>
    <form class="user-filters case-filters" method="get" action="{{ route('cases.index') }}">
        <label class="filter-search">
            <span>Find a case</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Patient name, patient ID, or case number" autocomplete="off">
        </label>
        <button class="primary-button filter-button" type="submit">Search</button>
        @if ($search !== '') <a class="reset-filters" href="{{ route('cases.index') }}">Clear search</a> @endif
    </form>
    <div class="table-scroll">
        <table class="users-table cases-table">
            <thead><tr><th scope="col">Patient / case</th><th scope="col">Location</th><th scope="col">{{ $isAdmin ? 'Care team' : ($isDermatologist ? 'Health worker' : 'Dermatologist') }}</th><th scope="col">Photos</th><th scope="col">Status</th><th scope="col">Created</th></tr></thead>
            <tbody>
                @forelse ($cases as $patientCase)
                    <tr>
                        <td>
                            <a class="case-patient-link" href="{{ route('cases.show', $patientCase) }}">
                                <strong>{{ $patientCase->patient->name }}</strong>
                                <span>Patient {{ $patientCase->patient->patient_code ?: '—' }} · Case #{{ $patientCase->id }} · {{ $patientCase->patient->age }} years · {{ $patientCase->patient->gender }}</span>
                            </a>
                        </td>
                        <td><span class="stacked-detail">{{ $patientCase->patient->municipality }}</span><span class="table-subtext">{{ $patientCase->patient->district }}</span></td>
                        <td>
                            @if ($isAdmin)
                                <span class="stacked-detail">{{ $patientCase->assignedDoctor?->full_name ?? 'No dermatologist' }}</span><span class="table-subtext">{{ $patientCase->creator?->full_name ?? 'Health worker unavailable' }}</span>
                            @else
                                {{ $isDermatologist ? ($patientCase->creator?->full_name ?? '—') : ($patientCase->assignedDoctor?->full_name ?? '—') }}
                            @endif
                        </td>
                        <td><span class="case-photo-count">▧ {{ $patientCase->photos_count }}</span></td>
                        <td><span class="case-status case-status-{{ str($patientCase->status)->slug() }}">{{ str($patientCase->status)->replace('_', ' ')->title() }}</span></td>
                        <td>{{ $patientCase->created_at?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="6">{{ $search !== '' ? 'No cases match that search.' : ($isAdmin ? 'No patient cases are available yet.' : ($isDermatologist ? 'No cases have been assigned to you yet.' : 'No patient cases yet. Add your first case to get started.')) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <footer class="table-footer">
        <p>@if ($cases->total()) Showing <strong>{{ $cases->firstItem() }}–{{ $cases->lastItem() }}</strong> of <strong>{{ $cases->total() }}</strong> cases @else No cases to show @endif</p>
        @if ($cases->hasPages())
            <nav class="simple-pagination" aria-label="Case pagination">
                @if ($cases->onFirstPage()) <span class="pagination-disabled">Previous</span> @else <a href="{{ $cases->previousPageUrl() }}">Previous</a> @endif
                <span>Page {{ $cases->currentPage() }} of {{ $cases->lastPage() }}</span>
                @if ($cases->hasMorePages()) <a href="{{ $cases->nextPageUrl() }}">Next</a> @else <span class="pagination-disabled">Next</span> @endif
            </nav>
        @endif
    </footer>
</section>
<p class="medical-note">Keep patient details and case photos confidential. Share information only with the assigned care team.</p>
@endsection
