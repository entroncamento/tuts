<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_message_analyses', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys / References
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('chat_id')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedBigInteger('course_id')->nullable()->index();
            
            // Hashed student identifier for privacy
            $table->string('student_hash')->nullable()->index();
            
            $table->string('role')->nullable(); // role (e.g. user)
            $table->string('language')->nullable(); // language (pt, en)
            
            // Excerpt & Topics
            $table->string('question_excerpt', 300)->nullable(); // truncated excerpt without PII
            $table->string('topic', 80)->nullable()->index();
            $table->string('subtopic', 80)->nullable();
            $table->string('intent', 50)->nullable();
            
            // Scores (0..1)
            $table->decimal('confusion_score', 4, 3)->nullable();
            $table->decimal('frustration_score', 4, 3)->nullable();
            $table->decimal('urgency_score', 4, 3)->nullable();
            
            // Priority & sentiment
            $table->string('difficulty_level', 20)->nullable();
            $table->string('priority', 20)->nullable()->index();
            $table->string('sentiment', 20)->nullable();
            
            // Flags
            $table->boolean('is_recurring')->default(false);
            $table->boolean('needs_teacher_attention')->default(false);
            
            // Summaries / actions
            $table->string('llm_summary', 500)->nullable();
            $table->string('suggested_teacher_action', 500)->nullable();
            
            // Provider info
            $table->string('analysis_provider', 50)->nullable(); // e.g. rag, rules_fallback
            $table->string('analysis_version', 20)->nullable();
            
            // Raw JSON response
            $table->jsonb('raw_analysis')->nullable();
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_message_analyses');
    }
};
