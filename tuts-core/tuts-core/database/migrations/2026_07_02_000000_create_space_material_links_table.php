<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_material_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_space_id')->constrained('study_spaces')->cascadeOnDelete();
            $table->foreignId('space_folder_id')->nullable()->constrained('space_folders')->nullOnDelete();
            $table->string('material_type', 30);
            $table->unsignedBigInteger('material_id');
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('study_space_id');
            $table->index('space_folder_id');
            $table->index(['material_type', 'material_id']);
            $table->index('added_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_material_links');
    }
};
