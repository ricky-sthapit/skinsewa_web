<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    /** Only administrators can choose staff levels; public registration never accepts a role. */
    public const INVITABLE_ROLES = [User::ROLE_MEDICAL_OFFICER, User::ROLE_DERMATOLOGIST];

    public function invite(string $email, string $role, ?string $facilityName, int $invitedBy): Registration
    {
        $email = mb_strtolower(trim($email));

        if (! in_array($role, self::INVITABLE_ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Only health workers and dermatologists can be invited.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        $otp = (string) random_int(100000, 999999);
        $ttl = max(1, (int) config('care.otp_ttl_minutes', 15));

        $invitation = DB::transaction(function () use ($email, $role, $facilityName, $invitedBy, $otp, $ttl): Registration {
            $invitation = Registration::query()->firstOrNew(['email' => $email]);
            $invitation->fill([
                'code' => Hash::make($otp),
                'is_registered' => false,
                'created_at' => now(),
                'role' => $role,
                'facility_name' => $facilityName,
                'invited_by' => $invitedBy,
                'otp_expires_at' => now()->addMinutes($ttl),
                'otp_attempts' => 0,
                'accepted_at' => null,
                'revoked_at' => null,
            ])->save();

            return $invitation;
        });

        // Deliver only after the hashed one-time code has been committed.
        $registrationUrl = URL::temporarySignedRoute(
            'registration.show',
            $invitation->otp_expires_at,
            ['registration' => $invitation->getKey()],
        );
        $invitation->notify(new RegistrationOtpNotification($otp, $ttl, $registrationUrl));

        return $invitation;
    }

    public function register(array $attributes): User
    {
        $email = mb_strtolower(trim($attributes['email']));

        $user = DB::transaction(function () use ($attributes, $email): ?User {
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
            }

            $invitation = Registration::query()->where('email', $email)->lockForUpdate()->first();
            if (! $invitation || $invitation->is_registered || $invitation->revoked_at) {
                throw ValidationException::withMessages(['otp' => 'The invitation is invalid or no longer available.']);
            }

            if (! $invitation->otp_expires_at || $invitation->otp_expires_at->isPast()) {
                throw ValidationException::withMessages(['otp' => 'The registration code has expired. Ask an administrator for a new invitation.']);
            }

            $maxAttempts = max(1, (int) config('care.otp_max_attempts', 5));
            if ($invitation->otp_attempts >= $maxAttempts) {
                throw ValidationException::withMessages(['otp' => 'Too many attempts. Ask an administrator to resend the invitation.']);
            }

            if (! Hash::check($attributes['otp'], $invitation->code)) {
                $invitation->otp_attempts++;
                if ($invitation->otp_attempts >= $maxAttempts) {
                    $invitation->revoked_at = now();
                }
                $invitation->save();

                return null;
            }

            $role = in_array($invitation->role, User::ROLES, true)
                ? $invitation->role
                : User::ROLE_MEDICAL_OFFICER;

            $user = User::query()->create([
                'first_name' => trim($attributes['first_name']),
                'last_name' => trim($attributes['last_name']),
                'email' => $email,
                'password' => $attributes['password'],
                'user_type' => $role,
                'is_admin' => $role === User::ROLE_ADMIN,
                'phone' => $attributes['phone'] ?? null,
                'facility_name' => $attributes['facility_name'] ?? $invitation->facility_name,
                'specialty' => $attributes['specialty'] ?? null,
                'email_verified_at' => now(),
            ]);

            // Consume the invite and make the saved hash unusable for any later attempt.
            $invitation->forceFill([
                'is_registered' => true,
                'accepted_at' => now(),
                'code' => Hash::make(Str::random(64)),
            ])->save();

            return $user;
        });

        if (! $user) {
            throw ValidationException::withMessages(['otp' => 'The email or registration code is incorrect.']);
        }

        return $user;
    }
}
