<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PLACE_TYPES = "'Province','District','Metropolitan City','Municipality','Rural Municipality','Sub-Metropolitan City'";

    public function up(): void
    {
        if (! Schema::hasColumn('places', 'name_ne')) {
            Schema::table('places', function (Blueprint $table): void {
                $table->string('name_ne', 100)->nullable()->after('name');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `places` MODIFY `type` ENUM('.self::PLACE_TYPES.') NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `places` MODIFY `type` ENUM('.self::PLACE_TYPES.') NOT NULL');
        }

        if (Schema::hasColumn('places', 'name_ne')) {
            Schema::table('places', function (Blueprint $table): void {
                $table->dropColumn('name_ne');
            });
        }
    }
};
