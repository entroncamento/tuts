<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_material_contexts', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->string('source'); // Expected values: 'personal', 'subject'
            
            $table->foreignId('personal_material_id')->nullable()->constrained('personal_materials')->nullOnDelete();
            $table->foreignId('subject_material_id')->nullable()->constrained('subject_materials')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            
            $table->foreignId('added_from_message_id')->nullable()->constrained('messages')->nullOnDelete();
            
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index(['chat_id', 'active']);
            $table->index(['user_id', 'active']);
            $table->index('source');
            $table->index('personal_material_id');
            $table->index('subject_material_id');
            $table->index('subject_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_material_contexts');
    }
};
