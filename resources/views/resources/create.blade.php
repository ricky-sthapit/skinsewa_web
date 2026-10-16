@extends('layouts.workspace')

@section('title', 'Add resource · SkinSewa')

@section('workspace-content')
<div class="back-link-row"><a class="back-link" href="{{ route('resources.index') }}">← Resources</a></div>
<section class="page-heading-row compact-heading">
    <div><p class="eyebrow">LEARNING LIBRARY</p><h1>Add resource</h1><p class="muted">Upload a dermatology guide as a PDF.</p></div>
</section>
@if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif
<section class="panel edit-user-panel" aria-labelledby="resource-form-title">
    <div class="panel-heading"><div><h2 id="resource-form-title">Resource details</h2><p class="muted">PDF files up to 20 MB are supported.</p></div></div>
    @include('resources.form', ['resource' => null, 'action' => route('resources.store'), 'method' => 'POST'])
</section>
@endsection
