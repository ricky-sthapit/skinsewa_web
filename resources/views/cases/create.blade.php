@extends('layouts.workspace')

@section('title', 'Add patient case · SkinSewa')

@section('workspace-content')
<div class="back-link-row"><a class="back-link" href="{{ route('cases.index') }}">← Patient cases</a></div>
<section class="page-heading-row compact-heading">
    <div><p class="eyebrow">NEW CASE</p><h1>Add patient case</h1><p class="muted">Record the patient and skin concern, then send the case to a dermatologist.</p></div>
</section>

@if ($errors->any())
    <div class="alert case-form-alert" role="alert"><strong>Please review the form.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form class="patient-case-form" method="post" action="{{ route('cases.store') }}" enctype="multipart/form-data">
    @csrf
    <section class="panel case-form-panel" aria-labelledby="patient-details-heading">
        <div class="case-section-heading"><span class="case-step">01</span><div><h2 id="patient-details-heading">Patient details</h2><p>Enter the patient's basic details and where they receive local care.</p></div></div>
        <div class="returning-patient-lookup" data-lookup-url="{{ route('patients.lookup') }}" data-old-code="{{ old('patient_code') }}" data-old-id="{{ old('patient_id') }}">
            <label class="case-field"><span>Returning patient? Search by patient ID</span><div class="patient-lookup-controls"><input id="patient-code-search" type="text" value="{{ old('patient_code') }}" maxlength="40" placeholder="For example, K-K-K-000123" autocomplete="off"><button class="secondary-button" id="patient-lookup-button" type="button">Find patient</button></div></label>
            <p class="patient-lookup-hint">Selecting a patient will show their previous visits and reuse their patient record.</p>
            <div id="patient-lookup-result" class="patient-lookup-result" aria-live="polite" hidden></div>
            <input type="hidden" name="patient_id" id="selected-patient-id" value="{{ old('patient_id') }}">
            <input type="hidden" name="patient_code" id="selected-patient-code" value="{{ old('patient_code') }}">
            @error('patient_id')<small class="field-error">{{ $message }}</small>@enderror
            <div id="follow-up-selection" class="follow-up-selection" hidden>
                <div><h3>Previous cases</h3><p>Each visit will create a new case number. Link this visit to an earlier case if this is a follow-up.</p></div>
                <label class="case-field"><span>Link as follow-up <small>(optional)</small></span><select name="follow_up_of" id="follow-up-case"><option value="">New case or unrelated concern</option></select></label>
                @error('follow_up_of')<small class="field-error">{{ $message }}</small>@enderror
                <div id="confirmed-patient-history" class="confirmed-patient-history"></div>
            </div>
        </div>
        <div id="new-patient-fields">
        @if ($provinces->isEmpty())
            <div class="case-data-note">Location choices are not available yet. Run the <code>PlacesSeeder</code> to load provinces, districts, and local levels.</div>
        @endif
        <div class="case-form-grid">
            <label class="case-field case-field-wide"><span>Patient full name <b aria-hidden="true">*</b></span><input name="patient_name" value="{{ old('patient_name') }}" maxlength="100" autocomplete="name" required>@error('patient_name')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Age <b aria-hidden="true">*</b></span><div class="input-suffix"><input name="age" type="number" value="{{ old('age') }}" min="0" max="130" inputmode="numeric" required><span>years</span></div>@error('age')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Gender <b aria-hidden="true">*</b></span><select name="gender" required><option value="">Choose gender</option><option value="Female" @selected(old('gender') === 'Female')>Female</option><option value="Male" @selected(old('gender') === 'Male')>Male</option></select>@error('gender')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Health post / facility <b aria-hidden="true">*</b></span><input name="phc" value="{{ old('phc', auth()->user()->facility_name) }}" maxlength="50" placeholder="Primary health care center" required>@error('phc')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Weight <small>(optional)</small></span><div class="input-suffix"><input name="weight" type="number" step="0.1" min="0.1" max="499.9" value="{{ old('weight') }}" inputmode="decimal"><span>kg</span></div>@error('weight')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Phone <small>(optional)</small></span><input name="phone" type="tel" value="{{ old('phone') }}" maxlength="100" autocomplete="tel">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Email <small>(optional)</small></span><input name="email" type="email" value="{{ old('email') }}" maxlength="100" autocomplete="email">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Province <b aria-hidden="true">*</b></span><select id="province" name="province_id" required data-old="{{ old('province_id') }}"><option value="">Choose province</option>@foreach ($provinces as $province)<option value="{{ $province->id }}" @selected((string) old('province_id') === (string) $province->id)>{{ $province->name }}{{ $province->name_ne ? ' · '.$province->name_ne : '' }}</option>@endforeach</select>@error('province_id')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>District <b aria-hidden="true">*</b></span><select id="district" name="district_id" required data-old="{{ old('district_id') }}" disabled><option value="">Choose province first</option></select>@error('district_id')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field case-field-wide"><span>Municipality / local level <b aria-hidden="true">*</b></span><select id="municipality" name="municipality_id" required data-old="{{ old('municipality_id') }}" disabled><option value="">Choose district first</option></select>@error('municipality_id')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        </div>
    </section>

    <section class="panel case-form-panel" aria-labelledby="case-details-heading">
        <div class="case-section-heading"><span class="case-step">02</span><div><h2 id="case-details-heading">Skin concern</h2><p>Describe what you observed and include the patient's relevant history.</p></div></div>
        <div class="case-form-grid case-clinical-grid">
            <label class="case-field case-field-wide"><span>Current symptoms <b aria-hidden="true">*</b></span><textarea name="symptoms" rows="3" maxlength="10000" placeholder="When did it start? Is there pain, itching, or any change?" required>{{ old('symptoms') }}</textarea>@error('symptoms')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Clinical findings <b aria-hidden="true">*</b></span><textarea name="finding" rows="4" maxlength="10000" placeholder="Describe the observed skin concern, location, appearance, and extent." required>{{ old('finding') }}</textarea>@error('finding')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="case-field"><span>Past medical history <b aria-hidden="true">*</b></span><textarea name="history" rows="4" maxlength="10000" placeholder="Relevant conditions, allergies, and current medicines. Enter ‘None known’ if applicable." required>{{ old('history') }}</textarea>@error('history')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
    </section>

    <section class="panel case-form-panel" aria-labelledby="photos-heading">
        <div class="case-section-heading"><span class="case-step">03</span><div><h2 id="photos-heading">Case photos <small>(optional)</small></h2><p>Use clear, well-lit images. Avoid including the patient's face or identifying details.</p></div></div>
        <label class="case-photo-picker" for="case-photos"><span class="photo-picker-icon" aria-hidden="true">＋</span><span><strong>Add skin photos</strong><small>JPG, PNG, or WebP · up to 10 photos · 10 MB each</small></span><span class="secondary-button small-button">Choose files</span></label>
        <input class="case-photo-input" id="case-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div class="case-photo-preview" id="case-photo-preview" aria-live="polite"></div>
        <small class="field-error case-photo-limit-message" id="case-photo-limit-message" role="status" hidden></small>
        @error('photos')<small class="field-error">{{ $message }}</small>@enderror
        @foreach ($errors->get('photos.*') as $photoErrors) @foreach ($photoErrors as $message)<small class="field-error">{{ $message }}</small>@endforeach @endforeach
    </section>

    <section class="panel case-form-panel case-routing-panel" aria-labelledby="routing-heading">
        <div class="case-section-heading"><span class="case-step">04</span><div><h2 id="routing-heading">Review and consent</h2><p>Choose a dermatologist to review this case.</p></div></div>
        @if ($dermatologists->isEmpty())
            <div class="empty-doctor-note">No active dermatologists are available yet. Ask an administrator to invite and activate a dermatologist before submitting a case.</div>
        @else
            <label class="case-field case-doctor-field"><span>Assign dermatologist <b aria-hidden="true">*</b></span><select name="assigned_to" required><option value="">Choose a dermatologist</option>@foreach ($dermatologists as $dermatologist)<option value="{{ $dermatologist->id }}" @selected((string) old('assigned_to') === (string) $dermatologist->id)>{{ $dermatologist->full_name }}{{ $dermatologist->specialty ? ' · '.$dermatologist->specialty : '' }}</option>@endforeach</select>@error('assigned_to')<small class="field-error">{{ $message }}</small>@enderror</label>
        @endif
        <label class="case-consent-row"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>I confirm the patient (or their guardian) has consented to sharing these details and photos with the assigned care team.</span></label>
        @error('consent')<small class="field-error">{{ $message }}</small>@enderror
        <div class="case-form-actions"><a class="secondary-button" href="{{ route('cases.index') }}">Cancel</a><button class="primary-button" type="submit" @disabled($dermatologists->isEmpty())>Create and send case</button></div>
    </section>
</form>

<script>
(() => {
    const lookup = document.querySelector('.returning-patient-lookup');
    const codeInput = document.getElementById('patient-code-search');
    const lookupButton = document.getElementById('patient-lookup-button');
    const result = document.getElementById('patient-lookup-result');
    const patientIdInput = document.getElementById('selected-patient-id');
    const patientCodeInput = document.getElementById('selected-patient-code');
    const newPatientFields = document.getElementById('new-patient-fields');
    const followUpSelection = document.getElementById('follow-up-selection');
    const followUpCase = document.getElementById('follow-up-case');
    const confirmedPatientHistory = document.getElementById('confirmed-patient-history');
    const oldFollowUpId = @json(old('follow_up_of'));
    newPatientFields.querySelectorAll('input,select,textarea').forEach((field) => { field.dataset.wasDisabled = field.disabled ? 'true' : 'false'; });
    const renderHistory = (cases) => {
        const history = document.createElement('div');
        history.className = 'patient-lookup-history';
        const heading = document.createElement('h4'); heading.textContent = 'Previous visits and treatment'; history.append(heading);
        if (!cases.length) { const empty = document.createElement('p'); empty.textContent = 'No previous visits are available.'; history.append(empty); }
        cases.forEach((visit) => {
            const article = document.createElement('article');
            const title = document.createElement('strong'); title.textContent = `Case #${visit.id} · ${visit.created_at || ''}`; article.append(title);
            const doctor = document.createElement('p'); doctor.textContent = `Dermatologist: ${visit.doctor || 'Not assigned'}`; article.append(doctor);
            [['Diagnosis and treatment', (visit.treatments || []).map((treatment) => `${treatment.diagnosis}: ${treatment.treatment}`).join(' · ')], ['Symptoms', visit.symptoms], ['Findings', visit.finding], ['History', visit.history]].forEach(([label, value]) => {
                if (!value) return;
                const line = document.createElement('p'); line.textContent = `${label}: ${value}`; article.append(line);
            });
            history.append(article);
        });
        return history;
    };
    const findPatient = async () => {
        const code = codeInput.value.trim().toUpperCase();
        if (!code) { codeInput.focus(); return; }
        lookupButton.disabled = true; lookupButton.textContent = 'Searching…'; result.hidden = false; result.replaceChildren();
        try {
            const response = await fetch(`${lookup.dataset.lookupUrl}?patient_code=${encodeURIComponent(code)}`, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Patient not found.');
            const card = document.createElement('div'); card.className = 'patient-lookup-card';
            const heading = document.createElement('div');
            const name = document.createElement('strong'); name.textContent = payload.patient.name;
            const details = document.createElement('span'); details.textContent = `${payload.patient.patient_code} · ${payload.patient.age} years · ${payload.patient.gender} · ${payload.patient.municipality}, ${payload.patient.district}`;
            heading.append(name, details); card.append(heading, renderHistory(payload.cases));
            const choose = document.createElement('button'); choose.type = 'button'; choose.className = 'primary-button small-button'; choose.textContent = 'Use this patient';
            choose.addEventListener('click', () => {
                patientIdInput.value = payload.patient.id; patientCodeInput.value = payload.patient.patient_code;
                newPatientFields.hidden = true; newPatientFields.querySelectorAll('input,select,textarea').forEach((field) => { field.disabled = true; });
                followUpSelection.hidden = false;
                followUpCase.replaceChildren(new Option('New case or unrelated concern', ''));
                payload.cases.forEach((visit) => {
                    const latestPlan = (visit.treatments || []).map((treatment) => treatment.diagnosis).filter(Boolean).join(', ');
                    const label = `Case #${visit.id} · ${visit.created_at || 'date unavailable'}${latestPlan ? ` · ${latestPlan}` : ''}`;
                    followUpCase.add(new Option(label, visit.id, false, String(visit.id) === String(oldFollowUpId)));
                });
                confirmedPatientHistory.replaceChildren(renderHistory(payload.cases));
                choose.textContent = 'Patient selected'; choose.disabled = true;
                let clear = result.querySelector('[data-clear-patient]');
                if (!clear) { clear = document.createElement('button'); clear.type = 'button'; clear.className = 'secondary-button small-button'; clear.dataset.clearPatient = 'true'; clear.textContent = 'Choose a different patient'; result.append(clear); }
            });
            card.append(choose); result.replaceChildren(card);
            if (lookup.dataset.oldId && String(lookup.dataset.oldId) === String(payload.patient.id)) choose.click();
        } catch (error) {
            const message = document.createElement('p'); message.className = 'patient-lookup-error'; message.textContent = error.message; result.append(message);
        } finally { lookupButton.disabled = false; lookupButton.textContent = 'Find patient'; }
    };
    lookupButton.addEventListener('click', findPatient);
    codeInput.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); findPatient(); } });
    result.addEventListener('click', (event) => {
        if (!event.target.closest('[data-clear-patient]')) return;
        patientIdInput.value = ''; patientCodeInput.value = ''; newPatientFields.hidden = false; followUpSelection.hidden = true;
        followUpCase.value = ''; confirmedPatientHistory.replaceChildren();
        newPatientFields.querySelectorAll('input,select,textarea').forEach((field) => { field.disabled = field.dataset.wasDisabled === 'true'; });
        event.target.remove(); result.replaceChildren();
    });
    if (codeInput.value.trim()) findPatient();

    const province = document.getElementById('province');
    const district = document.getElementById('district');
    const municipality = document.getElementById('municipality');
    const endpointBase = @json(url('/places'));
    const fillSelect = (select, places, placeholder, selected = '') => {
        select.replaceChildren(new Option(placeholder, ''));
        places.forEach((place) => {
            const label = place.name_ne ? `${place.name} · ${place.name_ne}` : place.name;
            const option = new Option(label, place.id, false, String(place.id) === String(selected));
            select.add(option);
        });
        select.disabled = places.length === 0;
    };
    const loadChildren = async (parent, target, placeholder, selected = '') => {
        if (!parent) { fillSelect(target, [], placeholder); return; }
        target.disabled = true;
        target.replaceChildren(new Option('Loading…', ''));
        try {
            const response = await fetch(`${endpointBase}/${encodeURIComponent(parent)}/children`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Locations could not be loaded');
            fillSelect(target, await response.json(), placeholder, selected);
        } catch (_) {
            fillSelect(target, [], 'Unable to load options. Please try again.');
        }
    };
    province.addEventListener('change', () => {
        municipality.replaceChildren(new Option('Choose district first', ''));
        municipality.disabled = true;
        loadChildren(province.value, district, 'Choose district');
    });
    district.addEventListener('change', () => loadChildren(district.value, municipality, 'Choose municipality / local level'));
    if (province.value) {
        loadChildren(province.value, district, 'Choose district', district.dataset.old).then(() => {
            if (district.value) return loadChildren(district.value, municipality, 'Choose municipality / local level', municipality.dataset.old);
        });
    }

    const picker = document.getElementById('case-photos');
    const preview = document.getElementById('case-photo-preview');
    const limitMessage = document.getElementById('case-photo-limit-message');
    const pendingPhotos = [];
    let previewUrls = [];

    const syncPickerFiles = () => {
        const transfer = new DataTransfer();
        pendingPhotos.forEach((file) => transfer.items.add(file));
        picker.files = transfer.files;
    };

    const renderPhotoPreviews = () => {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls = [];
        preview.replaceChildren();

        pendingPhotos.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'case-photo-preview-item';
            const image = document.createElement('img');
            const imageUrl = URL.createObjectURL(file);
            previewUrls.push(imageUrl);
            image.src = imageUrl;
            image.alt = `Preview of ${file.name}`;
            const caption = document.createElement('span');
            caption.textContent = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'case-photo-remove';
            remove.textContent = 'Remove';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.addEventListener('click', () => {
                pendingPhotos.splice(index, 1);
                syncPickerFiles();
                renderPhotoPreviews();
            });
            item.append(image, caption, remove);
            preview.append(item);
        });
    };

    picker.addEventListener('change', () => {
        const newlySelected = Array.from(picker.files || []);
        if (newlySelected.length === 0) return;

        if (pendingPhotos.length + newlySelected.length > 10) {
            limitMessage.textContent = 'You can attach up to 10 photos. Remove a photo before adding more.';
            limitMessage.hidden = false;
            syncPickerFiles();
            renderPhotoPreviews();
            return;
        }

        limitMessage.textContent = '';
        limitMessage.hidden = true;
        pendingPhotos.push(...newlySelected);
        syncPickerFiles();
        renderPhotoPreviews();
    });
})();
</script>
@endsection
