<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            if (!Schema::hasColumn('chats', 'retention_days')) {
                $table->unsignedTinyInteger('retention_days')->nullable();
            }

            if (!Schema::hasColumn('chats', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index('chats_expires_at_idx');
            }
        });

        DB::table('chats')
            ->where(function ($query) {
                $query->where('context_type', 'temporary')
                    ->orWhere('is_temporary', true);
            })
            ->whereNull('retention_days')
            ->update(['retention_days' => 7]);

        DB::table('chats')
            ->select(['id', 'created_at'])
            ->where(function ($query) {
                $query->where('context_type', 'temporary')
                    ->orWhere('is_temporary', true);
            })
            ->whereNull('expires_at')
            ->whereNotNull('created_at')
            ->orderBy('id')
            ->chunkById(200, function ($chats) {
                foreach ($chats as $chat) {
                    DB::table('chats')
                        ->where('id', $chat->id)
                        ->update([
                            'expires_at' => CarbonImmutable::parse($chat->created_at)->addDays(7),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'expires_at')) {
                try {
                    $table->dropIndex('chats_expires_at_idx');
                } catch (Throwable $e) {
                    // noop
                }

                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('chats', 'retention_days')) {
                $table->dropColumn('retention_days');
            }
        });
    }
};
