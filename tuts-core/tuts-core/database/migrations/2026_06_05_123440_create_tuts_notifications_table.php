<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuts_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type')->default('system');
            // system, reminder, calendar, study_plan, material, teacher_message

            $table->string('title');
            $table->text('body')->nullable();

            $table->json('data')->nullable();

            // Se for null, aparece logo.
            // Se tiver data futura, só aparece quando chegar a hora.
            $table->timestamp('scheduled_for')->nullable()->index();

            $table->timestamp('read_at')->nullable()->index();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuts_notifications');
    }
};
