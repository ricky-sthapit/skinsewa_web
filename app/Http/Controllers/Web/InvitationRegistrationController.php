<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class InvitationRegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function show(Registration $registration): View|RedirectResponse
    {
        if ($registration->is_registered) {
            return redirect()->route('home')->with('status', 'This invitation has already been used. Please sign in.');
        }

        abort_if($registration->revoked_at || ! $registration->otp_expires_at || $registration->otp_expires_at->isPast(), 410, 'This invitation has expired. Ask the administrator to resend it.');

        return view('auth.register', compact('registration'));
    }

    public function store(Request $request, Registration $registration): RedirectResponse
    {
        abort_if($registration->is_registered || $registration->revoked_at || ! $registration->otp_expires_at || $registration->otp_expires_at->isPast(), 410, 'This invitation has expired. Ask the administrator to resend it.');

        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
            'phone' => ['nullable', 'string', 'max:32'],
            'facility_name' => ['nullable', 'string', 'max:150'],
            'specialty' => ['nullable', 'string', 'max:120'],
        ]);
        $data['email'] = $registration->email;

        $this->registrations->register($data);

        return redirect()->route('home')->with('status', 'Your registration is complete. Sign in with your new account.');
    }
}
