<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educational_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('display_name', 160);
            $table->string('original_file_name', 255);
            $table->string('file_path', 255)->unique();
            $table->unsignedBigInteger('file_size_bytes');
            $table->timestamps();
            $table->index('display_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_resources');
    }
};
