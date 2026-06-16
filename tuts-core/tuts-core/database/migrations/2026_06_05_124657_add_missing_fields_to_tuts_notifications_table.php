<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tuts_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('tuts_notifications', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('tuts_notifications', 'type')) {
                $table->string('type')->default('system')->after('user_id');
            }

            if (!Schema::hasColumn('tuts_notifications', 'title')) {
                $table->string('title')->nullable()->after('type');
            }

            if (!Schema::hasColumn('tuts_notifications', 'body')) {
                $table->text('body')->nullable()->after('title');
            }

            if (!Schema::hasColumn('tuts_notifications', 'data')) {
                $table->json('data')->nullable()->after('body');
            }

            if (!Schema::hasColumn('tuts_notifications', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('data');
            }

            if (!Schema::hasColumn('tuts_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('scheduled_for');
            }

            if (!Schema::hasColumn('tuts_notifications', 'created_at')) {
                $table->timestamps();
            }
        });

        Schema::table('tuts_notifications', function (Blueprint $table) {
            try {
                $table->index(['user_id', 'read_at'], 'tuts_notifications_user_read_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->index(['user_id', 'scheduled_for'], 'tuts_notifications_user_scheduled_idx');
            } catch (Throwable $e) {
                //
            }
        });
    }

    public function down(): void
    {
        Schema::table('tuts_notifications', function (Blueprint $table) {
            try {
                $table->dropIndex('tuts_notifications_user_read_idx');
            } catch (Throwable $e) {
                //
            }

            try {
                $table->dropIndex('tuts_notifications_user_scheduled_idx');
            } catch (Throwable $e) {
                //
            }

            if (Schema::hasColumn('tuts_notifications', 'read_at')) {
                $table->dropColumn('read_at');
            }

            if (Schema::hasColumn('tuts_notifications', 'scheduled_for')) {
                $table->dropColumn('scheduled_for');
            }

            if (Schema::hasColumn('tuts_notifications', 'data')) {
                $table->dropColumn('data');
            }

            if (Schema::hasColumn('tuts_notifications', 'body')) {
                $table->dropColumn('body');
            }

            if (Schema::hasColumn('tuts_notifications', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('tuts_notifications', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('tuts_notifications', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
