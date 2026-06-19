<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('subject_sections', 'visible_to_students')) {
                $table->boolean('visible_to_students')->default(true)->after('description');
            }

            if (!Schema::hasColumn('subject_sections', 'visible_from')) {
                $table->timestamp('visible_from')->nullable()->after('visible_to_students');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subject_sections', function (Blueprint $table) {
            if (Schema::hasColumn('subject_sections', 'visible_from')) {
                $table->dropColumn('visible_from');
            }

            if (Schema::hasColumn('subject_sections', 'visible_to_students')) {
                $table->dropColumn('visible_to_students');
            }
        });
    }
};
