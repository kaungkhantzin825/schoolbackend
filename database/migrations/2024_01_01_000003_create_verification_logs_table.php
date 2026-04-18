<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained()->onDelete('set null');
            $table->string('verifier_name');
            $table->string('verifier_email')->nullable();
            $table->string('organization_type');
            $table->string('organization_name');
            $table->string('searched_name');
            $table->string('searched_father_name')->nullable();
            $table->string('searched_degree')->nullable();
            $table->year('searched_year')->nullable();
            $table->enum('result', ['verified', 'not_found'])->default('not_found');
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};
