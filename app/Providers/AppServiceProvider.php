<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = config('care.password_reset_url') ?: rtrim((string) config('app.url'), '/').'/reset-password';

            return $base.'?'.http_build_query(['token' => $token, 'email' => $user->email]);
        });
    }
}
