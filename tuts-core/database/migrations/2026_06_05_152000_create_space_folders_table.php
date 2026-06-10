<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('space_folders')) {
            return;
        }

        Schema::create('space_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('space_folders')->nullOnDelete();
            $table->string('name', 120);
            $table->string('type', 40)->default('folder');
            $table->string('color', 30)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'study_space_id']);
            $table->index(['study_space_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_folders');
    }
};
