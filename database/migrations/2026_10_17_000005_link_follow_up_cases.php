<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cases', 'follow_up_of')) {
            Schema::table('cases', function (Blueprint $table): void {
                $table->unsignedInteger('follow_up_of')->nullable()->after('patient_id');
                $table->index('follow_up_of', 'cases_follow_up_of_index');
                $table->foreign('follow_up_of', 'cases_follow_up_of_foreign')
                    ->references('id')->on('cases')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cases', 'follow_up_of')) {
            Schema::table('cases', function (Blueprint $table): void {
                $table->dropForeign('cases_follow_up_of_foreign');
                $table->dropIndex('cases_follow_up_of_index');
                $table->dropColumn('follow_up_of');
            });
        }
    }
};
