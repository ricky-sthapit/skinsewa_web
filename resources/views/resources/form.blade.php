<form class="edit-user-form resource-form" method="post" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="edit-form-grid">
        <label class="full-width-field">
            <span>Display name</span>
            <input name="display_name" value="{{ old('display_name', $resource?->display_name) }}" maxlength="160" required>
        </label>
        <label class="full-width-field">
            <span>PDF file @if ($resource) <small>(optional - leave blank to keep current file)</small> @endif</span>
            <input name="resource_file" type="file" accept="application/pdf,.pdf" @required(! $resource)>
            <small>PDF only, maximum file size 20 MB.</small>
        </label>
    </div>
    <div class="edit-form-actions">
        <a class="secondary-button" href="{{ route('resources.index') }}">Cancel</a>
        <button class="primary-button" type="submit">{{ $resource ? 'Save changes' : 'Add resource' }}</button>
    </div>
</form>
