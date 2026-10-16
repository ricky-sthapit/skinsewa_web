@extends('layouts.workspace')

@section('title', 'Resources · SkinSewa')

@section('workspace-content')
<section class="page-heading-row">
    <div>
        <p class="eyebrow">LEARNING LIBRARY</p>
        <h1>Resources</h1>
        <p class="muted">Dermatology guides for health workers and care teams.</p>
    </div>
    @if (auth()->user()->isAdministrator())
        <a class="primary-button" href="{{ route('resources.create') }}">Add resource</a>
    @endif
</section>

@if (session('status'))
    <div class="success-alert" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="panel users-panel" aria-labelledby="resource-list-title">
    <div class="panel-heading">
        <div>
            <h2 id="resource-list-title">Resource library</h2>
            <p class="muted">Open a guide to read it or download a copy.</p>
        </div>
        <span class="result-count">{{ $resources->total() }} {{ str('resource')->plural($resources->total()) }}</span>
    </div>

    <form class="user-filters resource-filters" method="get" action="{{ route('resources.index') }}">
        <label class="filter-search">
            <span>Search resources</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Display name" autocomplete="off">
        </label>
        <button class="primary-button filter-button" type="submit">Search</button>
        @if ($search !== '')
            <a class="reset-filters" href="{{ route('resources.index') }}">Clear search</a>
        @endif
    </form>

    <div class="table-scroll">
        <table class="users-table resources-table">
            <thead>
                <tr>
                    <th scope="col">Display name</th>
                    <th scope="col">Resource file</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($resources as $resource)
                    <tr>
                        <td><strong class="resource-display-name">{{ $resource->display_name }}</strong></td>
                        <td>
                            <a class="resource-file" href="{{ route('resources.view', $resource) }}" target="_blank" rel="noopener">
                                <span class="pdf-mark" aria-hidden="true">PDF</span>
                                <span class="resource-file-copy">
                                    <strong>{{ $resource->original_file_name }}</strong>
                                    <span>{{ number_format($resource->file_size_bytes / 1024, 0) }} KB</span>
                                </span>
                            </a>
                        </td>
                        <td>
                            <div class="table-actions resource-actions">
                                <a class="secondary-button small-button" href="{{ route('resources.download', $resource) }}">Download</a>
                                @if (auth()->user()->isAdministrator())
                                    <a class="secondary-button small-button" href="{{ route('resources.edit', $resource) }}">Edit</a>
                                    <form method="post" action="{{ route('resources.destroy', $resource) }}" onsubmit="return confirm('Delete {{ addslashes($resource->display_name) }}? This will also remove its PDF file.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger-button small-button" type="submit">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="3">{{ $search !== '' ? 'No resources match your search.' : 'No resources are available yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <footer class="table-footer">
        <p>
            @if ($resources->total() > 0)
                Showing <strong>{{ $resources->firstItem() }}–{{ $resources->lastItem() }}</strong> of <strong>{{ $resources->total() }}</strong> resources
            @else
                No resources to show
            @endif
        </p>
        @if ($resources->hasPages())
            <nav class="simple-pagination" aria-label="Pagination">
                @if ($resources->onFirstPage()) <span class="pagination-disabled">Previous</span> @else <a href="{{ $resources->previousPageUrl() }}">Previous</a> @endif
                <span>Page {{ $resources->currentPage() }} of {{ $resources->lastPage() }}</span>
                @if ($resources->hasMorePages()) <a href="{{ $resources->nextPageUrl() }}">Next</a> @else <span class="pagination-disabled">Next</span> @endif
            </nav>
        @endif
    </footer>
</section>
<p class="medical-note">These guides provide general education and do not replace assessment or advice from a qualified health professional.</p>
@endsection
