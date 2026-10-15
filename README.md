# SkinSewa Laravel API

This project is being rebuilt in small, verifiable slices. The current slice implements user levels, administrator-issued registration invitations, OTP registration, bearer-token API login/logout, session-based web login/logout, current-user lookup, password reset, and a minimal user dashboard.

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

## Verification

The auth flow has been smoke-checked against MySQL with temporary records inside a transaction that was rolled back. Covered paths include administrator login/invitation, hashed OTP storage, rejected and accepted OTPs, invitation-derived roles, protected admin routes, login, current-user lookup, password reset, and revocation of old API tokens after a reset.

## Current implementation files

- `app/Models/User.php` and `app/Models/Registration.php` — legacy table mappings and role handling.
- `app/Services/RegistrationService.php` — OTP issue/verification and one-time invite consumption.
- `app/Http/Controllers/Api/V1/AuthController.php` — registration, login, reset, logout, and profile endpoints.
- `app/Http/Controllers/Api/V1/Admin/RegistrationRequestController.php` — administrator invitation endpoint.
- `app/Http/Middleware/EnsureRole.php` — role gate for admin routes.
- `database/migrations` — legacy schema and additive care workflow migration.
