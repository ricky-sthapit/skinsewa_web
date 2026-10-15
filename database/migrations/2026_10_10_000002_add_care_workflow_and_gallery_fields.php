<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable();
            }
            if (! Schema::hasColumn('users', 'facility_name')) {
                $table->string('facility_name', 150)->nullable();
            }
            if (! Schema::hasColumn('users', 'specialty')) {
                $table->string('specialty', 120)->nullable();
            }
        });

        Schema::table('registration', function (Blueprint $table): void {
            if (! Schema::hasColumn('registration', 'role')) {
                $table->string('role', 32)->default('MEDICAL_OFFICER');
            }
            if (! Schema::hasColumn('registration', 'facility_name')) {
                $table->string('facility_name', 150)->nullable();
            }
            if (! Schema::hasColumn('registration', 'invited_by')) {
                $table->unsignedInteger('invited_by')->nullable();
            }
            if (! Schema::hasColumn('registration', 'otp_expires_at')) {
                $table->timestamp('otp_expires_at')->nullable();
            }
            if (! Schema::hasColumn('registration', 'otp_attempts')) {
                $table->unsignedSmallInteger('otp_attempts')->default(0);
            }
            if (! Schema::hasColumn('registration', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (! Schema::hasColumn('registration', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable();
            }
        });

        Schema::table('cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('cases', 'status')) {
                $table->string('status', 32)->default('awaiting_review');
            }
            if (! Schema::hasColumn('cases', 'priority')) {
                $table->string('priority', 16)->default('normal');
            }
            if (! Schema::hasColumn('cases', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
            if (! Schema::hasColumn('cases', 'consent_confirmed_at')) {
                $table->timestamp('consent_confirmed_at')->nullable();
            }
        });

        Schema::table('images', function (Blueprint $table): void {
            if (! Schema::hasColumn('images', 'storage_path')) {
                $table->string('storage_path', 255)->nullable();
            }
            if (! Schema::hasColumn('images', 'storage_disk')) {
                $table->string('storage_disk', 32)->default('local');
            }
            if (! Schema::hasColumn('images', 'mime_type')) {
                $table->string('mime_type', 100)->nullable();
            }
            if (! Schema::hasColumn('images', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->nullable();
            }
            if (! Schema::hasColumn('images', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('images', 'caption')) {
                $table->string('caption', 255)->nullable();
            }
            if (! Schema::hasColumn('images', 'sha256')) {
                $table->char('sha256', 64)->nullable();
            }
        });

        Schema::table('queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('queries', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
        });

        Schema::table('treatments', function (Blueprint $table): void {
            if (! Schema::hasColumn('treatments', 'follow_up_at')) {
                $table->timestamp('follow_up_at')->nullable();
            }
            if (! Schema::hasColumn('treatments', 'status')) {
                $table->string('status', 24)->default('active');
            }
        });

        Schema::table('pdf_resources', function (Blueprint $table): void {
            if (! Schema::hasColumn('pdf_resources', 'storage_path')) {
                $table->string('storage_path', 255)->nullable();
            }
            if (! Schema::hasColumn('pdf_resources', 'mime_type')) {
                $table->string('mime_type', 100)->default('application/pdf');
            }
            if (! Schema::hasColumn('pdf_resources', 'is_published')) {
                $table->boolean('is_published')->default(true);
            }
        });

        Schema::table('patients', function (Blueprint $table): void {
            if (! Schema::hasColumn('patients', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable();
            }
            if (! Schema::hasColumn('patients', 'consent_recorded_at')) {
                $table->timestamp('consent_recorded_at')->nullable();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            $this->convertLegacyTablesToInnoDb();
            $this->normalizeLegacyForeignKeyColumnTypes();
            $this->addIndexesAndForeignKeys();
        }

        // Existing open cases were not given a workflow status in the old dump.
        // The additive default marks them as awaiting review without deleting
        // or rewriting clinical details, photos, conversations, or resources.
    }

    private function convertLegacyTablesToInnoDb(): void
    {
        foreach ([
            'users', 'patients', 'cases', 'images', 'treatments', 'queries',
            'registration', 'pdf_resources', 'topics', 'places', 'personal_access_tokens',
            'failed_jobs', 'password_resets', 'migrations',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    private function normalizeLegacyForeignKeyColumnTypes(): void
    {
        $changes = [
            ['patients', 'created_by'],
            ['cases', 'patient_id'], ['cases', 'created_by'], ['cases', 'assigned_to'],
            ['images', 'case_id'], ['images', 'user_id'],
            ['treatments', 'treatment_by'], ['treatments', 'case_id'],
            ['queries', 'case_id'], ['queries', 'user_id'],
            ['registration', 'invited_by'], ['pdf_resources', 'user_id'], ['topics', 'user_id'],
        ];

        foreach ($changes as [$tableName, $columnName]) {
            if (! Schema::hasColumn($tableName, $columnName)) {
                continue;
            }
            // Source tables use a mix of signed INT, unsigned INT, and BIGINT
            // for the same user/case keys. Normalize to unsigned INT, matching
            // the legacy primary keys, before attaching referential constraints.
            $nullableReferences = [
                'patients.created_by', 'cases.created_by', 'images.user_id',
                'registration.invited_by',
            ];
            $nullable = in_array($tableName.'.'.$columnName, $nullableReferences, true);
            $defaults = ['pdf_resources.user_id' => ' DEFAULT 1', 'topics.user_id' => ' DEFAULT 1'];
            $default = $defaults[$tableName.'.'.$columnName] ?? '';
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` INT UNSIGNED".($nullable ? ' NULL' : ' NOT NULL').$default);
        }

        // The sample dump contains historical attribution IDs for a user row
        // that no longer exists. Keep the clinical records and clear only those
        // stale attribution references so the new foreign keys can be enforced.
        DB::statement('UPDATE `patients` p LEFT JOIN `users` u ON u.id = p.created_by SET p.created_by = NULL WHERE u.id IS NULL');
        DB::statement('UPDATE `cases` c LEFT JOIN `users` u ON u.id = c.created_by SET c.created_by = NULL WHERE u.id IS NULL');
        DB::statement('UPDATE `images` i LEFT JOIN `users` u ON u.id = i.user_id SET i.user_id = NULL WHERE u.id IS NULL');
    }

    private function addIndexesAndForeignKeys(): void
    {
        if (! $this->indexExists('images', 'images_case_sort_index')) {
            DB::statement('ALTER TABLE `images` ADD INDEX `images_case_sort_index` (`case_id`, `sort_order`, `id`)');
        }
        if (! $this->indexExists('queries', 'queries_case_id_id_index')) {
            DB::statement('ALTER TABLE `queries` ADD INDEX `queries_case_id_id_index` (`case_id`, `id`)');
        }
        if (! $this->indexExists('cases', 'cases_assignee_status_index')) {
            DB::statement('ALTER TABLE `cases` ADD INDEX `cases_assignee_status_index` (`assigned_to`, `status`)');
        }

        $foreignKeys = [
            ['patients', 'fk_sc_patients_creator', 'created_by', 'users'],
            ['cases', 'fk_sc_cases_patient', 'patient_id', 'patients'],
            ['cases', 'fk_sc_cases_creator', 'created_by', 'users'],
            ['cases', 'fk_sc_cases_doctor', 'assigned_to', 'users'],
            ['images', 'fk_sc_images_case', 'case_id', 'cases'],
            ['images', 'fk_sc_images_uploader', 'user_id', 'users'],
            ['treatments', 'fk_sc_treatments_case', 'case_id', 'cases'],
            ['treatments', 'fk_sc_treatments_doctor', 'treatment_by', 'users'],
            ['queries', 'fk_sc_queries_case', 'case_id', 'cases'],
            ['queries', 'fk_sc_queries_sender', 'user_id', 'users'],
            ['registration', 'fk_sc_registration_inviter', 'invited_by', 'users'],
            ['pdf_resources', 'fk_sc_resources_uploader', 'user_id', 'users'],
            ['topics', 'fk_sc_topics_uploader', 'user_id', 'users'],
        ];

        foreach ($foreignKeys as [$table, $name, $column, $parent]) {
            if ($this->foreignKeyExists($name)) {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`) ON UPDATE CASCADE");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $name): bool
    {
        return DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('constraint_name', $name)
            ->exists();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'fk_sc_patients_creator', 'fk_sc_cases_patient', 'fk_sc_cases_creator',
                'fk_sc_cases_doctor', 'fk_sc_images_case', 'fk_sc_images_uploader',
                'fk_sc_treatments_case', 'fk_sc_treatments_doctor',
                'fk_sc_queries_case', 'fk_sc_queries_sender', 'fk_sc_registration_inviter',
                'fk_sc_resources_uploader', 'fk_sc_topics_uploader',
            ] as $constraint) {
            foreach (['patients', 'cases', 'images', 'treatments', 'queries', 'registration', 'pdf_resources', 'topics'] as $table) {
                    if ($this->foreignKeyExists($constraint)) {
                        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
                        break;
                    }
                }
            }
        }

        foreach ([
            'users' => ['phone', 'facility_name', 'specialty'],
            'registration' => ['role', 'facility_name', 'invited_by', 'otp_expires_at', 'otp_attempts', 'accepted_at', 'revoked_at'],
            'cases' => ['status', 'priority', 'closed_at', 'consent_confirmed_at'],
            'images' => ['storage_path', 'storage_disk', 'mime_type', 'size_bytes', 'sort_order', 'caption', 'sha256'],
            'queries' => ['read_at'],
            'treatments' => ['follow_up_at', 'status'],
            'pdf_resources' => ['storage_path', 'mime_type', 'is_published'],
            'patients' => ['date_of_birth', 'consent_recorded_at'],
        ] as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }

        if (DB::getDriverName() === 'mysql') {
            foreach ([['images', 'images_case_sort_index'], ['queries', 'queries_case_id_id_index'], ['cases', 'cases_assignee_status_index']] as [$table, $index]) {
                if ($this->indexExists($table, $index)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
                }
            }
        }
    }
};
