# SkinSewa Laravel API

This project is being rebuilt in small, verifiable slices. It includes role-based accounts, administrator-issued registration invitations, OTP registration, bearer-token API login/logout, password reset, resources, and the Laravel care workspace for patient cases.

## Database

The app uses MySQL. Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`; the local development environment is configured for the `skinsewa` database. The checked-in migrations create the legacy-compatible tables and add the SkinSewa workflow fields. The locations seeder imports the supplied Nepal location JSON: 7 provinces, 77 districts, and 753 local levels. It stores English names in `name`, Nepali names in `name_ne`, and uses each local level’s `type_en` value for the `places.type` enum. Existing place IDs from the SQL dump are preserved.

Run the locations seeder after migrating, or seed the existing database with `php artisan db:seed`:

```sh
php artisan migrate --seed
```

## User levels

The legacy `users.user_type` field supports these levels:

- `ADMIN` — creates invitations and can manage all system areas.
- `MEDICAL_OFFICER` — the rural health worker who registers patients and creates cases.
- `DERMATOLOGIST` — the doctor who reviews cases assigned to them.

Public registration does not accept a role. An administrator chooses `MEDICAL_OFFICER` or `DERMATOLOGIST` when sending an invitation. The role is copied from the matching invitation after the user supplies the same invited email and OTP. The OTP is stored as a one-way hash, expires after `OTP_TTL_MINUTES`, and is locked after `OTP_MAX_ATTEMPTS` incorrect submissions.

Create the first administrator from the command line; it prompts for the password:

```sh
php artisan admin:create admin@example.org First Last
```

The local `.env.example` uses the log mailer, so invitations and reset links are written to `storage/logs/laravel.log`. Configure an approved SMTP or mail provider before sending real email.

Administrators can manage invitations from `/invitations`. Each invitation email contains a time-limited signed registration link and a one-time code. The registration page displays the invited email and user type as read-only values; the server derives both from the invitation record and requires the emailed code before creating the account. An admin can resend a fresh invitation from the invitation history page.

## Auth API

All routes are under `/api/v1`. Authenticated routes use `Authorization: Bearer <token>`.

| Method | Route | Purpose |
|---|---|---|
| POST | `/auth/register` | Accept a pending invite and create an account |
| POST | `/auth/login` | Verify email/password and issue a Sanctum token |
| POST | `/auth/logout` | Revoke the current bearer token |
| GET | `/auth/me` | Return the signed-in profile and role |
| POST | `/auth/forgot-password` | Send a password-reset link when an account exists |
| POST | `/auth/reset-password` | Set a new password using the reset token |
| POST | `/admin/registration-requests` | Admin-only invite for a health worker or dermatologist |

Registration request:

```json
{
  "email": "worker@example.org",
  "otp": "123456",
  "first_name": "Asha",
  "last_name": "Rai",
  "password": "CarePass123",
  "password_confirmation": "CarePass123",
  "phone": "+977..."
}
```

Admin invite request (requires an `ADMIN` bearer token):

```json
{
  "email": "doctor@example.org",
  "role": "DERMATOLOGIST",
  "facility_name": "City Clinic"
}
```

The invite response never contains the OTP. The registration response and login response each contain `{ data: { user, token, token_type } }`. The user payload includes the assigned level as `role`. Password reset responses do not reveal whether an email address has an account.

## Android API

All mobile routes use the `/api/v1` prefix. Send `Accept: application/json` on every request. For protected routes, send `Authorization: Bearer <token>`; send JSON as `Content-Type: application/json`, except uploads, which use `multipart/form-data`. The API returns JSON errors with Laravel validation details and HTTP `422`; inaccessible records return `404` to avoid disclosing their existence. IDs in path segments are database IDs.

| Method | Route | Access | Purpose |
|---|---|---|---|
| GET | `/api/health` | Public | Check API availability |
| POST | `/api/v1/auth/login` | Public | Sign in and receive a bearer token |
| POST | `/api/v1/auth/register` | Public | Register with invited email + six-digit OTP |
| POST | `/api/v1/auth/forgot-password` | Public | Email password reset link |
| POST | `/api/v1/auth/reset-password` | Public | Reset using emailed token |
| GET | `/api/v1/auth/me` | Signed in | Current user and role |
| POST | `/api/v1/auth/logout` | Signed in | Revoke current token |
| GET/PATCH | `/api/v1/profile` | Signed in | View/update phone, facility, specialty |
| GET | `/api/v1/places/provinces` | Signed in | List provinces |
| GET | `/api/v1/places/{place}/children` | Signed in | List districts for a province or local levels for a district |
| GET | `/api/v1/cases` | All roles | List accessible cases; admin sees all |
| GET | `/api/v1/cases/{case}` | All roles | Case, patient, history, photos, chat and treatment details |
| GET | `/api/v1/patients/lookup?patient_code=...` | Medical officer | Find a returning patient and prior cases |
| POST | `/api/v1/cases` | Medical officer | Create a patient visit/case and upload images |
| GET | `/api/v1/cases/{case}/messages?after_id=0` | All roles | Poll the conversation for new messages |
| POST | `/api/v1/cases/{case}/messages` | Case participants | Send a message and optional images |
| GET | `/api/v1/cases/{case}/photos/{photo}` | Case access | Read a private case/chat image |
| POST | `/api/v1/cases/{case}/treatments` | Assigned dermatologist | Add diagnosis and treatment plan; complete the case |
| GET | `/api/v1/resources` | Signed in | List/viewable PDF resources |
| GET | `/api/v1/resources/{resource}/view` | Signed in | Stream PDF inline |
| GET | `/api/v1/resources/{resource}/download` | Signed in | Download PDF |
| GET | `/api/v1/admin/dashboard` | Admin | Workspace counts, cases by status, recent cases |
| GET/GET by ID/PATCH/DELETE | `/api/v1/admin/users` | Admin | Search, inspect, edit or soft-delete users |
| GET/POST | `/api/v1/admin/invitations` | Admin | List invitations or invite a medical officer/dermatologist |
| POST | `/api/v1/admin/invitations/{id}/resend` | Admin | Issue a fresh invitation link and OTP |
| POST | `/api/v1/admin/registration-requests` | Admin | Compatibility invite endpoint (`role` field instead of `user_type`) |
| POST/POST by ID/DELETE | `/api/v1/admin/resources` | Admin | Create/update/delete PDF resources (multipart update uses POST `/.../{id}`) |

### Request examples

Login:

```json
{ "email": "worker@example.org", "password": "CarePass123" }
```

Case list supports `q`, `status`, `page`, and `per_page` query parameters. Page size is bounded to 5–25. The response shape is `{ "data": [...], "meta": { "current_page", "last_page", "per_page", "total", "available_statuses" } }`.

To create a new case, send `multipart/form-data` with `patient_name`, `gender` (`Male` or `Female`), `age`, `phc`, `province_id`, `district_id`, `municipality_id`, `finding`, `history`, `symptoms`, `assigned_to` (dermatologist user ID), and `consent=true`. Optional fields are `phone`, `email`, `weight`, `follow_up_of` (prior case ID), and repeated `photos[]` image files (up to 10 JPG/PNG/WebP images, max 10 MB each). For a returning patient, send `patient_id` instead of new-patient demographic/location fields; retrieve it using the patient lookup endpoint. A new case is created for every visit, including follow-ups.

To send a chat message, use `multipart/form-data` with `detail` and optional repeated `photos[]`. `detail` can be omitted when at least one photo is attached. The response includes `last_id` on message polling so the Android app can request only newer messages. Uploaded chat photos also appear in the case `photos` gallery.

The assigned dermatologist submits a JSON treatment plan such as:

```json
{
  "diagnosis": "Acne vulgaris",
  "treatment": "Apply the prescribed ointment nightly for two weeks.",
  "prevention": "Use a gentle cleanser and avoid picking lesions.",
  "referral": "",
  "follow_up_at": "2026-11-01T09:00:00+05:45"
}
```

The treatment is stored in the existing `treatments` table and the case status becomes `completed`. Case list filters return status strings from the database; the app should use `meta.available_statuses` rather than hard-coding a list.

Admin user listing accepts `q`, `user_type`, `page`, and `per_page` (5, 10, 15, 20, or 25). Invitation listing accepts `q`, `status` (`pending`, `accepted`, `expired`, `revoked`), `page`, and `per_page`. Resource creation and update use multipart fields `display_name` and `resource_file` (PDF, max 20 MB). Admin invite creation takes `email`, `user_type` (`MEDICAL_OFFICER` or `DERMATOLOGIST`), and optional `facility_name`; the OTP is sent by email and is never returned in API responses.

Conversation notifications can be bridged to a realtime service with the configured signed webhook described above. The app can use the message polling endpoint as a fallback; webhook delivery itself targets the configured server URL, not Android clients.

## Web homepage

The homepage (`/`) checks the web session: guests see the sign-in form, and signed-in users see the SkinSewa logo with their name, email, level, phone, facility, and specialty. Successful sign-in regenerates the session; sign-out invalidates it. Web sessions and mobile bearer tokens are separate.

## Patient cases, chat, and treatment

`/cases` shows cases created by a health worker, cases assigned to the signed-in dermatologist, or all cases to an administrator. Only the assigned dermatologist can add a diagnosis and course of action. The plan reuses the legacy `treatments` table (`diagnosis`, `prevention`, `treatment`, `referral`, `treatment_by`, and `case_id`); medicine and ointment instructions are recorded in its `treatment` field.

Returning patients keep one patient record and a stable location-based patient ID. Each visit creates a distinct case number; a health worker can optionally link a new case as a follow-up to an earlier case and review prior diagnoses and treatment plans. Saving a dermatologist's treatment plan marks that case completed.

The per-case conversation reuses the legacy `queries` table. Messages refresh in the browser every five seconds. A message can include up to 10 JPG, PNG, or WebP photos, each up to 10 MB. Attachments are stored as private entries in the existing `images` table and appear in both the conversation and the case gallery. The gallery supports fullscreen view, swipe navigation, and zoom controls. Administrators can read every case and conversation; only the case's creating health worker and assigned dermatologist can send messages.

When `CHAT_WEBHOOK_URL` and `CHAT_WEBHOOK_SECRET` are set, each new message sends a signed JSON `case.message.created` event to that URL. The signature is `sha256=` plus HMAC-SHA256 of `<unix timestamp>.<raw JSON body>`, using the configured secret. The request includes `X-SkinSewa-Event`, `X-SkinSewa-Event-Id`, `X-SkinSewa-Timestamp`, and `X-SkinSewa-Signature` headers. The webhook payload includes the case/message IDs, author, text, and attachment count. Delivery errors are logged and do not prevent the message from being saved. Leave both settings empty to keep webhook delivery disabled.

Apply the chat attachment migration with:

```sh
php artisan migrate
```

## Verification

The auth flow has been smoke-checked against MySQL with temporary records inside a transaction that was rolled back. Covered paths include administrator login/invitation, hashed OTP storage, rejected and accepted OTPs, invitation-derived roles, protected admin routes, login, current-user lookup, password reset, and revocation of old API tokens after a reset.

## Current implementation files

- `app/Models/User.php` and `app/Models/Registration.php` — legacy table mappings and role handling.
- `app/Services/RegistrationService.php` — OTP issue/verification and one-time invite consumption.
- `app/Http/Controllers/Api/V1/AuthController.php` — registration, login, reset, logout, and profile endpoints.
- `app/Http/Controllers/Api/V1/Admin/RegistrationRequestController.php` — administrator invitation endpoint.
- `app/Http/Middleware/EnsureRole.php` — role gate for admin routes.
- `database/migrations` — legacy schema and additive care workflow migration.
