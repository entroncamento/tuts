<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('subjects', 'acronym')) {
                $table->string('acronym', 30)->nullable()->after('url')->index();
            }

            if (!Schema::hasColumn('subjects', 'enrollment_code')) {
                $table->string('enrollment_code', 32)->nullable()->after('acronym')->unique();
            }

            if (!Schema::hasColumn('subjects', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('enrollment_code')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('subjects', 'degree')) {
                $table->string('degree')->nullable()->after('created_by');
            }

            if (!Schema::hasColumn('subjects', 'level')) {
                $table->string('level', 80)->nullable()->after('degree');
            }

            if (!Schema::hasColumn('subjects', 'year')) {
                $table->string('year', 40)->nullable()->after('level');
            }

            if (!Schema::hasColumn('subjects', 'semester')) {
                $table->string('semester', 40)->nullable()->after('year');
            }

            if (!Schema::hasColumn('subjects', 'academic_year')) {
                $table->string('academic_year', 20)->nullable()->after('semester');
            }

            if (!Schema::hasColumn('subjects', 'color')) {
                $table->string('color', 40)->nullable()->after('academic_year');
            }

            if (!Schema::hasColumn('subjects', 'status')) {
                $table->string('status', 30)->default('active')->after('color')->index();
            }

            if (!Schema::hasColumn('subjects', 'source')) {
                $table->string('source', 30)->default('import')->after('status')->index();
            }

            if (!Schema::hasColumn('subjects', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            foreach (['source', 'status', 'color', 'academic_year', 'semester', 'year', 'level', 'degree'] as $column) {
                if (Schema::hasColumn('subjects', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('subjects', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            if (Schema::hasColumn('subjects', 'enrollment_code')) {
                $table->dropUnique(['enrollment_code']);
                $table->dropColumn('enrollment_code');
            }

            if (Schema::hasColumn('subjects', 'acronym')) {
                $table->dropIndex(['acronym']);
                $table->dropColumn('acronym');
            }
        });
    }
};
