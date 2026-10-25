@extends('layouts.workspace')

@section('title', 'Case #'.$patientCase->id.' · SkinSewa')

@section('workspace-content')
<div class="back-link-row"><a class="back-link" href="{{ route('cases.index') }}">← {{ auth()->user()->clinicalRole() === \App\Models\User::ROLE_DERMATOLOGIST ? 'Assigned cases' : (auth()->user()->isAdministrator() ? 'All cases' : 'Patient cases') }}</a></div>
@if (session('status')) <div class="success-alert" role="status">{{ session('status') }}</div> @endif
<section class="page-heading-row compact-heading">
    <div><p class="eyebrow">CASE #{{ $patientCase->id }}</p><h1>{{ $patientCase->patient->name }}</h1><p class="muted">Created {{ $patientCase->created_at?->format('M j, Y · g:i A') }} · {{ $patientCase->patient->municipality }}, {{ $patientCase->patient->district }}</p></div>
    <span class="case-status case-status-{{ str($patientCase->status)->slug() }}">{{ str($patientCase->status)->replace('_', ' ')->title() }}</span>
</section>

<div class="case-detail-grid">
    <section class="panel case-detail-panel" aria-labelledby="case-patient-heading">
        <div class="panel-heading"><div><h2 id="case-patient-heading">Patient details</h2><p class="muted">Recorded with patient consent.</p></div></div>
        <dl class="case-detail-list">
            <div><dt>Patient ID</dt><dd>{{ $patientCase->patient->patient_code ?: 'Not assigned' }}</dd></div>
            <div><dt>Visit type</dt><dd>{{ $patientCase->follow_up_of ? 'Follow-up to case #'.$patientCase->follow_up_of : 'New case' }}</dd></div>
            <div><dt>Age and gender</dt><dd>{{ $patientCase->patient->age }} years · {{ $patientCase->patient->gender }}</dd></div>
            <div><dt>Health facility</dt><dd>{{ $patientCase->phc ?: $patientCase->patient->phc }}</dd></div>
            <div><dt>Province</dt><dd>{{ $patientCase->patient->province }}</dd></div>
            <div><dt>Local level</dt><dd>{{ $patientCase->patient->municipality }}</dd></div>
            @if ($patientCase->patient->phone)<div><dt>Phone</dt><dd>{{ $patientCase->patient->phone }}</dd></div>@endif
            @if ($patientCase->patient->email)<div><dt>Email</dt><dd>{{ $patientCase->patient->email }}</dd></div>@endif
            @if ($patientCase->patient->weight)<div><dt>Weight</dt><dd>{{ $patientCase->patient->weight }} kg</dd></div>@endif
        </dl>
    </section>
    <section class="panel case-detail-panel" aria-labelledby="case-routing-heading">
        @if (auth()->user()->clinicalRole() === \App\Models\User::ROLE_DERMATOLOGIST)
            <div class="panel-heading"><div><h2 id="case-routing-heading">Referred by health worker</h2><p class="muted">The care team member who submitted this case.</p></div></div>
            <div class="assigned-doctor-card"><span class="listed-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($patientCase->creator->first_name, 0, 1).mb_substr($patientCase->creator->last_name, 0, 1)) }}</span><div><strong>{{ $patientCase->creator->full_name }}</strong><span>{{ $patientCase->creator->facility_name ?: $patientCase->phc }}</span></div></div>
        @else
            <div class="panel-heading"><div><h2 id="case-routing-heading">Assigned dermatologist</h2><p class="muted">The clinician selected for review.</p></div></div>
            <div class="assigned-doctor-card"><span class="listed-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($patientCase->assignedDoctor->first_name, 0, 1).mb_substr($patientCase->assignedDoctor->last_name, 0, 1)) }}</span><div><strong>{{ $patientCase->assignedDoctor->full_name }}</strong><span>{{ $patientCase->assignedDoctor->specialty ?: 'Dermatologist' }}</span></div></div>
        @endif
        <p class="case-consent-record">Consent recorded {{ $patientCase->consent_confirmed_at?->format('M j, Y · g:i A') }}.</p>
    </section>
    <section class="panel case-detail-panel case-clinical-detail">
        <div class="panel-heading"><div><h2>Clinical notes</h2><p class="muted">Information provided for dermatologist review.</p></div></div>
        <div class="clinical-note"><h3>Current symptoms</h3><p>{{ $patientCase->symptoms }}</p></div>
        <div class="clinical-note"><h3>Clinical findings</h3><p>{{ $patientCase->finding }}</p></div>
        <div class="clinical-note"><h3>Past medical history</h3><p>{{ $patientCase->history }}</p></div>
    </section>
    <section class="panel case-detail-panel case-photos-detail" id="case-gallery">
        <div class="panel-heading"><div><h2>Case photo gallery</h2><p class="muted"><span data-gallery-count>{{ $patientCase->photos->count() }}</span> {{ str('photo')->plural($patientCase->photos->count()) }} · Includes case and chat uploads</p></div></div>
        @if ($patientCase->photos->isNotEmpty())
            <div class="case-detail-photo-grid">
                @foreach ($patientCase->photos as $photo)
                    <button class="case-gallery-item" type="button" data-gallery-photo-id="{{ $photo->id }}" aria-label="View case photo {{ $loop->iteration }} fullscreen">
                        <img src="{{ route('cases.photos.show', [$patientCase, $photo]) }}" alt="Case photo {{ $loop->iteration }}" loading="lazy">
                        <span>{{ $photo->query_id ? 'Added in chat' : 'Case photo' }} {{ $loop->iteration }}</span>
                    </button>
                @endforeach
            </div>
        @else
            <p class="case-no-photos">No photos were attached to this case.</p>
        @endif
    </section>
</div>

<section class="panel patient-history-panel" aria-labelledby="patient-history-heading">
    <div class="panel-heading"><div><h2 id="patient-history-heading">Patient visit history</h2><p class="muted">Previous cases for patient {{ $patientCase->patient->patient_code ?: 'record' }}.</p></div><span class="result-count">{{ $previousCases->count() }} previous {{ str('visit')->plural($previousCases->count()) }}</span></div>
    @forelse ($previousCases as $previousCase)
        <article class="patient-history-entry">
            <header><a href="{{ route('cases.show', $previousCase) }}"><strong>Case #{{ $previousCase->id }}</strong></a><time>{{ $previousCase->created_at?->format('M j, Y') }}</time><span class="case-status case-status-{{ str($previousCase->status)->slug() }}">{{ str($previousCase->status)->replace('_', ' ')->title() }}</span></header>
            <p><strong>Symptoms:</strong> {{ $previousCase->symptoms }}</p>
            @foreach ($previousCase->treatments as $treatment)
                <div class="patient-history-plan"><strong>{{ $treatment->diagnosis }}</strong><span>{{ $treatment->treatment }}</span><small>{{ $treatment->doctor?->full_name ?? 'Dermatologist' }} · {{ $treatment->created_at?->format('M j, Y') }}</small></div>
            @endforeach
        </article>
    @empty
        <p class="patient-history-empty">This is the patient's first recorded visit in your accessible case history.</p>
    @endforelse
</section>

<section class="panel case-treatment-panel" id="treatment-plan" aria-labelledby="treatment-heading">
    <div class="panel-heading"><div><h2 id="treatment-heading">Diagnosis and course of action</h2><p class="muted">Clinical plans are saved using the existing treatment record.</p></div></div>
    @if ($canPrescribe)
        <form class="case-treatment-form" method="post" action="{{ route('cases.treatments.store', $patientCase) }}">
            @csrf
            <label class="case-field"><span>Diagnosis <b>*</b></span><textarea name="diagnosis" rows="3" maxlength="1000" required>{{ old('diagnosis') }}</textarea>@error('diagnosis')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Course of action, medicine, or ointment <b>*</b></span><textarea name="treatment" rows="4" maxlength="1000" required placeholder="Include medicine or ointment, how to use it, and duration.">{{ old('treatment') }}</textarea>@error('treatment')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Prevention and care advice</span><textarea name="prevention" rows="3" maxlength="1000">{{ old('prevention') }}</textarea>@error('prevention')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Referral or follow-up</span><textarea name="referral" rows="3" maxlength="1000">{{ old('referral') }}</textarea>@error('referral')<small class="field-error">{{ $message }}</small>@enderror</label>
            <div class="case-form-actions"><button class="primary-button" type="submit">Save clinical plan</button></div>
        </form>
    @endif
    <div class="case-treatment-history">
        @forelse ($patientCase->treatments as $treatment)
            <article class="treatment-entry">
                <header><div><strong>{{ $treatment->doctor?->full_name ?? 'Dermatologist' }}</strong><span>{{ $treatment->created_at?->format('M j, Y · g:i A') }}</span></div></header>
                <dl class="treatment-detail-list">
                    <div><dt>Diagnosis</dt><dd>{{ $treatment->diagnosis }}</dd></div>
                    <div><dt>Course of action / medicine / ointment</dt><dd>{{ $treatment->treatment }}</dd></div>
                    @if ($treatment->prevention)<div><dt>Prevention and care</dt><dd>{{ $treatment->prevention }}</dd></div>@endif
                    @if ($treatment->referral)<div><dt>Referral or follow-up</dt><dd>{{ $treatment->referral }}</dd></div>@endif
                </dl>
            </article>
        @empty
            <p class="case-no-photos">No clinical plan has been added yet.</p>
        @endforelse
    </div>
</section>

<section class="panel case-chat-panel" id="case-chat" aria-labelledby="case-chat-heading"
    data-messages-url="{{ route('cases.messages.index', $patientCase) }}"
    data-send-url="{{ route('cases.messages.store', $patientCase) }}"
    data-current-id="{{ $patientCase->messages->last()?->id ?? 0 }}"
    data-current-user="{{ auth()->id() }}">
    <div class="panel-heading"><div><h2 id="case-chat-heading">Case conversation</h2><p class="muted">{{ $canCommunicate ? 'Ask for missing details or share additional case photos securely.' : 'Conversation view for this case.' }}</p></div><span class="chat-live-label"><i></i> Updates automatically</span></div>
    <div class="case-chat-messages" id="case-chat-messages" role="log" aria-live="polite" aria-relevant="additions text">
        @forelse ($patientCase->messages as $message)
            <article class="chat-message {{ (int) $message->user_id === (int) auth()->id() ? 'is-mine' : '' }}" data-chat-message-id="{{ $message->id }}">
                <div class="chat-message-meta"><strong>{{ $message->author?->full_name ?? 'Care team member' }}</strong><span>{{ $message->author?->roleLabel() }}</span><time>{{ $message->created_at?->format('M j · g:i A') }}</time></div>
                @if (trim($message->detail) !== '')<p>{{ $message->detail }}</p>@endif
                @if ($message->photos->isNotEmpty())<div class="chat-photo-grid">
                    @foreach ($message->photos as $photo)
                        <button class="chat-photo-button" type="button" data-gallery-photo-id="{{ $photo->id }}" aria-label="View chat photo fullscreen"><img src="{{ route('cases.photos.show', [$patientCase, $photo]) }}" alt="Photo shared in case conversation" loading="lazy"></button>
                    @endforeach
                </div>@endif
            </article>
        @empty
            <p class="chat-empty" id="case-chat-empty">No messages yet. Use the conversation to request details or clarify the care plan.</p>
        @endforelse
    </div>
    @if ($canCommunicate)
        <form class="case-chat-form" id="case-chat-form" method="post" action="{{ route('cases.messages.store', $patientCase) }}" enctype="multipart/form-data">
            @csrf
            <label class="case-field chat-composer"><span class="sr-only">Message</span><textarea name="detail" rows="2" maxlength="5000" placeholder="Write a message or request more details…"></textarea></label>
            <label class="chat-attach-button" for="case-chat-photos" title="Attach photos" aria-label="Attach photos">＋</label>
            <input class="case-photo-input" id="case-chat-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            <button class="primary-button" type="submit">Send</button>
            <div class="chat-upload-preview" id="chat-upload-preview" aria-live="polite"></div>
            <p class="chat-form-error" id="chat-form-error" role="alert" hidden></p>
        </form>
    @elseif ($isAdmin)
        <p class="case-admin-readonly">Administrator view is read-only. Only the case health worker and assigned dermatologist can send messages.</p>
    @endif
</section>

<dialog class="case-photo-viewer" id="case-photo-viewer" aria-label="Case photo gallery">
    <div class="case-photo-viewer-toolbar"><button type="button" data-gallery-close aria-label="Close gallery">×</button><span id="case-photo-position"></span><div><button type="button" data-gallery-zoom-out aria-label="Zoom out">−</button><button type="button" data-gallery-zoom-reset aria-label="Reset zoom">Reset</button><button type="button" data-gallery-zoom-in aria-label="Zoom in">＋</button></div></div>
    <button class="case-photo-viewer-nav case-photo-viewer-prev" type="button" data-gallery-prev aria-label="Previous photo">‹</button>
    <img class="case-photo-viewer-image" id="case-photo-viewer-image" alt="Fullscreen case photo">
    <button class="case-photo-viewer-nav case-photo-viewer-next" type="button" data-gallery-next aria-label="Next photo">›</button>
</dialog>

<p class="medical-note">This record contains confidential patient health information. Keep it within the assigned care team.</p>

<script>
(() => {
    const panel = document.getElementById('case-chat');
    const gallery = document.getElementById('case-gallery');
    const viewer = document.getElementById('case-photo-viewer');
    if (!panel || !gallery || !viewer) return;

    const messages = document.getElementById('case-chat-messages');
    const form = document.getElementById('case-chat-form');
    const error = document.getElementById('chat-form-error');
    const photoInput = document.getElementById('case-chat-photos');
    const preview = document.getElementById('chat-upload-preview');
    const image = document.getElementById('case-photo-viewer-image');
    const photoPosition = document.getElementById('case-photo-position');
    let lastId = Number(panel.dataset.currentId || 0);
    let currentPhoto = 0;
    let zoom = 1;
    let touchStartX = null;

    const galleryButtons = () => Array.from(gallery.querySelectorAll('[data-gallery-photo-id]'));
    const showPhoto = (index) => {
        const buttons = galleryButtons();
        if (!buttons.length) return;
        currentPhoto = (index + buttons.length) % buttons.length;
        const selected = buttons[currentPhoto];
        image.src = selected.querySelector('img').src;
        image.alt = selected.querySelector('img').alt;
        image.style.transform = 'scale(1)';
        zoom = 1;
        photoPosition.textContent = `${currentPhoto + 1} / ${buttons.length}`;
    };
    const openPhoto = (id) => {
        const buttons = galleryButtons();
        const index = buttons.findIndex((button) => button.dataset.galleryPhotoId === String(id));
        if (index < 0) return;
        showPhoto(index);
        viewer.showModal();
    };
    const updateCount = () => {
        const count = galleryButtons().length;
        const label = gallery.querySelector('[data-gallery-count]');
        if (label) label.textContent = count;
    };
    const appendPhoto = (photo, casePhoto = false) => {
        let button = gallery.querySelector(`[data-gallery-photo-id="${photo.id}"]`);
        if (button) return button;
        button = document.createElement('button');
        button.type = 'button';
        button.className = casePhoto ? 'case-gallery-item' : 'case-gallery-item is-chat-photo';
        button.dataset.galleryPhotoId = photo.id;
        button.setAttribute('aria-label', 'View case photo fullscreen');
        const img = document.createElement('img');
        img.src = photo.url;
        img.alt = casePhoto ? 'Case photo' : 'Photo shared in case conversation';
        img.loading = 'lazy';
        const caption = document.createElement('span');
        caption.textContent = casePhoto ? 'Case photo' : 'Added in chat';
        button.append(img, caption);
        let grid = gallery.querySelector('.case-detail-photo-grid');
        if (!grid) {
            grid = document.createElement('div');
            grid.className = 'case-detail-photo-grid';
            gallery.append(grid);
            gallery.querySelector('.case-no-photos')?.remove();
        }
        grid.append(button);
        updateCount();
        return button;
    };
    const appendMessage = (item) => {
        if (document.querySelector(`[data-chat-message-id="${item.id}"]`)) return;
        document.getElementById('case-chat-empty')?.remove();
        const article = document.createElement('article');
        article.className = `chat-message${item.is_mine ? ' is-mine' : ''}`;
        article.dataset.chatMessageId = item.id;
        const meta = document.createElement('div');
        meta.className = 'chat-message-meta';
        const author = document.createElement('strong'); author.textContent = item.author;
        const role = document.createElement('span'); role.textContent = (item.role || '').replaceAll('_', ' ').toLowerCase().replace(/\b\w/g, (char) => char.toUpperCase());
        const time = document.createElement('time'); time.textContent = item.created_at;
        meta.append(author, role, time); article.append(meta);
        if (item.detail) { const body = document.createElement('p'); body.textContent = item.detail; article.append(body); }
        if (item.photos?.length) {
            const photoGrid = document.createElement('div'); photoGrid.className = 'chat-photo-grid';
            item.photos.forEach((photo) => {
                appendPhoto(photo);
                const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-photo-button';
                button.dataset.galleryPhotoId = photo.id; button.setAttribute('aria-label', 'View chat photo fullscreen');
                const img = document.createElement('img'); img.src = photo.url; img.alt = 'Photo shared in case conversation';
                button.append(img); photoGrid.append(button);
            });
            article.append(photoGrid);
        }
        messages.append(article); messages.scrollTop = messages.scrollHeight;
    };

    document.addEventListener('click', (event) => {
        const photoButton = event.target.closest('[data-gallery-photo-id]');
        if (photoButton) openPhoto(photoButton.dataset.galleryPhotoId);
        if (event.target.closest('[data-gallery-close]')) viewer.close();
        if (event.target.closest('[data-gallery-prev]')) showPhoto(currentPhoto - 1);
        if (event.target.closest('[data-gallery-next]')) showPhoto(currentPhoto + 1);
        if (event.target.closest('[data-gallery-zoom-in]')) zoom = Math.min(4, zoom + 0.5);
        if (event.target.closest('[data-gallery-zoom-out]')) zoom = Math.max(1, zoom - 0.5);
        if (event.target.closest('[data-gallery-zoom-reset]')) zoom = 1;
        image.style.transform = `scale(${zoom})`;
    });
    viewer.addEventListener('click', (event) => { if (event.target === viewer) viewer.close(); });
    document.addEventListener('keydown', (event) => {
        if (!viewer.open) return;
        if (event.key === 'ArrowLeft') showPhoto(currentPhoto - 1);
        if (event.key === 'ArrowRight') showPhoto(currentPhoto + 1);
    });
    image.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].clientX; }, { passive: true });
    image.addEventListener('touchend', (event) => {
        if (touchStartX === null) return;
        const delta = event.changedTouches[0].clientX - touchStartX;
        if (Math.abs(delta) > 45) showPhoto(currentPhoto + (delta < 0 ? 1 : -1));
        touchStartX = null;
    }, { passive: true });

    if (photoInput) photoInput.addEventListener('change', () => {
        preview.replaceChildren();
        Array.from(photoInput.files || []).forEach((file) => {
            const label = document.createElement('span'); label.textContent = file.name;
            preview.append(label);
        });
    });
    if (form) form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.hidden = true;
        const button = form.querySelector('button[type="submit"]'); button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'The message could not be sent.');
            appendMessage(payload.message); lastId = Math.max(lastId, Number(payload.message.id));
            form.reset(); preview.replaceChildren();
        } catch (exception) { error.textContent = exception.message; error.hidden = false; }
        finally { button.disabled = false; }
    });

    const refresh = async () => {
        try {
            const response = await fetch(`${panel.dataset.messagesUrl}?after_id=${lastId}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            payload.messages.forEach((item) => { appendMessage(item); lastId = Math.max(lastId, Number(item.id)); });
        } catch (_) { /* Continue polling when the connection returns. */ }
    };
    window.setInterval(refresh, 5000);
})();
</script>
@endsection
