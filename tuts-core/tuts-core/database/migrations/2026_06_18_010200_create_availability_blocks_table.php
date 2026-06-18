<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('repeat_type', 30)->default('none');
            $table->json('repeat_days')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('color', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['starts_on', 'ends_on']);
            $table->index('repeat_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
