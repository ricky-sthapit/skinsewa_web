@extends('layouts.workspace')

@section('title', 'Edit resource · SkinSewa')

@section('workspace-content')
<div class="back-link-row"><a class="back-link" href="{{ route('resources.index') }}">← Resources</a></div>
<section class="page-heading-row compact-heading">
    <div><p class="eyebrow">LEARNING LIBRARY</p><h1>Edit resource</h1><p class="muted">Change the display name or replace its PDF file.</p></div>
</section>
@if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif
<section class="panel edit-user-panel" aria-labelledby="resource-form-title">
    <div class="panel-heading"><div><h2 id="resource-form-title">Resource details</h2><p class="muted">Current file: {{ $resource->original_file_name }}</p></div></div>
    @include('resources.form', ['resource' => $resource, 'action' => route('resources.update', $resource), 'method' => 'PUT'])
</section>
@endsection
