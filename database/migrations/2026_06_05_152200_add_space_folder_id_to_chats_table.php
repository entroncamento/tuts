<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'space_folder_id')) {
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
        if (!Schema::hasTable('chats') || !Schema::hasColumn('chats', 'space_folder_id')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('space_folder_id');
        });
    }
};
