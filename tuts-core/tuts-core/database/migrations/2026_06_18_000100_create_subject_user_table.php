<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('status', 30)->default('active');
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->unique(['subject_id', 'user_id', 'role'], 'subject_user_subject_user_role_unique');
            $table->index(['user_id', 'role', 'status'], 'subject_user_user_role_status_idx');
            $table->index(['subject_id', 'role', 'status'], 'subject_user_subject_role_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_user');
    }
};
