<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_subject_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('cover_provider')->nullable();
            $table->string('cover_external_id')->nullable();
            $table->text('cover_image_url')->nullable();
            $table->text('cover_thumbnail_url')->nullable();
            $table->string('cover_color')->nullable();
            $table->string('cover_blur_hash')->nullable();
            $table->text('cover_alt')->nullable();
            $table->string('cover_photographer_name')->nullable();
            $table->text('cover_photographer_url')->nullable();
            $table->text('cover_source_url')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subject_preferences');
    }
};
