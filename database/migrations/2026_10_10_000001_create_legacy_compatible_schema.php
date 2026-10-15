<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the tables represented in rickycom_dcrn.sql for a fresh install.
     * Existing imports are adopted in place, so a restored dump can be upgraded.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('first_name', 100)->default('');
                $table->string('last_name', 100)->default('');
                $table->string('email', 100)->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 100);
                $table->enum('user_type', ['MEDICAL_OFFICER', 'DERMATOLOGIST', 'ADMIN']);
                $table->boolean('is_admin')->default(false);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->enum('gender', ['Male', 'Female']);
                $table->unsignedInteger('age');
                $table->string('phc', 50);
                $table->string('province', 100);
                $table->string('district', 100);
                $table->string('municipality', 100);
                $table->string('phone', 100)->nullable();
                $table->string('email', 100)->nullable();
                $table->double('weight', 8, 2)->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cases')) {
            Schema::create('cases', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('phc', 100)->nullable();
                $table->text('finding');
                $table->text('history');
                $table->text('symptoms');
                $table->unsignedInteger('patient_id');
                $table->unsignedInteger('created_by');
                $table->unsignedInteger('assigned_to');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('images')) {
            Schema::create('images', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('filename', 100);
                $table->unsignedInteger('case_id');
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('treatments')) {
            Schema::create('treatments', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('diagnosis', 1000);
                $table->string('prevention', 1000);
                $table->string('treatment', 1000);
                $table->string('referral', 1000);
                $table->unsignedInteger('treatment_by');
                $table->unsignedInteger('case_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('queries')) {
            Schema::create('queries', function (Blueprint $table): void {
                $table->increments('id');
                $table->text('detail');
                $table->unsignedInteger('case_id');
                $table->unsignedInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('registration')) {
            Schema::create('registration', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email', 100)->unique();
                // Holds a Laravel password hash for all new invitation OTPs.
                $table->string('code', 100);
                $table->boolean('is_registered')->default(false);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('pdf_resources')) {
            Schema::create('pdf_resources', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title', 100);
                $table->string('file', 100);
                $table->unsignedInteger('user_id')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title', 100);
                $table->text('description');
                $table->unsignedInteger('user_id')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('places')) {
            Schema::create('places', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('name_ne', 100)->nullable();
                $table->enum('type', ['Province', 'District', 'Metropolitan City', 'Municipality', 'Rural Municipality', 'Sub-Metropolitan City']);
                $table->integer('parent')->default(0);
                $table->integer('level');
            });
        }

        // Laravel 13/Sanctum tables. These are separate from the legacy
        // password_resets table and use the framework's standard names.
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table): void {
                $table->string('email', 100)->index();
                $table->string('token', 100);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('tokenable_type', 100);
                $table->unsignedBigInteger('tokenable_id');
                $table->string('name', 100);
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['tokenable_type', 'tokenable_id']);
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'sessions', 'cache_locks', 'cache', 'failed_jobs', 'jobs',
            'personal_access_tokens', 'password_reset_tokens', 'password_resets', 'places', 'topics',
            'pdf_resources', 'registration', 'queries', 'treatments', 'images',
            'cases', 'patients', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
