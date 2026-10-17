<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:100']]);
        $email = mb_strtolower(trim($data['email']));

        Password::sendResetLink(['email' => $email]);

        $message = config('mail.default') === 'log'
            ? 'If an account exists for that email, reset instructions were generated. In this local setup, check storage/logs/laravel.log.'
            : 'If an account exists for that email, password reset instructions have been sent.';

        return redirect()->route('password.request')->with('status', $message);
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        if (! is_string($token) || $token === '' || $email === '') {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Open the password reset link from your email, or request a new one.']);
        }

        return view('auth.reset-password', compact('token', 'email'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:100'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()->route('password.request')
                ->withErrors(['email' => __($status)]);
        }

        return redirect()->route('home')->with('status', 'Your password was reset. You can now sign in.');
    }
}
