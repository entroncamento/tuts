<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_material_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('source', 30);
            $table->unsignedBigInteger('material_id');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('subject_sections')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index(['source', 'material_id']);
            $table->index('subject_id');
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_material_refs');
    }
};
