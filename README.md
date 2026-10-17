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
