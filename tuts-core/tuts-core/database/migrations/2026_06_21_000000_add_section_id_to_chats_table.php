<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chats') || Schema::hasColumn('chats', 'section_id')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('subject_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('chats') || !Schema::hasColumn('chats', 'section_id')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
