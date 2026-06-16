<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('space_materials')) {
            return;
        }

        Schema::table('space_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('space_materials', 'space_folder_id')) {
                $table->foreignId('space_folder_id')
                    ->nullable()
                    ->after('study_space_id')
                    ->constrained('space_folders')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('space_materials') || !Schema::hasColumn('space_materials', 'space_folder_id')) {
            return;
        }

        Schema::table('space_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('space_folder_id');
        });
    }
};
