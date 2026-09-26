<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the read paths that run on every public verification.
 *
 * The verify lookup filters university_id + graduation_year exactly (the name
 * fields use leading-wildcard LIKE and cannot be indexed), so a composite on
 * those two turns a full-table scan into a narrow range scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index(['university_id', 'graduation_year'], 'students_uni_year_idx');
            $table->index('graduate_name', 'students_name_idx');
        });

        Schema::table('verification_logs', function (Blueprint $table) {
            $table->index('verifier_email', 'vlogs_verifier_email_idx');
            $table->index(['university_id', 'status'], 'vlogs_uni_status_idx');
            $table->index(['status', 'created_at'], 'vlogs_status_created_idx');
        });

        Schema::table('degrees', function (Blueprint $table) {
            $table->index(['university_id', 'status'], 'degrees_uni_status_idx');
        });

        Schema::table('universities', function (Blueprint $table) {
            $table->index('status', 'universities_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_uni_year_idx');
            $table->dropIndex('students_name_idx');
        });

        Schema::table('verification_logs', function (Blueprint $table) {
            $table->dropIndex('vlogs_verifier_email_idx');
            $table->dropIndex('vlogs_uni_status_idx');
            $table->dropIndex('vlogs_status_created_idx');
        });

        Schema::table('degrees', function (Blueprint $table) {
            $table->dropIndex('degrees_uni_status_idx');
        });

        Schema::table('universities', function (Blueprint $table) {
            $table->dropIndex('universities_status_idx');
        });
    }
};
