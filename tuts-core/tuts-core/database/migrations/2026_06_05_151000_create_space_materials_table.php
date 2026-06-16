<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('study_space_id')
                ->constrained('study_spaces')
                ->cascadeOnDelete();

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('disk')->default('local');
            $table->string('path');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'study_space_id']);
            $table->index(['study_space_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_materials');
    }
};
