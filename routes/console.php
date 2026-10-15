<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('admin:create {email} {first_name} {last_name}', function (): int {
    $email = mb_strtolower(trim((string) $this->argument('email')));
    if (User::query()->where('email', $email)->exists()) {
        $this->error('An account already exists for that email.');

        return Command::FAILURE;
    }

    $password = $this->secret('New administrator password (minimum 10 characters):');
    if (! is_string($password) || strlen($password) < 10) {
        $this->error('Password must have at least 10 characters.');

        return Command::FAILURE;
    }

    User::query()->create([
        'email' => $email,
        'first_name' => trim((string) $this->argument('first_name')),
        'last_name' => trim((string) $this->argument('last_name')),
        'password' => $password,
        'user_type' => User::ROLE_ADMIN,
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    $this->info('Master administrator created. Use the registration invitation API to onboard staff.');

    return Command::SUCCESS;
})->purpose('Create the first SkinSewa administrator');
