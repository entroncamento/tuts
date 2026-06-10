<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'study_space_id')) {
                $table->foreignId('study_space_id')->nullable()->constrained('study_spaces')->nullOnDelete();
            }

            if (!Schema::hasColumn('chats', 'context_type')) {
                $table->string('context_type')->default('uc');
            }

            if (!Schema::hasColumn('chats', 'is_temporary')) {
                $table->boolean('is_temporary')->default(false);
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            try {
                $table->index(['user_id', 'context_type'], 'chats_user_context_idx');
            } catch (Throwable $e) {
                // índice já existe ou driver não permite neste contexto
            }

            try {
                $table->index(['study_space_id', 'updated_at'], 'chats_space_updated_idx');
            } catch (Throwable $e) {
                // índice já existe ou driver não permite neste contexto
            }
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            try {
                $table->dropIndex('chats_user_context_idx');
            } catch (Throwable $e) {
                // noop
            }

            try {
                $table->dropIndex('chats_space_updated_idx');
            } catch (Throwable $e) {
                // noop
            }

            if (Schema::hasColumn('chats', 'is_temporary')) {
                $table->dropColumn('is_temporary');
            }

            if (Schema::hasColumn('chats', 'context_type')) {
                $table->dropColumn('context_type');
            }

            if (Schema::hasColumn('chats', 'study_space_id')) {
                $table->dropConstrainedForeignId('study_space_id');
            }
        });
    }
};
