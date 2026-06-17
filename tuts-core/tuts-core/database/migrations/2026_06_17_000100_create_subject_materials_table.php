<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('subject_sections')->nullOnDelete();
            $table->string('name');
            $table->string('type', 40)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('source', 40)->default('official');
            $table->boolean('verified_by_teacher')->default(true);
            $table->timestamps();

            $table->index(['subject_id', 'section_id']);
            $table->index(['subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_materials');
    }
};
