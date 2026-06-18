<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('color', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_id', 'start_at', 'end_at']);
            $table->index(['created_by', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_events');
    }
};
