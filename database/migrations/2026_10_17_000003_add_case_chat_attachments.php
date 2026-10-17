<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('images', 'query_id')) {
            Schema::table('images', function (Blueprint $table): void {
                $table->unsignedInteger('query_id')->nullable()->after('case_id');
                $table->index('query_id', 'images_query_id_index');
                $table->foreign('query_id', 'images_query_id_foreign')
                    ->references('id')->on('queries')->nullOnDelete();
            });
        }

        Schema::table('queries', function (Blueprint $table): void {
            if (! Schema::hasColumn('queries', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('images', 'query_id')) {
            Schema::table('images', function (Blueprint $table): void {
                $table->dropForeign('images_query_id_foreign');
                $table->dropIndex('images_query_id_index');
                $table->dropColumn('query_id');
            });
        }
    }
};
