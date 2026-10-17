<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'patient_code')) {
            Schema::table('patients', function (Blueprint $table): void {
                $table->string('patient_code', 40)->nullable()->unique();
            });
        }

        DB::table('patients')->whereNull('patient_code')->orderBy('id')->get([
            'id', 'province', 'district', 'municipality',
        ])->each(function (object $patient): void {
            $parts = collect([$patient->province, $patient->district, $patient->municipality])
                ->map(function (?string $place): string {
                    $ascii = Str::ascii(trim((string) $place));
                    preg_match('/[A-Za-z0-9]/', $ascii, $match);

                    return Str::upper($match[0] ?? 'X');
                });

            DB::table('patients')->where('id', $patient->id)->update([
                'patient_code' => $parts->implode('-').'-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'patient_code')) {
            Schema::table('patients', function (Blueprint $table): void {
                $table->dropUnique(['patient_code']);
                $table->dropColumn('patient_code');
            });
        }
    }
};
