<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);
            $table->string('scope', 30)->default('personal');
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('color', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'start_at', 'end_at']);
            $table->index(['subject_id', 'start_at', 'end_at']);
            $table->index(['kind', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_items');
    }
};
